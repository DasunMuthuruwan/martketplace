<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus;
use App\Enums\ReportStatus;
use App\Enums\StaffRole;
use App\Livewire\Admin\ModerationQueue;
use App\Livewire\Admin\RecentChanges;
use App\Livewire\Admin\ReportsQueue;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ListingApproved;
use App\Notifications\ListingRejected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/**
 * T6.1 admin screens: every action authorised, and refused when called directly without the
 * permission (implementation plan M6).
 */
class ModerationAdminTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Notification::fake();
        $this->moderator = $this->staff(StaffRole::Moderator, twoFactor: false);
    }

    private function pending(array $attributes = []): Listing
    {
        return Listing::factory()->status(ListingStatus::PendingReview)->create(['published_at' => null, 'submitted_at' => now()] + $attributes);
    }

    // ---- Access -----------------------------------------------------------------------------

    public function test_pages_need_the_right_permission(): void
    {
        $this->actingAs($this->moderator)->get(route('admin.moderation'))->assertOk()->assertSee(__('admin.nav.moderation'));
        $this->actingAs($this->moderator)->get(route('admin.changes'))->assertOk();
        $this->actingAs($this->moderator)->get(route('admin.reports'))->assertOk();

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('admin.moderation'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.reports'))->assertForbidden();

        // Owner/Admin without confirmed 2FA are sent to set it up first (T2.2).
        $this->actingAs($this->staff(StaffRole::Admin, twoFactor: false))->get(route('admin.moderation'))->assertRedirect(route('account.two-factor'));
    }

    public function test_components_refuse_users_without_permission_even_when_called_directly(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(ModerationQueue::class)->assertForbidden();
        Livewire::actingAs($user)->test(RecentChanges::class)->assertForbidden();
        Livewire::actingAs($user)->test(ReportsQueue::class)->assertForbidden();
    }

    // ---- Moderation queue -------------------------------------------------------------------

    public function test_queue_lists_pending_ads_flagged_first_then_oldest(): void
    {
        $older = $this->pending(['title' => 'Older pending ad here', 'submitted_at' => now()->subDay()]);
        $newer = $this->pending(['title' => 'Newer pending ad here']);
        $flagged = $this->pending(['title' => 'Flagged pending ad here', 'flagged' => true]);
        Listing::factory()->create(['title' => 'Already live ad here']);

        Livewire::actingAs($this->moderator)->test(ModerationQueue::class)
            ->assertSeeInOrder(['Flagged pending ad here', 'Older pending ad here', 'Newer pending ad here'])
            ->assertDontSee('Already live ad here')
            ->set('flaggedOnly', true)
            ->assertSee('Flagged pending ad here')
            ->assertDontSee('Older pending ad here');

        $this->assertNotNull([$older, $newer, $flagged]);
    }

    public function test_review_pane_shows_reasons_and_seller_summary(): void
    {
        $listing = $this->pending(['description' => 'Call me on 077 123 4567 for a quick sale today.', 'flagged' => true]);

        Livewire::actingAs($this->moderator)->test(ModerationQueue::class)
            ->call('select', $listing->id)
            ->assertSet('selectedId', $listing->id)
            ->assertSee(__('admin.moderation.reason_contact_in_text'))
            ->assertSee($listing->user->name)
            ->assertSee(__('admin.moderation.seller_stats', ['published' => 0, 'rejected' => 0, 'reports' => 0]));
    }

    public function test_approve_reject_and_request_changes(): void
    {
        $toApprove = $this->pending();
        $toReject = $this->pending();
        $toChange = $this->pending();

        Livewire::actingAs($this->moderator)->test(ModerationQueue::class)
            ->call('select', $toApprove->id)->call('approve')
            // The next ad waiting opens straight away (F5.1).
            ->assertSet('selectedId', $toReject->id)
            ->call('startDecision', 'reject')->assertSet('decision', 'reject')
            ->set('template', 'photos')->set('note', 'Please add your own photos.')->call('reject')
            ->assertSet('selectedId', $toChange->id)->assertSet('decision', null)
            ->call('startDecision', 'changes')->set('template', 'price')->call('requestChanges')
            ->assertSet('selectedId', null);

        $this->assertSame(ListingStatus::Active, $toApprove->fresh()->status);
        $this->assertSame(ListingStatus::Rejected, $toReject->fresh()->status);
        $this->assertSame(__('admin.moderation.templates.photos').' Please add your own photos.', $toReject->fresh()->moderation_note);
        $this->assertSame(__('admin.moderation.templates.price'), $toChange->fresh()->moderation_note);
        $this->assertSame(['listing.approved', 'listing.rejected', 'listing.changes_requested'], AuditLog::orderBy('id')->pluck('action')->all());
        Notification::assertSentTo($toApprove->user, ListingApproved::class);
        Notification::assertSentTo($toReject->user, ListingRejected::class);
    }

    public function test_reject_needs_a_reason_and_decisions_are_not_repeated(): void
    {
        $listing = $this->pending();
        $component = Livewire::actingAs($this->moderator)->test(ModerationQueue::class)->call('select', $listing->id);

        $component->call('reject')->assertHasErrors('reason');
        $this->assertSame(ListingStatus::PendingReview, $listing->fresh()->status);

        // Another moderator decides first: this one gets a message, not a second decision.
        $listing->forceFill(['status' => ListingStatus::Active])->save();
        $component->call('approve')->assertHasErrors('status');
        $this->assertSame(0, AuditLog::count());
    }

    public function test_only_pending_ads_can_be_selected_and_own_ads_are_refused(): void
    {
        Livewire::actingAs($this->moderator)->test(ModerationQueue::class)
            ->call('select', Listing::factory()->create()->id)
            ->assertNotFound();

        $own = $this->pending(['user_id' => $this->moderator->id]);
        Livewire::actingAs($this->moderator)->test(ModerationQueue::class)
            ->call('select', $own->id)->call('approve')
            ->assertForbidden();
        $this->assertSame(ListingStatus::PendingReview, $own->fresh()->status);
    }

    public function test_selected_id_cannot_be_set_from_the_browser(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->moderator)->test(ModerationQueue::class)->set('selectedId', $this->pending()->id);
    }

    // ---- Recent changes ---------------------------------------------------------------------

    public function test_recent_price_changes_feed(): void
    {
        $listing = Listing::factory()->create(['title' => 'Price changed ad here']);
        AuditLog::create(['actor_id' => $listing->user_id, 'action' => 'listing.price_changed', 'subject_type' => $listing->getMorphClass(), 'subject_id' => $listing->id,
            'changes' => ['before' => ['price' => '1000.000', 'price_type' => 'fixed'], 'after' => ['price' => '900.000', 'price_type' => 'fixed']]]);

        Livewire::actingAs($this->moderator)->test(RecentChanges::class)
            ->assertSee('Price changed ad here')->assertSee('1000.000')->assertSee('900.000');
    }

    // ---- Reports ----------------------------------------------------------------------------

    private function report(Listing $listing): Report
    {
        $report = new Report(['reason' => 'scam', 'details' => 'Asked for money in advance.']);
        $report->forceFill(['reporter_id' => User::factory()->create()->id, 'reportable_type' => 'listing', 'reportable_id' => $listing->id])->save();

        return $report;
    }

    public function test_reports_can_be_dismissed_or_actioned(): void
    {
        $first = $this->report(Listing::factory()->create());
        $second = $this->report(Listing::factory()->create());

        Livewire::actingAs($this->moderator)->test(ReportsQueue::class)
            ->assertSee(__('reports.reasons.scam'))
            ->call('select', $first->id)->assertSee('Asked for money in advance.')->call('dismiss')
            ->call('select', $second->id)->set('note', 'Warned the seller.')->call('action');

        $this->assertSame(ReportStatus::Dismissed, $first->fresh()->status);
        $this->assertSame(ReportStatus::Actioned, $second->fresh()->status);
        $this->assertSame('Warned the seller.', $second->fresh()->resolution_note);
    }

    public function test_take_the_ad_down_and_close_the_report_together(): void
    {
        $listing = Listing::factory()->create();
        $report = $this->report($listing);

        Livewire::actingAs($this->moderator)->test(ReportsQueue::class)
            ->call('select', $report->id)->set('note', 'Confirmed scam.')->call('suspendAndAction');

        $this->assertSame(ListingStatus::Suspended, $listing->fresh()->status);
        $this->assertSame(ReportStatus::Actioned, $report->fresh()->status);
        $this->assertEqualsCanonicalizing(['listing.suspended', 'report.actioned'], AuditLog::pluck('action')->all());
    }

    public function test_taking_an_ad_down_needs_a_reason_and_changes_nothing_without_one(): void
    {
        $listing = Listing::factory()->create();
        $report = $this->report($listing);

        Livewire::actingAs($this->moderator)->test(ReportsQueue::class)
            ->call('select', $report->id)->call('suspendAndAction')->assertHasErrors('reason');

        $this->assertSame(ListingStatus::Active, $listing->fresh()->status);
        $this->assertSame(ReportStatus::Open, $report->fresh()->status);
    }

    public function test_resolving_needs_reports_resolve(): void
    {
        // A user who may view reports but not resolve them is refused on the action itself.
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('access-admin', 'reports.view');
        $report = $this->report(Listing::factory()->create());

        Livewire::actingAs($viewer->fresh())->test(ReportsQueue::class)
            ->call('select', $report->id)->call('dismiss')->assertForbidden();
        $this->assertSame(ReportStatus::Open, $report->fresh()->status);
    }
}
