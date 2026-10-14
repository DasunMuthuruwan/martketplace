<?php

namespace Tests\Feature\Public;

use App\Enums\ContactVisibility;
use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** F2.3: listing page, sold / gone pages and seller page (layout doc §3.3, components §5.2). */
class ListingPageTest extends TestCase
{
    use RefreshDatabase;

    private Country $country;

    private Category $cars;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->country = Country::factory()->create(['name' => ['en' => 'Testland']]);
        $vehicles = Category::factory()->create(['name' => ['en' => 'Vehicles'], 'slug' => 'vehicles', 'icon' => 'car']);
        $this->cars = Category::factory()->create(['parent_id' => $vehicles->id, 'name' => ['en' => 'Cars'], 'slug' => 'cars']);
        $this->seller = User::factory()->create(['name' => 'Kamal Seller', 'phone' => '+94771234567', 'phone_country' => 'LK']);
        $this->seller->forceFill(['contact_visibility' => ContactVisibility::Public])->save();
    }

    private function ad(array $attributes = []): Listing
    {
        return Listing::factory()->inCountry($this->country)->for($this->seller)->create($attributes + ['category_id' => $this->cars->id, 'title' => 'Family hatchback']);
    }

    public function test_page_structure_one_h1_breadcrumbs_panel_details_and_safety(): void
    {
        $listing = $this->ad(['description' => "Line one\nLine two"]);

        $html = $this->get($listing->publicUrl())->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('Family hatchback', $html);
        $this->assertStringContainsString(route('ads.category', ['cc' => strtolower($this->country->iso2), 'category' => 'vehicles', 'subcategory' => 'cars']), $html);
        $this->assertStringContainsString(__('listings.detail.id', ['id' => $listing->id]), $html);
        $this->assertStringContainsString('data-clamp', $html);
        $this->assertStringContainsString(__('listings.detail.safety_title'), $html);
        $this->assertStringContainsString('Kamal Seller', $html);
        $this->assertStringContainsString($this->seller->sellerUrl(), $html);
    }

    public function test_contact_buttons_never_put_the_number_in_the_html(): void
    {
        $listing = $this->ad();

        $html = $this->get($listing->publicUrl())->getContent();

        $this->assertStringContainsString('data-contact-channel="call"', $html);
        $this->assertStringContainsString('data-contact-channel="whatsapp"', $html);
        $this->assertStringContainsString('data-contact-url="'.route('listings.contact', $listing).'"', $html);
        $this->assertStringNotContainsString('771234567', $html);
        $this->assertStringNotContainsString('wa.me', $html);
    }

    public function test_phone_bar_replaces_the_bottom_navigation_on_the_listing_page(): void
    {
        $html = $this->get($this->ad()->publicUrl())->getContent();

        $this->assertStringNotContainsString('data-bottom-nav', $html);
        $this->assertStringContainsString(__('listings.detail.call'), $html);
        $this->assertStringContainsString('env(safe-area-inset-bottom)', $html);
    }

    public function test_members_only_contact_sends_guests_to_log_in(): void
    {
        $this->seller->forceFill(['contact_visibility' => ContactVisibility::Registered])->save();
        $listing = $this->ad();

        $guest = $this->get($listing->publicUrl())->getContent();
        $this->assertStringContainsString(__('public.login_to_contact'), $guest);
        $this->assertStringNotContainsString('data-contact-channel=', $guest);

        $member = $this->actingAs(User::factory()->create())->get($listing->publicUrl())->getContent();
        $this->assertStringContainsString('data-contact-channel="call"', $member);
    }

    public function test_save_share_and_report_for_members_and_guests(): void
    {
        $listing = $this->ad();

        $guest = $this->get($listing->publicUrl())->getContent();
        $this->assertStringNotContainsString('data-favourite-url', $guest);
        $this->assertStringNotContainsString('id="report-listing"', $guest);
        $this->assertStringContainsString('data-share-url="'.$listing->publicUrl().'"', $guest);

        $member = $this->actingAs(User::factory()->create())->get($listing->publicUrl())->getContent();
        $this->assertStringContainsString('data-favourite-url="'.route('listings.favourite', $listing).'"', $member);
        $this->assertStringContainsString('aria-pressed="false"', $member);
        $this->assertStringContainsString('<dialog id="report-listing"', $member);
        $this->assertStringContainsString('name="reportable_id" value="'.$listing->id.'"', $member);
        foreach (__('reports.reasons') as $value => $text) {
            $this->assertStringContainsString('value="'.$value.'"', $member);
        }
    }

    public function test_report_from_the_dialog_fields_is_accepted(): void
    {
        $listing = $this->ad();
        $reporter = User::factory()->create();

        $this->actingAs($reporter)->postJson(route('reports.store'), ['reportable_type' => 'listing', 'reportable_id' => $listing->id, 'reason' => 'scam'])
            ->assertCreated()->assertJsonPath('message', __('reports.received'));
    }

    public function test_similar_ads_use_the_card_grid(): void
    {
        $listing = $this->ad();
        $this->ad(['title' => 'Another hatchback']);

        $html = $this->get($listing->publicUrl())->getContent();

        $this->assertStringContainsString(__('public.similar'), $html);
        $this->assertStringContainsString('Another hatchback', $html);
        $this->assertStringContainsString('listing-grid', $html);
    }

    public function test_sold_and_gone_pages(): void
    {
        $sold = $this->ad(['status' => ListingStatus::Sold, 'sold_at' => now()->subDay()]);
        $this->get($sold->publicUrl())->assertOk()->assertSee(__('public.sold_title'))->assertSee('Family hatchback')->assertSee('noindex', false);

        $expired = $this->ad(['status' => ListingStatus::Expired, 'expires_at' => now()->subDay()]);
        $html = $this->get($expired->publicUrl())->assertStatus(410)->getContent();
        $this->assertStringContainsString(__('public.gone_title'), $html);
        $this->assertStringContainsString(__('public.browse_similar', ['category' => 'Cars', 'country' => 'Testland']), $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_seller_page_shows_profile_count_and_cards(): void
    {
        $this->ad(['title' => 'Seller car one']);
        $this->ad(['title' => 'Seller car two']);

        $html = $this->get($this->seller->sellerUrl())->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('Kamal Seller', $html);
        $this->assertStringContainsString('2 ads', $html);
        $this->assertStringContainsString('Seller car two', $html);
        $this->assertStringNotContainsString('771234567', $html);
    }
}
