<?php

namespace Tests\Feature\Public;

use App\Enums\ContactVisibility;
use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Listing;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Public routes and pages (T5.2, routes doc §1, global scenario 10). */
class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    private Country $lk;

    private Category $cars;

    private Region $western;

    private Region $colombo;

    private City $nugegoda;

    private Listing $ad;

    protected function setUp(): void
    {
        parent::setUp();
        Currency::factory()->create(['code' => 'LKR', 'symbol_override' => 'Rs']);
        $this->lk = Country::factory()->create(['iso2' => 'LK', 'iso3' => 'LKA', 'name' => ['en' => 'Sri Lanka'], 'default_currency' => 'LKR', 'number_locale' => 'en_LK', 'region_levels' => 2]);
        Country::factory()->inactive()->create(['iso2' => 'DE', 'iso3' => 'DEU']);
        config(['marketplace.default_country' => 'LK', 'marketplace.seo.indexing' => false]);

        $vehicles = Category::factory()->create(['slug' => 'vehicles', 'name' => ['en' => 'Vehicles']]);
        $this->cars = Category::factory()->childOf($vehicles)->create(['slug' => 'cars', 'name' => ['en' => 'Cars']]);
        Category::factory()->childOf($vehicles)->create(['slug' => 'vans']);

        $this->western = Region::factory()->create(['country_id' => $this->lk->id, 'slug' => 'western']);
        $this->colombo = Region::factory()->childOf($this->western)->create(['slug' => 'colombo-district']);
        $this->nugegoda = City::factory()->inRegion($this->colombo)->create(['slug' => 'nugegoda', 'name' => ['en' => 'Nugegoda']]);

        $seller = User::factory()->create(['name' => 'Nimal Perera', 'phone' => '+94771234567', 'phone_country' => 'LK']);
        $seller->forceFill(['contact_visibility' => ContactVisibility::Public])->save();
        $this->ad = Listing::factory()->for($seller)->inCountry($this->lk)->inCategory($this->cars)->create([
            'title' => 'Toyota Aqua 2015 hybrid', 'price' => '6500000',
            'region_id' => $this->western->id, 'subregion_id' => $this->colombo->id, 'city_id' => $this->nugegoda->id,
        ]);
    }

    // ---- Home, countries, country home ------------------------------------------------------

    public function test_home_redirects_to_the_known_country_or_shows_the_chooser(): void
    {
        $this->get('/')->assertStatus(302)->assertRedirect(url('/lk'));

        config(['marketplace.default_country' => null]);
        $this->get('/')->assertOk()->assertSee('Sri Lanka')->assertSee(route('country.choose'), false)->assertSee('Toyota Aqua 2015 hybrid');
    }

    public function test_countries_page_lists_active_countries_with_ad_counts(): void
    {
        $this->get('/countries')->assertOk()->assertSee('Sri Lanka')->assertSee('1 ad');
    }

    public function test_country_home(): void
    {
        // Top-level category tiles with live-ad counts (subcategories are on the category pages, F2.2).
        $this->get('/lk')->assertOk()->assertSee('Vehicles')->assertSee('1 ad')->assertSee('Toyota Aqua 2015 hybrid')->assertSee('Rs');
        $this->get('/de')->assertNotFound();
        $this->get('/LK')->assertStatus(301)->assertRedirect(url('/lk'));
    }

    // ---- Category and location landing pages ------------------------------------------------

    public function test_category_pages_and_canonical_redirects(): void
    {
        $this->get('/lk/ads')->assertOk()->assertSee('Toyota Aqua 2015 hybrid');
        $this->get('/lk/ads/vehicles')->assertOk()->assertSee('Toyota Aqua 2015 hybrid');
        $this->get('/lk/ads/vehicles/cars')->assertOk()->assertSee('Toyota Aqua 2015 hybrid');
        $this->get('/lk/ads/vehicles/vans')->assertOk()->assertDontSee('Toyota Aqua 2015 hybrid');

        $this->get('/lk/ads/cars?q=aqua')->assertStatus(301)->assertRedirect(url('/lk/ads/vehicles/cars?q=aqua'));
        $this->cars->update(['slug' => 'motor-cars']);
        $this->get('/lk/ads/vehicles/cars')->assertStatus(301)->assertRedirect(url('/lk/ads/vehicles/motor-cars'));

        $this->get('/lk/ads/vans/motor-cars')->assertNotFound();
        $this->get('/lk/ads/no-such-category')->assertNotFound();
    }

    public function test_location_landing_pages(): void
    {
        $this->get('/lk/ads/in/western')->assertOk()->assertSee('Toyota Aqua 2015 hybrid');
        $this->get('/lk/ads/in/colombo-district/nugegoda')->assertOk()->assertSee('Toyota Aqua 2015 hybrid');
        $this->get('/lk/ads/vehicles/cars/in/western/nugegoda')->assertOk()->assertSee('Toyota Aqua 2015 hybrid');
        $this->get('/lk/ads/vehicles/in/western')->assertOk()->assertSee('Toyota Aqua 2015 hybrid');

        $southern = Region::factory()->create(['country_id' => $this->lk->id, 'slug' => 'southern']);
        $this->get('/lk/ads/in/southern')->assertOk()->assertDontSee('Toyota Aqua 2015 hybrid');
        $this->get('/lk/ads/in/southern/nugegoda')->assertNotFound();
        $this->get('/lk/ads/in/atlantis')->assertNotFound();
        $this->assertNotNull($southern);
    }

    public function test_query_filters_and_invalid_values_are_dropped(): void
    {
        $this->get('/lk/ads?region=western&min_price=6000000')->assertOk()->assertSee('Toyota Aqua 2015 hybrid');
        $this->get('/lk/ads?max_price=1000')->assertOk()->assertDontSee('Toyota Aqua 2015 hybrid');

        $this->get('/lk/ads?q=aqua&min_price=abc&type=auction')->assertStatus(302)->assertRedirect(url('/lk/ads?q=aqua'));
        $this->get('/lk/ads?page=500')->assertStatus(302)->assertRedirect(url('/lk/ads'));
    }

    public function test_global_search(): void
    {
        $this->get('/ads?q=aqua&country=lk')->assertStatus(301)->assertRedirect(url('/lk/ads?q=aqua'));
        $this->get('/ads?min_price=1')->assertOk()->assertSee(__('public.price_needs_currency'))->assertSee('Toyota Aqua 2015 hybrid');
        $this->get('/ads?currency=LKR&max_price=1000')->assertOk()->assertDontSee('Toyota Aqua 2015 hybrid');
        $this->get('/ads?category=vehicles')->assertOk()->assertSee('Toyota Aqua 2015 hybrid');
    }

    // ---- Listing page -----------------------------------------------------------------------

    public function test_listing_page_shows_the_ad_but_never_the_number(): void
    {
        $this->get($this->ad->publicUrl())
            ->assertOk()
            ->assertSee('Toyota Aqua 2015 hybrid')
            ->assertSee('Rs')->assertSee('6,500,000')
            ->assertSee('Nugegoda')
            ->assertSee('Nimal Perera')
            ->assertSee(route('listings.contact', $this->ad), false)
            ->assertDontSee('771234567')
            ->assertDontSee($this->ad->user->email);
    }

    public function test_outdated_slug_redirects_permanently(): void
    {
        $this->assertSame(url('/ad/toyota-aqua-2015-hybrid-'.$this->ad->id), $this->ad->publicUrl());

        $this->get('/ad/old-title-'.$this->ad->id)->assertStatus(301)->assertRedirect($this->ad->publicUrl());
        $this->get('/ad/-'.$this->ad->id)->assertStatus(301);
        $this->get('/ad/x-999999')->assertNotFound();
    }

    public function test_sold_expired_deleted_and_hidden_ads(): void
    {
        $url = '/ad/toyota-aqua-2015-hybrid-'.$this->ad->id;

        $this->ad->forceFill(['status' => ListingStatus::Sold, 'sold_at' => now()->subDays(5)])->save();
        $this->get($url)->assertOk()->assertSee(__('public.sold_title'))->assertSee('noindex', false);
        $this->ad->forceFill(['sold_at' => now()->subDays(31)])->save();
        $this->get($url)->assertStatus(410);

        $this->ad->forceFill(['status' => ListingStatus::Active, 'expires_at' => now()->subDay()])->save();
        $this->get($url)->assertStatus(410)->assertSee(__('public.gone_title'));

        foreach ([ListingStatus::Draft, ListingStatus::PendingReview, ListingStatus::Rejected, ListingStatus::Suspended] as $status) {
            $this->ad->forceFill(['status' => $status, 'expires_at' => now()->addDay()])->save();
            $this->get($url)->assertNotFound();
        }

        $this->ad->forceFill(['status' => ListingStatus::Active])->save();
        $this->ad->user->forceFill(['status' => 'banned'])->save();
        $this->get($url)->assertNotFound();

        $this->ad->delete();
        $this->get($url)->assertStatus(410);
    }

    // ---- Seller page ------------------------------------------------------------------------

    public function test_seller_page(): void
    {
        $seller = $this->ad->user;
        Listing::factory()->for($seller)->inCountry($this->lk)->inCategory($this->cars)->draft()->create(['title' => 'Unfinished draft ad']);

        $this->get($seller->sellerUrl())->assertOk()->assertSee('Nimal Perera')->assertSee('Toyota Aqua 2015 hybrid')
            ->assertDontSee('Unfinished draft ad')->assertDontSee($seller->email);
        $this->get('/seller/'.$seller->id.'-someone-else')->assertStatus(301)->assertRedirect($seller->sellerUrl());
        $this->get('/seller/'.$seller->id)->assertStatus(301);

        $seller->forceFill(['status' => 'banned'])->save();
        $this->get($seller->sellerUrl())->assertNotFound();
    }

    public function test_pages_are_not_indexed_until_seo_indexing_is_enabled(): void
    {
        $this->get('/lk/ads/vehicles')->assertSee('noindex,nofollow', false);

        config(['marketplace.seo.indexing' => true]);
        $this->get('/lk/ads/vehicles')->assertSee('index,follow', false)->assertDontSee('noindex', false);
        $this->get('/lk/ads/vehicles?q=aqua')->assertSee('noindex,follow', false);
    }
}
