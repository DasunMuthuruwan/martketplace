<?php

namespace Tests\Feature\Listings;

use App\Actions\Listings\ManageListingImages;
use App\Enums\ListingStatus;
use App\Models\Country;
use App\Models\User;
use App\Support\ListingsDisk;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsListings;
use Tests\TestCase;

/** F4.1 part 3: photos, contact and review, submit, owner's view (layout doc §3.4, components §5.2). */
class PostingPhotosSubmitTest extends TestCase
{
    use BuildsListings, RefreshDatabase;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Country::where('iso2', 'LK')->update(['is_active' => true]);
        Storage::fake(ListingsDisk::NAME);
        Notification::fake();
        config(['marketplace.moderation_mode' => 'all']);
        $this->seller = $this->sellerWithPhone();
    }

    public function test_reveal_route_keeps_its_public_address(): void
    {
        // The wizard's contact-options route must not take over this name (F4.1 part 3 regression).
        $this->assertSame(url('/listings/5/contact'), route('listings.contact', 5));
    }

    public function test_photos_step_shows_the_count_and_upload_form(): void
    {
        $listing = $this->completeDraft($this->seller);

        $html = $this->actingAs($this->seller)->get(route('listings.edit', [$listing, 'photos']))->assertOk()->getContent();

        $this->assertStringContainsString('1 / '.$listing->category->max_images, $html);
        $this->assertStringContainsString('name="photos[]"', $html);
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        $this->assertStringContainsString(__('posting.photos.cover'), $html);
        $this->assertStringContainsString(__('posting.photos.remove', ['n' => 1]), $html);
    }

    public function test_photos_step_gives_the_upload_script_its_limits_and_messages(): void
    {
        $listing = $this->completeDraft($this->seller);

        $html = $this->actingAs($this->seller)->get(route('listings.edit', [$listing, 'photos']))->assertOk()->getContent();

        $mb = intdiv((int) config('marketplace.images.max_upload_kb'), 1024);
        $this->assertStringContainsString('data-max-bytes="'.($mb * 1024 * 1024).'"', $html);
        $this->assertStringContainsString(e(__('posting.photos.too_large', ['mb' => $mb])), $html);
        $this->assertStringContainsString(e(__('posting.photos.progress')), $html);
        $this->assertStringContainsString('data-photo-client-errors', $html);
    }

    public function test_upload_several_photos_with_an_error_per_bad_file(): void
    {
        $listing = $this->completeDraft($this->seller);
        $tiny = UploadedFile::fake()->image('tiny.jpg', 50, 50);

        $this->actingAs($this->seller)->post(route('listings.photos.store', $listing), ['photos' => [$this->photo(), $tiny]])
            ->assertRedirect(route('listings.edit', [$listing, 'photos']))
            ->assertSessionHas('status', trans_choice('posting.photos.added', 1, ['count' => 1]));

        $this->assertSame(2, $listing->images()->count());
        $this->assertStringStartsWith('tiny.jpg: ', session('photo_errors')[0]);
    }

    public function test_json_upload_for_the_progress_bar_flashes_the_outcome(): void
    {
        $listing = $this->completeDraft($this->seller);

        $this->actingAs($this->seller)->postJson(route('listings.photos.store', $listing), ['photos' => [$this->photo()]])
            ->assertOk()->assertJsonPath('added', 1);
        $this->assertSame(trans_choice('posting.photos.added', 1, ['count' => 1]), session('status'));
    }

    public function test_move_set_cover_and_remove(): void
    {
        $listing = $this->completeDraft($this->seller);
        app(ManageListingImages::class)->add($this->seller, $listing, $this->photo());
        [$first, $second] = $listing->images()->orderBy('sort_order')->orderBy('id')->pluck('id')->all();

        $this->actingAs($this->seller)->post(route('listings.photos.move', [$listing, $second]), ['direction' => 'earlier'])->assertRedirect();
        $this->assertSame([$second, $first], $listing->images()->orderBy('sort_order')->pluck('id')->all());

        $this->post(route('listings.photos.cover', [$listing, $second]))->assertRedirect();
        $this->assertSame($second, $listing->fresh()->cover_image_id);

        $this->delete(route('listings.photos.destroy', [$listing, $first]))->assertRedirect();
        $this->assertSame([$second], $listing->images()->pluck('id')->all());
    }

    public function test_photos_of_someone_elses_ad_cannot_be_touched(): void
    {
        $listing = $this->completeDraft($this->seller);
        $image = $listing->images()->value('id');

        $this->actingAs(User::factory()->create())->delete(route('listings.photos.destroy', [$listing, $image]))->assertForbidden();
        $this->assertSame(1, $listing->images()->count());
    }

    public function test_review_shows_contact_choices_preview_and_summary(): void
    {
        $listing = $this->completeDraft($this->seller);

        $html = $this->actingAs($this->seller)->get(route('listings.edit', [$listing, 'review']))->assertOk()->getContent();

        $this->assertStringContainsString('action="'.route('listings.contact-options', $listing).'"', $html);
        $this->assertStringContainsString('name="show_whatsapp"', $html);
        $this->assertStringContainsString('iPhone 15 in excellent condition', $html);
        $this->assertStringContainsString('action="'.route('listings.submit', $listing).'"', $html);
        $this->assertStringContainsString(route('listings.edit', [$listing, 'price']), $html);
    }

    public function test_contact_choices_are_saved(): void
    {
        $listing = $this->completeDraft($this->seller);

        $this->actingAs($this->seller)->post(route('listings.contact-options', $listing), ['show_call' => '1'])
            ->assertRedirect(route('listings.edit', [$listing, 'review']));

        $this->assertSame([true, false], [$listing->fresh()->show_call, $listing->fresh()->show_whatsapp]);
    }

    public function test_submit_sends_the_draft_for_review_and_confirms(): void
    {
        $listing = $this->completeDraft($this->seller);

        $this->actingAs($this->seller)->post(route('listings.submit', $listing))->assertRedirect(route('listings.submitted', $listing));
        $this->assertSame(ListingStatus::PendingReview, $listing->fresh()->status);

        $this->get(route('listings.submitted', $listing))->assertOk()->assertSee(__('posting.submitted.review_title'));
    }

    public function test_incomplete_drafts_go_to_the_missing_step_with_the_reason(): void
    {
        $listing = $this->completeDraft($this->seller);
        $listing->images()->get()->each->delete();

        $html = $this->actingAs($this->seller)->followingRedirects()->post(route('listings.submit', $listing))->assertOk()->getContent();

        // No photos: the photos step is the first unfinished one, so the seller lands there with the reason.
        $this->assertStringContainsString(__('posting.photos.title'), $html);
        $this->assertStringContainsString(__('posting.review.not_ready'), $html);
        $this->assertStringContainsString(e(__('moderation.incomplete.photos')), $html);
        $this->assertSame(ListingStatus::Draft, $listing->fresh()->status);
    }

    public function test_live_ads_have_no_submit_button(): void
    {
        $listing = $this->completeDraft($this->seller);
        $listing->forceFill(['status' => ListingStatus::Active, 'published_at' => now(), 'expires_at' => now()->addDays(30)])->save();

        $html = $this->actingAs($this->seller)->get(route('listings.edit', [$listing, 'review']))->getContent();

        $this->assertStringNotContainsString(route('listings.submit', $listing), $html);
        $this->assertStringContainsString(__('posting.review.view_ad'), $html);
    }

    public function test_owner_sees_their_pending_ad_with_a_banner_and_others_get_404(): void
    {
        $listing = $this->completeDraft($this->seller);
        $this->actingAs($this->seller)->post(route('listings.submit', $listing));

        $html = $this->get($listing->fresh()->publicUrl())->assertOk()->getContent();
        $this->assertStringContainsString(__('posting.banner.pending'), $html);
        $this->assertStringContainsString('noindex', $html);

        $this->actingAs(User::factory()->create())->get($listing->fresh()->publicUrl())->assertNotFound();
        auth()->logout();
        $this->get($listing->fresh()->publicUrl())->assertNotFound();
    }
}
