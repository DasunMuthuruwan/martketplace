<?php

namespace Tests\Feature\Listings;

use App\Actions\Listings\ListingLifecycle;
use App\Actions\Listings\ManageListingImages;
use App\Actions\Listings\ModerateListing;
use App\Actions\Listings\SaveListingDetails;
use App\Actions\Listings\SaveListingLocation;
use App\Actions\Listings\SaveListingPrice;
use App\Actions\Listings\SubmitListing;
use App\Actions\Listings\SuspendListing;
use App\Actions\Listings\UpdateListingContact;
use App\Enums\ContactVisibility;
use App\Enums\ListingStatus;
use App\Enums\StaffRole;
use App\Exceptions\Listings\ListingNotEditable;
use App\Models\AuditLog;
use App\Models\BlockedTerm;
use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\ListingExpired;
use App\Notifications\ListingExpiringSoon;
use App\Notifications\ListingSuspended;
use App\Support\ListingsDisk;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsListings;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/**
 * Lifecycle after submission (T4.4b): LST-06/07/08, MSG-02/03, Q-MOD-1, architecture §4.2.
 */
class LifecycleTest extends TestCase
{
    use BuildsListings, CreatesStaff, RefreshDatabase;

    private User $seller;

    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Country::where('iso2', 'LK')->update(['is_active' => true]);
        Storage::fake(ListingsDisk::NAME);
        Notification::fake();
        config(['marketplace.moderation_mode' => 'all', 'marketplace.listings.expiry_days' => 60]);

