<?php

namespace Tests\Feature\Public;

use App\Enums\ListingStatus;
use App\Enums\PriceType;
use App\Enums\RentPeriod;
use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\Page;
use App\Services\Listings\ListingPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Tests\TestCase;

/** F2.1: country chooser at `/`, country home, listing card and category tiles (layout doc §3.1). */
class HomePagesTest extends TestCase
{
    use RefreshDatabase;

    private Country $country;

    private Category $root;

    private Category $leaf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->country = Country::factory()->create(['name' => ['en' => 'Testland']]);
        $this->root = Category::factory()->create(['name' => ['en' => 'Vehicles'], 'slug' => 'vehicles', 'icon' => 'car']);
        $this->leaf = Category::factory()->create(['parent_id' => $this->root->id, 'name' => ['en' => 'Cars'], 'slug' => 'cars']);
    }

    private function listing(array $attributes = []): Listing
    {
        return Listing::factory()->inCountry($this->country)->create(['category_id' => $this->leaf->id] + $attributes);
    }

    private function photo(Listing $listing): void
    {
        $image = new ListingImage;
        $image->forceFill([
            'listing_id' => $listing->id, 'user_id' => $listing->user_id, 'disk' => 'listings', 'directory' => 'listings/'.$listing->id,
            'basename' => Str::lower(Str::random(32)), 'format' => 'webp', 'width' => 800, 'height' => 600, 'bytes' => 1, 'sha256' => str_repeat('a', 64),
        ])->save();
    }

    public function test_chooser_lists_active_countries_as_buttons_with_flags(): void
    {
        Country::factory()->create(['name' => ['en' => 'Closedland'], 'is_active' => false]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('name="country" value="'.strtolower($this->country->iso2).'"', $html);
        $this->assertStringContainsString('fi-'.strtolower($this->country->iso2), $html);
        $this->assertStringNotContainsString('Closedland', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_chooser_shows_latest_ads_from_every_country_with_the_country_named(): void
    {
        $this->listing(['title' => 'Global sample ad']);

        $this->get('/')->assertSee('Global sample ad')->assertSee('Testland');
    }

    public function test_country_home_has_hero_tiles_with_counts_latest_grid_and_safety(): void
    {
        $this->listing(['title' => 'First car']);
        $this->listing(['title' => 'Second car']);
        Page::create(['slug' => 'safety', 'title' => ['en' => 'Safety tips'], 'body' => ['en' => 'x'], 'is_published' => true]);

        $html = $this->get('/'.strtolower($this->country->iso2))->assertOk()->getContent();

        $this->assertStringContainsString(__('public.home.hero', ['country' => 'Testland']), $html);
        $this->assertStringContainsString('href="'.route('ads.category', ['cc' => strtolower($this->country->iso2), 'category' => 'vehicles']).'"', $html);
        $this->assertStringContainsString('2 ads', $html);
        $this->assertStringContainsString('listing-grid', $html);
        $this->assertStringContainsString('First car', $html);
        $this->assertStringContainsString(route('page.show', 'safety'), $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_empty_country_home_explains_and_has_no_see_all(): void
    {
        $html = $this->get('/'.strtolower($this->country->iso2))->assertOk()->getContent();

        $this->assertStringContainsString(__('public.home.empty_title'), $html);
        $this->assertStringNotContainsString(__('public.home.see_all'), $html);
    }

    public function test_card_shows_full_price_suffix_photo_count_place_time_and_one_link(): void
    {
        $listing = $this->listing(['title' => 'Family car', 'price' => '7850000', 'price_type' => PriceType::Negotiable]);
        $this->photo($listing);
        $this->photo($listing);
        $listing = Listing::withCount('images')->with(['country', 'currencyModel', 'category.parent'])->find($listing->id);

        $html = Blade::render('<x-listing.card :listing="$l" :present="$p" />', ['l' => $listing, 'p' => app(ListingPresenter::class)]);

        $parts = app(ListingPresenter::class)->priceParts($listing);
        $this->assertStringContainsString('7,850,000', $parts['amount']);  // full figure, never 7.85M
        $this->assertStringContainsString(e($parts['amount']), $html);
        $this->assertStringContainsString(__('public.price_types.negotiable'), $html);
        $this->assertMatchesRegularExpression('/2<span class="sr-only"> photos/', $html);
        $this->assertDoesNotMatchRegularExpression('/>\s*·\s*<time/', $html); // no stray separator without a place
        $this->assertSame(1, substr_count($html, '<a '));                     // the whole card is one link
        $this->assertStringContainsString('href="'.$listing->publicUrl().'"', $html);
        $this->assertStringContainsString('<time datetime=', $html);
    }

    public function test_price_parts_keep_the_old_combined_text(): void
    {
        $present = app(ListingPresenter::class);
        $rent = $this->listing(['price' => '85000', 'price_type' => PriceType::Negotiable, 'rent_period' => RentPeriod::cases()[0]]);
        $parts = $present->priceParts($rent);

        $this->assertSame($parts['amount'].' '.$parts['suffix'], $present->price($rent));
        $this->assertStringContainsString(__('public.price_types.negotiable'), $parts['suffix']);
    }

    public function test_card_without_photo_uses_the_category_icon_and_sold_shows_a_badge(): void
    {
        $listing = Listing::with(['country', 'currencyModel', 'category.parent'])->find($this->listing(['status' => ListingStatus::Sold, 'sold_at' => now()])->id);

        $html = Blade::render('<x-listing.card :listing="$l" :present="$p" />', ['l' => $listing, 'p' => app(ListingPresenter::class)]);

        $this->assertStringContainsString('<path d="M19 17h2c.6', $html); // the category's car icon as the placeholder
        $this->assertStringContainsString(__('public.card.sold'), $html);
    }
}
