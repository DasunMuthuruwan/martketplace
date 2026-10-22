<?php

namespace Tests\Feature\Insights;

use App\Enums\ListingStatus;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use App\Services\Insights\Trends;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** G2 (ADR-027): popular ads, trending searches and models, most marked as sold, per country. */
class TrendsTest extends TestCase
{
    use RefreshDatabase;

    private Country $lk;

    private Category $cars;

    private Category $vans;

    private int $modelAttribute;

    private AttributeOption $aqua;

    private AttributeOption $fit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lk = Country::factory()->create(['iso2' => 'LK', 'is_active' => true]);
        $vehicles = Category::factory()->create(['slug' => 'vehicles', 'name' => ['en' => 'Vehicles']]);
        $this->cars = Category::factory()->create(['parent_id' => $vehicles->id, 'slug' => 'cars', 'name' => ['en' => 'Cars']]);
        $this->vans = Category::factory()->create(['parent_id' => $vehicles->id, 'slug' => 'vans', 'name' => ['en' => 'Vans']]);
        $make = Attribute::factory()->select([])->create(['key' => 'make']);
        $model = Attribute::factory()->select([])->create(['key' => 'model', 'depends_on_attribute_id' => $make->id]);
        $this->modelAttribute = $model->id;
        $toyota = AttributeOption::factory()->create(['attribute_id' => $make->id, 'label' => ['en' => 'Toyota']]);
        $honda = AttributeOption::factory()->create(['attribute_id' => $make->id, 'label' => ['en' => 'Honda']]);
        $this->aqua = AttributeOption::factory()->create(['attribute_id' => $model->id, 'parent_option_id' => $toyota->id, 'label' => ['en' => 'Aqua']]);
        $this->fit = AttributeOption::factory()->create(['attribute_id' => $model->id, 'parent_option_id' => $honda->id, 'label' => ['en' => 'Fit']]);
    }

    private function ad(string $title, ?AttributeOption $model = null, array $attributes = []): Listing
    {
        $ad = Listing::factory()->inCountry($this->lk)->create($attributes + ['category_id' => $this->cars->id, 'title' => $title]);
        if ($model !== null) {
            DB::table('listing_attribute_values')->insert(['listing_id' => $ad->id, 'attribute_id' => $this->modelAttribute, 'value_option_id' => $model->id]);
        }

        return $ad;
    }

    /** @param  array<int, int>  $perDayAgo  days ago => views */
    private function views(Listing $ad, array $perDayAgo): void
    {
        foreach ($perDayAgo as $daysAgo => $views) {
            DB::table('listing_daily_views')->insert(['listing_id' => $ad->id, 'date' => now()->subDays($daysAgo)->toDateString(), 'views' => $views]);
        }
    }

    public function test_popular_ads_are_the_most_viewed_live_ads_this_week(): void
    {
        $a = $this->ad('Most viewed');
        $b = $this->ad('Second');
        $c = $this->ad('Too few views');
        $d = $this->ad('Sold one', null, ['status' => ListingStatus::Sold]);
        $old = $this->ad('Popular long ago');
        $this->views($a, [0 => 20, 2 => 10]);
        $this->views($b, [1 => 12]);
        $this->views($c, [0 => 5]);
        $this->views($d, [0 => 99]);
        $this->views($old, [10 => 99]);

        $this->assertSame(['Most viewed', 'Second'], app(Trends::class)->popularAds($this->lk)->pluck('title')->all());
        $html = $this->get('/lk')->assertOk()->getContent();
        $this->assertStringContainsString('data-popular', $html);
        $this->assertStringContainsString(__('public.trends.popular'), $html);
    }

    public function test_trending_searches_rise_against_last_week_and_need_enough_searches(): void
    {
        $row = fn (string $term, int $daysAgo, int $n) => ['country_id' => $this->lk->id, 'term' => $term, 'date' => now()->subDays($daysAgo)->toDateString(), 'searches' => $n];
        DB::table('search_terms')->insert([
            $row('toyota aqua', 1, 8), $row('toyota aqua', 9, 2),
            $row('honda fit', 1, 6), $row('honda fit', 9, 9),
            $row('rare term', 1, 3),
            $row('new and big', 0, 12),
        ]);

        $this->assertSame([['term' => 'new and big', 'searches' => 12], ['term' => 'toyota aqua', 'searches' => 8]], app(Trends::class)->trendingSearches($this->lk));
    }

    public function test_trending_models_by_view_growth_in_the_country_and_category(): void
    {
        $aquaAd = $this->ad('Aqua 2015', $this->aqua);
        $fitAd = $this->ad('Fit 2014', $this->fit);
        $van = $this->ad('Aqua van', $this->aqua, ['category_id' => $this->vans->id]);
        $this->views($aquaAd, [1 => 20, 9 => 5]);
        $this->views($fitAd, [1 => 12, 9 => 15]);
        $this->views($van, [1 => 30]);

        $this->assertSame([['label' => 'Toyota Aqua', 'views' => 50]], app(Trends::class)->trendingModels($this->lk));
        $this->assertSame([['label' => 'Toyota Aqua', 'views' => 20]], app(Trends::class)->trendingModels($this->lk, $this->cars));

        $html = $this->get('/lk/ads/vehicles/cars')->assertOk()->getContent();
        $this->assertStringContainsString('data-trending-category', $html);
        $this->assertStringContainsString('Toyota Aqua', $html);
        $this->assertStringNotContainsString('data-trending-category', $this->get('/lk/ads/vehicles/cars?q=aqua')->getContent());
    }

    public function test_most_marked_sold_in_90_days_with_a_minimum(): void
    {
        foreach (range(1, 3) as $i) {
            $this->ad("Aqua sold {$i}", $this->aqua, ['status' => ListingStatus::Sold, 'sold_at' => now()->subDays(10)]);
        }
        foreach (range(1, 2) as $i) {
            $this->ad("Fit sold {$i}", $this->fit, ['status' => ListingStatus::Sold, 'sold_at' => now()->subDays(10)]);
        }
        $this->ad('Aqua sold long ago', $this->fit, ['status' => ListingStatus::Sold, 'sold_at' => now()->subDays(100)]);

        $this->assertSame([['label' => 'Toyota Aqua', 'sold' => 3]], app(Trends::class)->mostMarkedSold($this->lk));
        $this->get('/lk')->assertSee(__('public.trends.most_sold'))->assertSee(__('public.trends.most_sold_help'));
    }

    public function test_nothing_shows_without_enough_data(): void
    {
        $this->ad('Quiet ad', $this->aqua);

        $html = $this->get('/lk')->assertOk()->getContent();

        foreach (['data-popular', 'data-trending', 'data-most-sold'] as $marker) {
            $this->assertStringNotContainsString($marker, $html);
        }
    }

    public function test_lists_are_per_country(): void
    {
        $in = Country::factory()->create(['iso2' => 'IN', 'is_active' => true]);
        $indian = Listing::factory()->inCountry($in)->create(['category_id' => $this->cars->id, 'title' => 'Indian ad']);
        $this->views($indian, [0 => 50]);

        $this->assertTrue(app(Trends::class)->popularAds($this->lk)->isEmpty());
        $this->assertSame(['Indian ad'], app(Trends::class)->popularAds($in)->pluck('title')->all());
    }
}
