<?php

namespace Tests\Feature\Listings;

use App\Actions\Listings\ModerateListing;
use App\Actions\Listings\SaveListingDetails;
use App\Actions\Listings\SaveListingLocation;
use App\Actions\Listings\SubmitListing;
use App\Enums\ContactVisibility;
use App\Enums\ListingStatus;
use App\Enums\StaffRole;
use App\Models\Attribute;
use App\Models\AuditLog;
use App\Models\BlockedTerm;
use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\ListingApproved;
use App\Notifications\ListingRejected;
use App\Support\ListingsDisk;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\Support\BuildsListings;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/**
 * Submission and moderation (T4.4a): LST-03/05/09/10, MSG-03, MOD-01/03/04/05, CD-06.
 */
class SubmitAndModerateTest extends TestCase
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
        config(['marketplace.moderation_mode' => 'all']);

        $this->seller = $this->sellerWithPhone();
        $this->moderator = $this->staff(StaffRole::Moderator);
    }

    private function ad(array $details = []): Listing
    {
        return $this->completeDraft($this->seller, $details);
    }

    private function submit(Listing $listing, array $input = [], ?User $seller = null): Listing
    {
        return app(SubmitListing::class)->handle($seller ?? $this->seller, $listing, $input);
    }

    // ---- Submission -------------------------------------------------------------------------

    public function test_a_complete_ad_goes_to_review_at_launch(): void
    {
        $listing = $this->submit($this->ad());

        $this->assertSame(ListingStatus::PendingReview, $listing->status);
        $this->assertNotNull($listing->submitted_at);
        $this->assertNull($listing->published_at);
        $this->assertFalse($listing->flagged);
        $this->assertFalse($listing->isPublic());
    }

    public function test_every_step_must_be_finished(): void
    {
        $listing = app(SaveListingLocation::class)->handle($this->seller, null, [
            'category_id' => Category::where('slug', 'mobile-phones')->value('id'),
            'country_id' => Country::where('iso2', 'LK')->value('id'),
        ]);

        $errors = $this->errors(fn () => $this->submit($listing));

        $this->assertEqualsCanonicalizing(['title', 'price_type', 'image'], array_keys($errors));
        $this->assertSame(ListingStatus::Draft, $listing->fresh()->status);
    }

    public function test_required_attributes_added_later_are_enforced(): void
    {
        $listing = $this->ad();
        Category::where('slug', 'mobile-phones')->firstOrFail()->attributeDefinitions()
            ->updateExistingPivot(Attribute::where('key', 'ram')->value('id'), ['is_required' => true]);

        $this->assertStringContainsString('RAM', $this->errors(fn () => $this->submit($listing))['attributes']);
    }

    public function test_a_visible_contact_channel_is_required(): void
    {
        $listing = $this->ad();

        $this->seller->forceFill(['contact_visibility' => ContactVisibility::Hidden])->save();
        $this->assertArrayHasKey('contact', $this->errors(fn () => $this->submit($listing->fresh())));

        $this->seller->forceFill(['contact_visibility' => ContactVisibility::Public])->save();
        $listing->forceFill(['show_call' => false, 'show_whatsapp' => false])->save();
        $this->assertArrayHasKey('contact', $this->errors(fn () => $this->submit($listing->fresh())));

        $listing->forceFill(['show_whatsapp' => true])->save();
        $this->assertSame(ListingStatus::PendingReview, $this->submit($listing->fresh())->status);
    }

    public function test_turnstile_is_required_when_configured(): void
    {
        config(['marketplace.turnstile.site_key' => 'site', 'marketplace.turnstile.secret_key' => 'secret']);
        Http::fake(fn (ClientRequest $request) => Http::response(['success' => ($request->data()['response'] ?? null) === 'good']));
        $listing = $this->ad();

        $this->assertArrayHasKey('cf-turnstile-response', $this->errors(fn () => $this->submit($listing)));
        $this->assertArrayHasKey('cf-turnstile-response', $this->errors(fn () => $this->submit($listing, ['cf-turnstile-response' => 'bad'])));

        $this->assertSame(ListingStatus::PendingReview, $this->submit($listing, ['cf-turnstile-response' => 'good'])->status);
    }

    // ---- Moderation modes -------------------------------------------------------------------

    public function test_without_moderation_an_ad_goes_live_at_once(): void
    {
        config(['marketplace.moderation_mode' => 'none', 'marketplace.listings.expiry_days' => 60]);

        $listing = $this->submit($this->ad());

        $this->assertSame(ListingStatus::Active, $listing->status);
        $this->assertTrue($listing->isPublic());
        $this->assertSame(now()->addDays(60)->toDateString(), $listing->expires_at->toDateString());
    }

    public function test_new_users_mode_reviews_until_a_first_ad_was_approved(): void
    {
        config(['marketplace.moderation_mode' => 'new_users']);

        $first = $this->submit($this->ad());
        $this->assertSame(ListingStatus::PendingReview, $first->status);

        app(ModerateListing::class)->approve($this->moderator, $first);
        $second = $this->submit($this->ad(['title' => 'Second phone for sale, like new']));

        $this->assertSame(ListingStatus::Active, $second->status);
    }

    // ---- Screening ---------------------------------------------------------------------------

    public function test_blocked_terms_refuse_and_flagged_terms_force_review(): void
    {
        config(['marketplace.moderation_mode' => 'none']);
        BlockedTerm::create(['term' => 'Counterfeit', 'match' => 'word', 'action' => 'block']);
        BlockedTerm::create(['term' => 'replica', 'match' => 'word', 'action' => 'flag', 'country_id' => Country::where('iso2', 'LK')->value('id')]);
        BlockedTerm::create(['term' => 'wire transfer only', 'match' => 'phrase', 'action' => 'flag', 'country_id' => Country::where('iso2', 'GB')->value('id')]);

        $blocked = $this->ad(['description' => 'Original box, not a COUNTERFEIT unit, honestly.']);
        $this->assertStringContainsString('counterfeit', $this->errors(fn () => $this->submit($blocked))['description']);
        $this->assertSame(ListingStatus::Draft, $blocked->fresh()->status);

        // Whole words only: "replicas" in another word does not match, the GB-only phrase does not apply in LK.
        $clean = $this->submit($this->ad(['description' => 'Not one of the replicastore fakes. Wire transfer only.']));
        $this->assertSame(ListingStatus::Active, $clean->status);

        $flagged = $this->submit($this->ad(['title' => 'Replica phone with box and charger']));
        $this->assertTrue($flagged->flagged);
        $this->assertSame(ListingStatus::PendingReview, $flagged->status, 'Flags force review even with moderation off');
    }

    public function test_contact_details_in_the_text_and_duplicates_are_flagged(): void
    {
        $withPhone = $this->submit($this->ad(['description' => 'Call me on 077 123 4567 any time after six pm.']));
        $this->assertTrue($withPhone->flagged);
        $withEmail = $this->submit($this->ad(['title' => 'Phone for sale, email seller', 'description' => 'Write to seller@example.com for more photos please.']));
        $this->assertTrue($withEmail->flagged);

        $original = $this->submit($this->ad(['title' => 'Unique title for the duplicate check']));
        $this->assertFalse($original->flagged);
        $copy = $this->submit($this->ad(['title' => '  unique TITLE for the duplicate   check ']));
        $this->assertTrue($copy->flagged);
    }

    public function test_active_listing_limit(): void
    {
        config(['marketplace.listings.max_active_per_user' => 1]);
        $this->submit($this->ad());

        $this->assertArrayHasKey('status', $this->errors(fn () => $this->submit($this->ad(['title' => 'Another phone for sale today']))));
    }

    // ---- Moderation decisions ---------------------------------------------------------------

    public function test_approval_publishes_audits_and_notifies(): void
    {
        $listing = $this->submit($this->ad());

        $approved = app(ModerateListing::class)->approve($this->moderator, $listing);

        $this->assertSame(ListingStatus::Active, $approved->status);
        $this->assertTrue($approved->isPublic());
        $this->assertSame($this->moderator->id, $approved->moderated_by);
        $log = AuditLog::sole();
        $this->assertSame(['listing.approved', $this->moderator->id, $listing->id], [$log->action, $log->actor_id, $log->subject_id]);
        $this->assertSame(['status' => ['pending_review', 'active']], $log->changes);
        $this->assertSame(64, strlen((string) $log->ip_hash));
        $this->assertNotSame(request()->ip(), $log->ip_hash);
        Notification::assertSentTo($this->seller, ListingApproved::class);
    }

    public function test_rejection_needs_a_reason_and_the_seller_can_fix_and_resubmit(): void
    {
        $listing = $this->submit($this->ad());

        $this->assertArrayHasKey('reason', $this->errors(fn () => app(ModerateListing::class)->reject($this->moderator, $listing, '  ')));
        $rejected = app(ModerateListing::class)->reject($this->moderator, $listing, 'Photos show a different phone.');

        $this->assertSame(ListingStatus::Rejected, $rejected->status);
        $this->assertSame('Photos show a different phone.', $rejected->moderation_note);
        Notification::assertSentTo($this->seller, ListingRejected::class, fn (ListingRejected $n) => ! $n->changesRequested);

        $fixed = app(SaveListingDetails::class)->handle($this->seller, $rejected, [
            'title' => 'iPhone 15 in excellent condition', 'description' => 'New photos of this exact phone added. Box included.',
            'type' => 'sale', 'condition' => 'used', 'attributes' => ['phone_brand' => 'apple', 'phone_model' => 'iphone-15'],
        ]);
        $resubmitted = $this->submit($fixed);
        $this->assertSame(ListingStatus::PendingReview, $resubmitted->status);
        $this->assertNull($resubmitted->moderation_note);
    }

    public function test_request_changes(): void
    {
        $listing = $this->submit($this->ad());

        $result = app(ModerateListing::class)->requestChanges($this->moderator, $listing, 'Add the storage size to the title.');

        $this->assertSame(ListingStatus::Rejected, $result->status);
        $this->assertSame('listing.changes_requested', AuditLog::sole()->action);
        Notification::assertSentTo($this->seller, ListingRejected::class, fn (ListingRejected $n) => $n->changesRequested);
    }

    public function test_who_may_moderate_and_what(): void
    {
        $listing = $this->submit($this->ad());
        $actions = app(ModerateListing::class);

        foreach ([User::factory()->create(), $this->staff(StaffRole::Moderator, attributes: ['id' => $this->seller->id + 1000])] as $index => $actor) {
            if ($index === 1) {
                $listing->forceFill(['user_id' => $actor->id])->save(); // a moderator's own ad
            }
            try {
                $actions->approve($actor, $listing->fresh());
                $this->fail('Moderation allowed');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        $listing->forceFill(['user_id' => $this->seller->id])->save();
        $actions->approve($this->moderator, $listing->fresh());
        $this->assertArrayHasKey('status', $this->errors(fn () => $actions->reject($this->staff(StaffRole::Admin), $listing->fresh(), 'Too late')), 'Already decided');
        $this->assertSame(1, AuditLog::count());
    }

    public function test_audit_entries_are_append_only(): void
    {
        $listing = $this->submit($this->ad());
        app(ModerateListing::class)->approve($this->moderator, $listing);
        $log = AuditLog::sole();

        foreach ([fn () => $log->update(['reason' => 'edited']), fn () => $log->delete()] as $change) {
            try {
                $change();
                $this->fail('Audit entry changed');
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertNull($log->fresh()->reason);
    }
}