        $this->seller = $this->sellerWithPhone();
        $this->moderator = $this->staff(StaffRole::Moderator);
    }

    /** A live ad (submitted and approved). */
    private function liveAd(array $details = []): Listing
    {
        $listing = app(SubmitListing::class)->handle($this->seller, $this->completeDraft($this->seller, $details));

        return app(ModerateListing::class)->approve($this->moderator, $listing);
    }

    // ---- Editing a live ad (LST-06) ---------------------------------------------------------

    public function test_a_material_edit_takes_a_live_ad_back_to_review(): void
    {
        $listing = $this->liveAd();

        $edited = app(SaveListingDetails::class)->handle($this->seller, $listing, ['title' => 'iPhone 15 128 GB, excellent condition'] + $this->phoneDetails());

        $this->assertSame(ListingStatus::PendingReview, $edited->status);
        $this->assertFalse($edited->isPublic());
        $this->assertSame('iPhone 15 128 GB, excellent condition', $edited->title);
        $this->assertNotNull($edited->published_at, 'Still known as previously published');
    }

    public function test_photos_and_location_are_material_reorder_and_cover_are_not(): void
    {
        $listing = $this->liveAd();
        $images = app(ManageListingImages::class);
        $second = $images->add($this->seller, $listing, $this->photo());
        $this->assertSame(ListingStatus::PendingReview, $listing->fresh()->status, 'New photo');

        $listing = app(ModerateListing::class)->approve($this->moderator, $listing->fresh());
        $images->setCover($this->seller, $listing, $second->id);
        $images->reorder($this->seller, $listing, [$second->id, $listing->images()->where('id', '!=', $second->id)->value('id')]);
        $this->assertSame(ListingStatus::Active, $listing->fresh()->status, 'Presentation only');

        app(SaveListingLocation::class)->handle($this->seller, $listing->fresh(), [
            'category_id' => $listing->category_id, 'country_id' => $listing->country_id, 'locality_text' => 'Near the bus stand',
        ]);
        $this->assertSame(ListingStatus::PendingReview, $listing->fresh()->status, 'Location');
    }

    public function test_without_moderation_edits_stay_live_unless_flagged(): void
    {
        config(['marketplace.moderation_mode' => 'none']);
        $listing = app(SubmitListing::class)->handle($this->seller, $this->completeDraft($this->seller));
        $this->assertSame(ListingStatus::Active, $listing->status);

        $listing = app(SaveListingDetails::class)->handle($this->seller, $listing, ['description' => 'Battery health 95%. Selling because I upgraded.'] + $this->phoneDetails());
        $this->assertSame(ListingStatus::Active, $listing->status);

        $listing = app(SaveListingDetails::class)->handle($this->seller, $listing, ['description' => 'Call 077 123 4567 for a quick deal, price is final.'] + $this->phoneDetails());
        $this->assertSame(ListingStatus::PendingReview, $listing->status);
        $this->assertTrue($listing->flagged);
    }

    public function test_blocked_terms_refuse_an_edit_and_nothing_changes(): void
    {
        $listing = $this->liveAd();
        BlockedTerm::create(['term' => 'counterfeit', 'action' => 'block']);

        $errors = $this->errors(fn () => app(SaveListingDetails::class)->handle($this->seller, $listing, ['description' => 'Counterfeit charger included in the box.'] + $this->phoneDetails()));

        $this->assertArrayHasKey('description', $errors);
        $fresh = $listing->fresh();
        $this->assertSame(ListingStatus::Active, $fresh->status);
        $this->assertSame($this->phoneDetails()['description'], $fresh->description, 'Rolled back');
    }

    public function test_country_and_category_are_fixed_after_submission(): void
    {
        $listing = $this->liveAd();

        $errors = $this->errors(fn () => app(SaveListingLocation::class)->handle($this->seller, $listing, [
            'category_id' => Category::where('slug', 'tablets')->value('id'), 'country_id' => $listing->country_id,
        ]));

        $this->assertArrayHasKey('category_id', $errors);
        $this->assertSame(ListingStatus::Active, $listing->fresh()->status);
    }

    public function test_price_changes_apply_at_once_and_are_recorded(): void
    {
        $listing = $this->liveAd();
        AuditLog::query()->toBase()->delete(); // keep only the price entry

        $updated = app(SaveListingPrice::class)->handle($this->seller, $listing, ['price_type' => 'fixed', 'price' => '175000']);

        $this->assertSame(ListingStatus::Active, $updated->status);
        $this->assertSame('175000.000', $updated->price);
        $log = AuditLog::sole();
        $this->assertSame(['listing.price_changed', $this->seller->id], [$log->action, $log->actor_id]);
        $this->assertSame(['price' => '185000.000', 'price_type' => 'negotiable', 'rent_period' => null], $log->changes['before']);
        $this->assertSame('175000.000', $log->changes['after']['price']);

        app(SaveListingPrice::class)->handle($this->seller, $updated, ['price_type' => 'fixed', 'price' => '175000']);
        $this->assertSame(1, AuditLog::count(), 'No entry when nothing changed');
    }

    public function test_a_live_ad_keeps_one_photo_and_one_contact_channel(): void
    {
        $listing = $this->liveAd();

        $this->assertArrayHasKey('image', $this->errors(fn () => app(ManageListingImages::class)->remove($this->seller, $listing, $listing->images()->value('id'))));

        // Registered visibility with a phone: turning both toggles off leaves no channel.
        $this->assertArrayHasKey('contact', $this->errors(fn () => app(UpdateListingContact::class)->handle($this->seller, $listing, ['show_call' => false, 'show_whatsapp' => false])));
        $updated = app(UpdateListingContact::class)->handle($this->seller, $listing, ['show_call' => false, 'show_whatsapp' => true]);
        $this->assertFalse($updated->show_call);
        $this->assertSame(ListingStatus::Active, $updated->status, 'Contact toggles are not material');

        // Drafts may switch both off; submit checks it.
        $draft = $this->completeDraft($this->seller, ['title' => 'Another phone for sale, unused']);
        $this->assertFalse(app(UpdateListingContact::class)->handle($this->seller, $draft, ['show_call' => false, 'show_whatsapp' => false])->show_whatsapp);
        $this->seller->forceFill(['contact_visibility' => ContactVisibility::Public])->save();
    }

    // ---- Sold, renew, delete ------------------------------------------------------------------

    public function test_mark_sold(): void
    {
        $listing = $this->liveAd();

        $sold = app(ListingLifecycle::class)->markSold($this->seller, $listing);

        $this->assertSame(ListingStatus::Sold, $sold->status);
        $this->assertNotNull($sold->sold_at);
        $this->assertFalse($sold->isPublic());
        $this->assertArrayHasKey('status', $this->errors(fn () => app(ListingLifecycle::class)->markSold($this->seller, $sold)));

        $this->expectException(ListingNotEditable::class);
        app(SaveListingPrice::class)->handle($this->seller, $sold, ['price_type' => 'fixed', 'price' => '1000']);
    }

    public function test_renew_window_and_expired_ads(): void
    {
        $lifecycle = app(ListingLifecycle::class);
        $listing = $this->liveAd();

        $this->assertStringContainsString('last 7 days', $this->errors(fn () => $lifecycle->renew($this->seller, $listing))['status']);

        $this->travel(55)->days();
        $renewed = $lifecycle->renew($this->seller, $listing->fresh());
        $this->assertSame(now()->addDays(60)->toDateString(), $renewed->expires_at->toDateString());
        $this->assertNotNull($renewed->renewed_at);

        // Expired (by date and by status) ads come back live without review within 90 days.
        $this->travel(61)->days();
        $this->artisan('marketplace:expire-listings')->assertSuccessful();
        $this->assertSame(ListingStatus::Expired, $renewed->fresh()->status);
        $back = $lifecycle->renew($this->seller, $renewed->fresh());
        $this->assertSame(ListingStatus::Active, $back->status);
        $this->assertTrue($back->isPublic());

        $this->travel(61 + 91)->days();
        $this->assertArrayHasKey('status', $this->errors(fn () => $lifecycle->renew($this->seller, $back->fresh())), 'Too long after expiry');
    }

    public function test_renewing_an_expired_ad_respects_the_live_limit(): void
    {
        config(['marketplace.listings.max_active_per_user' => 1]);
        $old = $this->liveAd();
        $old->forceFill(['status' => ListingStatus::Expired, 'expires_at' => now()->subDay()])->save();
        $this->liveAd(['title' => 'Second phone, still in its box']);

        $this->assertArrayHasKey('status', $this->errors(fn () => app(ListingLifecycle::class)->renew($this->seller, $old->fresh())));
    }

    public function test_delete_is_a_soft_delete_by_the_owner_only(): void
    {
        $listing = $this->liveAd();

        try {
            app(ListingLifecycle::class)->delete(User::factory()->create(), $listing);
            $this->fail('Deleted by someone else');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        app(ListingLifecycle::class)->delete($this->seller, $listing);
        $this->assertSoftDeleted($listing);
        $this->assertSame(0, Listing::public()->count());
    }

    // ---- Expiry job (LST-08) ------------------------------------------------------------------

    public function test_expiry_reminders_and_status_sync_are_sent_once(): void
    {
        $listing = $this->liveAd();

        $this->travel(58)->days(); // 2 days left: inside the 3-day reminder window
        $this->artisan('marketplace:expire-listings')->expectsOutputToContain('Reminders sent: 1. Listings expired: 0.')->assertSuccessful();
        $this->artisan('marketplace:expire-listings')->expectsOutputToContain('Reminders sent: 0.')->assertSuccessful();
        Notification::assertSentToTimes($this->seller, ListingExpiringSoon::class, 1);

        $this->travel(3)->days();
        $this->assertFalse($listing->fresh()->isPublic(), 'Hidden by time even before the job runs');
        $this->artisan('marketplace:expire-listings')->expectsOutputToContain('Listings expired: 1.')->assertSuccessful();
        $this->assertSame(ListingStatus::Expired, $listing->fresh()->status);
        Notification::assertSentToTimes($this->seller, ListingExpired::class, 1);
    }

    // ---- Staff suspension -------------------------------------------------------------------

    public function test_suspend_and_reinstate(): void
    {
        $listing = $this->liveAd();
        $admin = $this->staff(StaffRole::Admin);
        AuditLog::query()->toBase()->delete();

        $suspended = app(SuspendListing::class)->suspend($admin, $listing, 'Reported as stolen, under review.');
        $this->assertSame(ListingStatus::Suspended, $suspended->status);
        $this->assertFalse($suspended->isPublic());
        Notification::assertSentTo($this->seller, ListingSuspended::class);

        $this->expectExceptionOnEdit($suspended);

        $back = app(SuspendListing::class)->reinstate($admin, $suspended);
        $this->assertSame(ListingStatus::Active, $back->status);
        $this->assertTrue($back->isPublic());
        $this->assertSame(['listing.suspended', 'listing.reinstated'], AuditLog::orderBy('id')->pluck('action')->all());
    }

    public function test_a_never_approved_ad_goes_back_to_review_when_reinstated(): void
    {
        $pending = app(SubmitListing::class)->handle($this->seller, $this->completeDraft($this->seller));
        $admin = $this->staff(StaffRole::Admin);

        $suspended = app(SuspendListing::class)->suspend($admin, $pending, 'Checking ownership documents.');

        $this->assertSame(ListingStatus::PendingReview, app(SuspendListing::class)->reinstate($admin, $suspended)->status);
    }

    public function test_who_may_suspend(): void
    {
        $listing = $this->liveAd();

        // Moderators have listings.suspend (authorization doc §3); regular users do not.
        $this->assertSame(ListingStatus::Suspended, app(SuspendListing::class)->suspend($this->moderator, $listing, 'Spam')->status);

        $this->expectException(AuthorizationException::class);
        app(SuspendListing::class)->reinstate(User::factory()->create(), $listing->fresh());
    }

    private function expectExceptionOnEdit(Listing $listing): void
    {
        try {
            app(SaveListingDetails::class)->handle($this->seller, $listing, $this->phoneDetails());
            $this->fail('A suspended ad was edited');
        } catch (ListingNotEditable) {
            $this->addToAssertionCount(1);
        }
    }
}
