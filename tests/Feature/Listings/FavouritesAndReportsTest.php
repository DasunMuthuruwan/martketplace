<?php

namespace Tests\Feature\Listings;

use App\Actions\Account\DeleteAccount;
use App\Actions\Engagement\ResolveReport;
use App\Enums\ListingStatus;
use App\Enums\ReportStatus;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** Favourites (SRCH-08) and reports (MOD-02) — T4.6. */
class FavouritesAndReportsTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private User $buyer;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->buyer = User::factory()->create();
        $this->listing = Listing::factory()->create();
    }

    private function report(array $input, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->buyer)->postJson(route('reports.store'), $input + [
            'reportable_type' => 'listing', 'reportable_id' => $this->listing->id, 'reason' => 'scam',
        ]);
    }

    // ---- Favourites ---------------------------------------------------------------------------

    public function test_toggle_a_favourite(): void
    {
        $url = route('listings.favourite', $this->listing);
        $this->postJson($url)->assertUnauthorized(); // guests

        $this->actingAs($this->buyer)->postJson($url)->assertOk()->assertExactJson(['favourited' => true, 'count' => 1]);
        $this->actingAs(User::factory()->create())->postJson($url)->assertOk()->assertJson(['count' => 2]);
        $this->actingAs($this->buyer)->postJson($url)->assertOk()->assertExactJson(['favourited' => false, 'count' => 1]);

    }

    public function test_only_public_ads_can_be_saved_but_removing_always_works(): void
    {
        $this->buyer->favourites()->attach($this->listing->id, ['created_at' => now()]);
        $this->listing->forceFill(['status' => ListingStatus::Sold])->save();

        $this->actingAs($this->buyer)->postJson(route('listings.favourite', $this->listing))->assertOk()->assertJson(['favourited' => false]);
        $this->actingAs($this->buyer)->postJson(route('listings.favourite', $this->listing))->assertNotFound();
        $this->assertSame(0, $this->buyer->favourites()->count());
    }

    public function test_favourites_are_listed_newest_first_and_removed_with_the_account(): void
    {
        $older = Listing::factory()->create();
        $this->buyer->favourites()->attach($older->id, ['created_at' => now()->subDay()]);
        $this->buyer->favourites()->attach($this->listing->id, ['created_at' => now()]);

        $this->assertSame([$this->listing->id, $older->id], $this->buyer->favourites()->pluck('listings.id')->all());

        Notification::fake();
        $this->actingAs($this->buyer);
        app(DeleteAccount::class)->delete($this->buyer, ['password' => 'password']);
        $this->assertSame(0, $this->listing->favouritedBy()->count());
    }

    // ---- Reports ------------------------------------------------------------------------------

    public function test_report_a_listing_and_a_user(): void
    {
        $this->report(['details' => 'Asked for advance payment by bank transfer.'])
            ->assertCreated()->assertJson(['status' => 'open']);
        $this->report(['reportable_type' => 'user', 'reportable_id' => $this->listing->user_id, 'reason' => 'offensive'])->assertCreated();

        $report = Report::where('reportable_type', 'listing')->sole();
        $this->assertSame([$this->buyer->id, $this->listing->id], [$report->reporter_id, $report->reportable_id]);
        $this->assertTrue($report->target()->is($this->listing));
    }

    public function test_one_open_report_per_reporter_and_target(): void
    {
        $this->report([])->assertCreated();
        $this->report(['reason' => 'duplicate'])->assertStatus(409)->assertJson(['message' => __('reports.duplicate')]);
        $this->report([], User::factory()->create())->assertCreated(); // someone else may report too

        // After it is resolved, a new report is possible.
        Report::query()->update(['status' => 'dismissed']);
        $this->report([])->assertCreated();
    }

    public function test_report_validation(): void
    {
        $this->report(['reason' => 'boring'])->assertJsonValidationErrors('reason');
        $this->report(['reason' => 'other'])->assertJsonValidationErrors('details');
        $this->report(['details' => '<a href="x">click</a>'])->assertJsonValidationErrors('details');
        $this->report(['reportable_type' => 'App\\Models\\User'])->assertJsonValidationErrors('reportable_type');
        $this->report(['reportable_id' => 999999])->assertJsonValidationErrors('reportable_id');
        $this->report(['reportable_type' => 'user', 'reportable_id' => $this->buyer->id])->assertJsonValidationErrors('reportable_id'); // self

        $own = Listing::factory()->for($this->buyer)->create();
        $this->report(['reportable_id' => $own->id])->assertJsonValidationErrors('reportable_id');

        $draft = Listing::factory()->draft()->create();
        $this->report(['reportable_id' => $draft->id])->assertJsonValidationErrors('reportable_id');
        $this->assertSame(0, Report::count());
    }

    public function test_reporting_needs_a_verified_account_and_is_rate_limited(): void
    {
        $this->postJson(route('reports.store'), [])->assertUnauthorized();
        $this->report([], User::factory()->unverified()->create())->assertForbidden();

        foreach (range(1, 10) as $i) {
            $this->report(['reportable_id' => Listing::factory()->create()->id]);
        }
        $this->report(['reportable_id' => Listing::factory()->create()->id])->assertTooManyRequests();
    }

    // ---- Resolving --------------------------------------------------------------------------

    public function test_staff_resolve_reports_with_an_audit_entry(): void
    {
        $this->report([])->assertCreated();
        $report = Report::sole();
        $moderator = $this->staff(StaffRole::Moderator);

        $resolved = app(ResolveReport::class)->handle($moderator, $report, ReportStatus::Actioned, 'Ad suspended.');

        $this->assertSame(ReportStatus::Actioned, $resolved->status);
        $this->assertSame($moderator->id, $resolved->handled_by);
        $this->assertSame(['report.actioned', 'Ad suspended.'], [AuditLog::sole()->action, AuditLog::sole()->reason]);

        $this->expectException(ValidationException::class);
        app(ResolveReport::class)->handle($this->staff(StaffRole::Admin), $report->fresh(), ReportStatus::Dismissed, null);
    }

    public function test_who_may_resolve(): void
    {
        $this->report(['reportable_type' => 'user', 'reportable_id' => $this->listing->user_id, 'reason' => 'offensive'])->assertCreated();
        $report = Report::sole();

        foreach ([User::factory()->create(), $this->listing->user] as $actor) {
            if ($actor->is($this->listing->user)) {
                $actor->assignRole(StaffRole::Moderator->value); // staff never resolve reports about themselves
            }
            try {
                app(ResolveReport::class)->handle($actor->fresh(), $report, ReportStatus::Dismissed, null);
                $this->fail('Report resolved by '.$actor->id);
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(ReportStatus::Open, $report->fresh()->status);
    }
}
