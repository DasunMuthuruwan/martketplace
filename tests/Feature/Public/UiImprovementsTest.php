<?php

namespace Tests\Feature\Public;

use App\Models\Category;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Listing;
use App\Models\Region;
use App\Models\User;
use App\Services\Boosts\BoostManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** U2: featured ads on the home page, the hero's category and place selectors, more from the seller, card favourites. */
class UiImprovementsTest extends TestCase
{
    use RefreshDatabase;

    private Country $lk;

    private Country $gb;

    private Category $cars;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $lkr = Currency::factory()->create(['code' => 'LKR', 'minor_units' => 2]);
        $gbp = Currency::factory()->create(['code' => 'GBP', 'minor_units' => 2]);
        $this->lk = Country::factory()->create(['iso2' => 'LK', 'is_active' => true, 'default_currency' => $lkr->code, 'region_levels' => 1]);
        $this->gb = Country::factory()->create(['iso2' => 'GB', 'is_active' => true, 'default_currency' => $gbp->code]);
        $this->cars = Category::factory()->create(['parent_id' => Category::factory()->create(['slug' => 'vehicles'])->id, 'slug' => 'cars']);
        $this->seller = User::factory()->create();
    }

    /** @param array<string, mixed> $attributes */
    private function ad(Country $country, array $attributes = []): Listing
    {
        return Listing::factory()->inCountry($country)->for($attributes['user'] ?? $this->seller)
            ->create(array_diff_key($attributes, ['user' => 1]) + ['category_id' => $this->cars->id, 'expires_at' => now()->addDays(40)]);
    }

    public function test_featured_section_shows_running_top_ads_of_the_country_only(): void
    {
        $featured = $this->ad($this->lk, ['title' => 'Featured Aqua']);
        $plain = $this->ad($this->lk, ['title' => 'Plain Vitz']);
        $elsewhere = $this->ad($this->gb, ['title' => 'Featured Elsewhere']);
        $ended = $this->ad($this->lk, ['title' => 'Ended Prius']);
        app(BoostManager::class)->top($featured, 7);
        app(BoostManager::class)->top($elsewhere, 7);
        app(BoostManager::class)->end(app(BoostManager::class)->top($ended, 7));

        $home = $this->get('/lk')->assertOk();
        $featuredIds = $home->viewData('featured')->modelKeys();

        $this->assertSame([$featured->id], $featuredIds);
        $home->assertSee('data-featured', false)->assertSee(__('public.home.featured'));
        $this->assertNotContains($plain->id, $featuredIds);
    }

    public function test_featured_section_is_hidden_without_featured_ads_and_skips_ads_that_are_not_public(): void
    {
        $hidden = $this->ad($this->lk, ['title' => 'Expired top ad']);
        app(BoostManager::class)->top($hidden, 7);
        $hidden->forceFill(['expires_at' => now()->subDay()])->save();

        $this->get('/lk')->assertOk()->assertDontSee('data-featured', false);
    }

    public function test_hero_offers_the_countrys_categories_and_top_level_places(): void
    {
        Region::factory()->create(['country_id' => $this->lk->id, 'level' => 1, 'slug' => 'western', 'name' => 'Western', 'is_active' => true]);
        Region::factory()->create(['country_id' => $this->gb->id, 'level' => 1, 'slug' => 'england', 'name' => 'England', 'is_active' => true]);

        $home = $this->get('/lk')->assertOk();

        $this->assertSame(['western' => 'Western'], $home->viewData('regions'));
        $home->assertSee('name="category"', false)->assertSee('value="vehicles"', false)->assertSee('name="region"', false);
    }

    public function test_category_from_the_query_moves_into_the_path_and_keeps_other_values(): void
    {
        $this->get('/lk/ads?category=cars&q=aqua&region=western')
            ->assertRedirect(route('ads.category', ['cc' => 'lk', 'category' => 'vehicles', 'subcategory' => 'cars']).'?q=aqua&region=western');
        $this->get('/lk/ads?category=vehicles')->assertRedirect(route('ads.category', ['cc' => 'lk', 'category' => 'vehicles']));
        // Unknown or empty: the results page as before.
        $this->get('/lk/ads?category=nothing-here')->assertOk();
        $this->get('/lk/ads?category=')->assertOk();
    }

    public function test_more_from_seller_excludes_the_ad_other_countries_and_hidden_ads(): void
    {
        $ad = $this->ad($this->lk, ['title' => 'Main ad']);
        $other = $this->ad($this->lk, ['title' => 'Second ad']);
        $this->ad($this->gb, ['title' => 'Abroad ad']);
        $this->ad($this->lk, ['title' => 'Expired ad', 'expires_at' => now()->subDay()]);
        $this->ad($this->lk, ['title' => 'Someone else', 'user' => User::factory()->create()]);

        $page = $this->get($ad->publicUrl())->assertOk();

        $this->assertSame([$other->id], $page->viewData('sellerMore')->modelKeys());
        $page->assertSee('data-seller-more', false);
    }

    public function test_similar_ads_do_not_repeat_the_sellers_other_ads(): void
    {
        $ad = $this->ad($this->lk, ['title' => 'Main ad']);
        $mine = $this->ad($this->lk, ['title' => 'Same seller']);
        $theirs = $this->ad($this->lk, ['title' => 'Other seller', 'user' => User::factory()->create()]);

        $page = $this->get($ad->publicUrl())->assertOk();

        $this->assertSame([$mine->id], $page->viewData('sellerMore')->modelKeys());
        $this->assertSame([$theirs->id], $page->viewData('similar')->modelKeys());
    }

    public function test_more_from_seller_is_hidden_when_there_is_nothing_else(): void
    {
        $ad = $this->ad($this->lk);

        $this->get($ad->publicUrl())->assertOk()->assertDontSee('data-seller-more', false);
    }

    public function test_cards_show_the_signed_in_users_saved_state_and_send_guests_to_log_in(): void
    {
        $saved = $this->ad($this->lk, ['title' => 'Saved one']);
        $this->ad($this->lk, ['title' => 'Not saved']);
        $buyer = User::factory()->create();
        $buyer->favourites()->attach($saved->id);

        $html = $this->actingAs($buyer)->get('/lk')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'data-card-favourite aria-pressed="true"'));
        $this->assertSame(1, substr_count($html, 'data-card-favourite aria-pressed="false"'));
        $this->assertStringContainsString('data-favourite-url="'.route('listings.favourite', $saved).'"', $html);

        auth()->logout();
        $guest = $this->get('/lk')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-favourite-url', $guest);
        $this->assertStringContainsString('data-card-favourite href="'.route('login').'"', $guest);
    }
}
