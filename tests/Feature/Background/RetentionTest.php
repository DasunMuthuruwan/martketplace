<?php

namespace Tests\Feature\Background;

use App\Enums\ListingStatus;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\StaffRole;
use App\Enums\UserStatus;
use App\Livewire\Admin\UsersAdmin;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\Report;
use App\Models\User;
use App\Notifications\InactiveAccountWarning;
use App\Services\Audit\AuditLogger;
use App\Services\Security\BannedIdentities;
use Illuminate\Auth\Events\Login;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** T7.2: retention (docs/architecture/09-data-retention.md §1–§3), with time travel per data type. */
class RetentionTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('listings');
        Notification::fake();
    }

    private function prune(): void
    {
        $this->artisan('marketplace:prune')->assertSuccessful();
    }

    /** A photo row with its files on the fake disk. */
    private function photo(Listing $listing): ListingImage
    {
        $image = new ListingImage;
        $image->forceFill([
            'listing_id' => $listing->id, 'user_id' => $listing->user_id, 'disk' => 'listings', 'directory' => 'listings/'.$listing->id,
            'basename' => Str::lower(Str::random(32)), 'format' => 'webp', 'width' => 800, 'height' => 600, 'bytes' => 1000, 'sha256' => str_repeat('a', 64),
        ])->save();
        foreach ($image->variantNames() as $variant) {
            Storage::disk('listings')->put($image->path($variant), 'x');
        }

        return $image;
    }

    private function report(array $attributes = []): Report
    {
        $report = new Report;
        $report->forceFill($attributes + [
            'reportable_type' => 'listing', 'reportable_id' => 1, 'reason' => ReportReason::cases()[0]->value, 'status' => ReportStatus::Open,
        ])->save();

        return $report;
    }

    private function assertPhotoGone(ListingImage $image): void
    {
        $this->assertModelMissing($image);
        foreach ($image->variantNames() as $variant) {
            Storage::disk('listings')->assertMissing($image->path($variant));
        }
    }

    public function test_daily_at_three_without_overlap(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn (Event $e) => str_contains((string) $e->command, 'marketplace:prune') && ! str_contains((string) $e->command, 'prune-images'));

        $this->assertNotNull($event);
        $this->assertSame('0 3 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    // ---- Listings ---------------------------------------------------------------------------

    public function test_deleted_listing_photos_after_30_days_and_row_after_180(): void
    {
        $listing = Listing::factory()->create();
        $photo = $this->photo($listing);
        $listing->delete();

        $this->travel(29)->days();
        $this->prune();
        $this->assertModelExists($photo);

        $this->travel(2)->days();
        $this->prune();
        $this->assertPhotoGone($photo);
        $this->assertNotNull(Listing::withTrashed()->find($listing->id));

        $this->travel(150)->days();
        $this->prune();
        $this->assertNull(Listing::withTrashed()->find($listing->id));
    }

    public function test_expired_listing_deleted_180_days_after_expiry(): void
    {
        $listing = Listing::factory()->status(ListingStatus::Expired)->create(['expires_at' => now()]);
        $photo = $this->photo($listing);

        $this->travel(179)->days();
        $this->prune();
        $this->assertModelExists($listing);

        $this->travel(2)->days();
        $this->prune();
        $this->assertNull(Listing::withTrashed()->find($listing->id));
        $this->assertPhotoGone($photo);
    }

    public function test_sold_listing_photos_after_90_days_row_after_180(): void
    {
        $listing = Listing::factory()->status(ListingStatus::Sold)->create(['sold_at' => now(), 'expires_at' => now()->addYear()]);
        $photo = $this->photo($listing);

        $this->travel(91)->days();
        $this->prune();
        $this->assertPhotoGone($photo);
        $this->assertModelExists($listing);

        $this->travel(90)->days();
        $this->prune();
        $this->assertNull(Listing::withTrashed()->find($listing->id));
    }

    public function test_rejected_after_90_days_and_stale_drafts_after_60(): void
    {
        $rejected = Listing::factory()->status(ListingStatus::Rejected)->create(['moderated_at' => now()]);
        $draft = Listing::factory()->draft()->create();
        $editedDraft = Listing::factory()->draft()->create();

        $this->travel(59)->days();
        $editedDraft->touch(); // still being worked on
        $this->prune();
        $this->assertModelExists($draft);

        $this->travel(2)->days();
        $this->prune();
        $this->assertNull(Listing::withTrashed()->find($draft->id));
        $this->assertModelExists($editedDraft);
        $this->assertModelExists($rejected);

        $this->travel(30)->days();
        $this->prune();
        $this->assertNull(Listing::withTrashed()->find($rejected->id));
    }

    public function test_active_listings_are_untouched(): void
    {
        $listing = Listing::factory()->create(['expires_at' => now()->addYears(2)]);
        $photo = $this->photo($listing);

        $this->travel(400)->days();
        $this->prune();

        $this->assertModelExists($listing);
        $this->assertModelExists($photo);
    }

    // ---- Accounts ---------------------------------------------------------------------------

    public function test_deleted_account_is_anonymised_after_30_days_with_listings_removed(): void
    {
        $user = User::factory()->create(['name' => 'Kamal', 'email' => 'kamal@example.com', 'phone' => '+15550000001']);
        $listing = Listing::factory()->for($user)->create();
        $photo = $this->photo($listing);
        $report = $this->report(['reporter_id' => $user->id]);
        $user->delete();

        $this->travel(29)->days();
        $this->prune();
        $this->assertSame('Kamal', User::withTrashed()->find($user->id)->name);

        $this->travel(2)->days();
        $this->prune();

        $user = User::withTrashed()->find($user->id);
        $this->assertNotNull($user->anonymised_at);
        $this->assertSame([__('account.deleted_user'), 'deleted+'.$user->id.'@invalid', null], [$user->name, $user->email, $user->phone]);
        $this->assertNull($user->two_factor_secret);
        $this->assertNull(Listing::withTrashed()->find($listing->id));
        $this->assertPhotoGone($photo);
        $this->assertNull($report->fresh()->reporter_id);
        $this->assertTrue(AuditLog::where('action', 'account.anonymised')->where('subject_id', $user->id)->exists());

        // Idempotent: a second run changes nothing.
        $this->prune();
        $this->assertSame(1, AuditLog::where('action', 'account.anonymised')->count());
    }

    public function test_banned_account_anonymised_after_30_days_but_ban_hashes_kept(): void
    {
        $user = User::factory()->create(['email' => 'spam@example.com']);
        $user->forceFill(['status' => UserStatus::Banned])->save(); // as ManageAccount::ban does
        app(BannedIdentities::class)->add($user);

        $this->travel(31)->days();
        $this->prune();

        $user = User::withTrashed()->find($user->id);
        $this->assertNotNull($user->anonymised_at);
        $this->assertTrue(app(BannedIdentities::class)->isBanned('spam@example.com'));
    }

    public function test_inactive_account_is_warned_then_deleted_unless_they_sign_in(): void
    {
        $idle = User::factory()->create(['last_login_at' => now()]);
        $returns = User::factory()->create(['last_login_at' => now()]);
        $this->seedRoles();
        $staff = $this->staff(StaffRole::Moderator, twoFactor: false);
        $staff->forceFill(['last_login_at' => now()])->save();

        $this->travel(1096)->days();
        $this->prune();
        Notification::assertSentTo([$idle, $returns], InactiveAccountWarning::class);
        Notification::assertNotSentTo($staff, InactiveAccountWarning::class);

        // One signs in within the 30 days, which cancels the deletion.
        $this->travel(10)->days();
        event(new Login('web', $returns, false));

        $this->travel(21)->days();
        $this->prune();
        $this->assertSoftDeleted($idle);
        $this->assertNotSoftDeleted($returns);
        $this->assertNull($returns->fresh()->inactive_warned_at);
        $this->assertTrue(AuditLog::where('action', 'account.deleted_inactive')->where('subject_id', $idle->id)->exists());

        // Then the normal deleted-account path anonymises it 30 days later.
        $this->travel(31)->days();
        $this->prune();
        $this->assertNotNull(User::withTrashed()->find($idle->id)->anonymised_at);
    }

    // ---- Legal hold -------------------------------------------------------------------------

    public function test_legal_hold_protects_the_account_and_its_listings(): void
    {
        $this->seedRoles();
        $owner = $this->staff(StaffRole::Owner);
        $admin = $this->staff(StaffRole::Admin);
        $user = User::factory()->create();
        $listing = Listing::factory()->for($user)->status(ListingStatus::Rejected)->create(['moderated_at' => now()]);
        $other = Listing::factory()->status(ListingStatus::Rejected)->create(['moderated_at' => now()]);
        session()->put('auth.password_confirmed_at', time());

        Livewire::actingAs($admin)->test(UsersAdmin::class)->call('select', $user->id)->call('toggleLegalHold')->assertForbidden();
        Livewire::actingAs($owner)->test(UsersAdmin::class)->call('select', $user->id)->call('toggleLegalHold')->assertHasErrors('reason');
        Livewire::actingAs($owner)->test(UsersAdmin::class)->call('select', $user->id)->set('reason', 'Police request 42')->call('toggleLegalHold')->assertHasNoErrors();
        $this->assertTrue($user->fresh()->legal_hold);
        $this->assertSame('Police request 42', AuditLog::where('action', 'legal_hold.set')->value('reason'));
        $user->delete();

        // Listing hold from the CLI.
        $this->artisan('marketplace:legal-hold', ['type' => 'listing', 'id' => $other->id, 'reason' => 'Fraud case'])->assertSuccessful();

        $this->travel(400)->days();
        $this->prune();
        $this->assertModelExists($listing);
        $this->assertModelExists($other);
        $this->assertNull(User::withTrashed()->find($user->id)->anonymised_at);

        $this->artisan('marketplace:legal-hold', ['type' => 'listing', 'id' => $other->id, 'reason' => 'Closed', '--lift' => true])->assertSuccessful();
        $this->prune();
        $this->assertNull(Listing::withTrashed()->find($other->id));
        $this->assertTrue(AuditLog::where('action', 'legal_hold.lifted')->exists());
    }

    // ---- Reports and audit log --------------------------------------------------------------

    public function test_closed_reports_and_audit_entries_after_two_years(): void
    {
        $closed = $this->report(['status' => ReportStatus::Dismissed, 'handled_at' => now()]);
        $open = $this->report();
        app(AuditLogger::class)->record(null, 'test.old');

        $this->travel(731)->days();
        app(AuditLogger::class)->record(null, 'test.recent');
        $this->prune();

        $this->assertModelMissing($closed);
        $this->assertModelExists($open);
        $this->assertSame(['test.recent'], AuditLog::pluck('action')->filter(fn ($a) => str_starts_with($a, 'test.'))->values()->all());
    }

    public function test_dry_run_changes_nothing(): void
    {
        $listing = Listing::factory()->draft()->create();
        $this->travel(61)->days();

        $this->artisan('marketplace:prune', ['--dry-run' => true])->expectsOutputToContain('would change')->assertSuccessful();

        $this->assertModelExists($listing);
    }
}
