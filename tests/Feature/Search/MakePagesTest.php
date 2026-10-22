<?php

namespace Tests\Feature\Search;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use App\Services\Insights\MakePages;
use App\Services\Seo\SitemapBuilder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** G4 (ADR-027): make/model landing pages with a price guide, linked from ads and in the sitemap. */
class MakePagesTest extends TestCase
{
    use RefreshDatabase;

    private Country $lk;

    private Category $cars;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->lk = Country::where('iso2', 'LK')->firstOrFail();
        $this->lk->update(['is_active' => true]);
        $this->cars = Category::where('slug', 'cars')->firstOrFail();
    }

    private function option(string $key, string $value): AttributeOption
    {
        return AttributeOption::whereHas('attribute', fn ($q) => $q->where('key', $key))->where('value', $value)->firstOrFail();
    }

    private function car(string $title, string $make, ?string $model, string $price, array $attributes = []): Listing
    {
        $ad = Listing::factory()->inCountry($this->lk)->create(['category_id' => $this->cars->id, 'title' => $title, 'price' => $price] + $attributes);
        foreach (array_filter(['make' => $make, 'model' => $model]) as $key => $value) {
            DB::table('listing_attribute_values')->insert([
                'listing_id' => $ad->id, 'attribute_id' => Attribute::where('key', $key)->value('id'), 'value_option_id' => $this->option($key, $value)->id,
            ]);
        }

        return $ad;
    }

    private function url(string $make, ?string $model = null): string
    {
        return route('ads.make', array_filter(['cc' => 'lk', 'category' => 'vehicles', 'subcategory' => 'cars', 'make' => $make, 'model' => $model]));
    }

    public function test_a_model_page_lists_its_ads_with_its_own_title_and_a_price_guide(): void
    {
        foreach ([5000000, 5500000, 6000000, 6200000, 7000000] as $i => $price) {
            $this->car("Aqua number {$i}", 'toyota', 'aqua', (string) $price);
        }
        $this->car('Honda Fit 2015', 'honda', 'fit', '4000000');

        $response = $this->get($this->url('toyota', 'aqua'))->assertOk();

        $response->assertSee('Toyota Aqua for sale in Sri Lanka')
            ->assertSee('Aqua number 4')->assertDontSee('Honda Fit 2015')
            ->assertSee('data-price-guide', false)
            ->assertSee('6,000,000');
        $html = $response->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="'.$this->url('toyota', 'aqua').'"', $html);

        config(['marketplace.seo.indexing' => true]); // as in production
        $this->get($this->url('toyota', 'aqua'))->assertSee('<meta name="robots" content="index,follow">', false);
        $this->get($this->url('toyota', 'aqua').'?sort=price_asc')->assertSee('noindex', false);
    }

    public function test_the_make_page_covers_all_its_models_and_shows_no_guide_with_few_ads(): void
    {
        $this->car('Toyota Aqua 2014', 'toyota', 'aqua', '5000000');
        $this->car('Toyota Prius 2016', 'toyota', 'prius', '8000000');

        $this->get($this->url('toyota'))->assertOk()
            ->assertSee('Toyota for sale in Sri Lanka')
            ->assertSee('Toyota Aqua 2014')->assertSee('Toyota Prius 2016')
            ->assertDontSee('data-price-guide', false);
    }

    public function test_unknown_or_mismatched_makes_and_models_are_not_found(): void
    {
        $this->get($this->url('no-such-make'))->assertNotFound();
        $this->get($this->url('toyota', 'fit'))->assertNotFound(); // Fit is a Honda
        $this->get(route('ads.make', ['cc' => 'lk', 'category' => 'property', 'subcategory' => 'houses-for-sale', 'make' => 'toyota']))->assertNotFound();
    }

    public function test_extreme_prices_do_not_skew_the_guide(): void
    {
        foreach (range(1, 19) as $i) {
            $this->car("Aqua {$i}", 'toyota', 'aqua', (string) (5000000 + $i * 10000));
        }
        $this->car('Typo price', 'toyota', 'aqua', '1');

        $html = $this->get($this->url('toyota', 'aqua'))->assertOk()->getContent();
        preg_match('/data-price-guide.*?<\/section>/s', $html, $m);

        $this->assertStringNotContainsString('>1<', str_replace(' ', '', $m[0] ?? ''));
        $this->assertStringContainsString('5,1', $m[0] ?? '');
    }

    public function test_ads_link_to_their_model_page_and_the_sitemap_lists_them(): void
    {
        $ad = $this->car('Toyota Aqua 2015', 'toyota', 'aqua', '5000000');

        $this->get($ad->publicUrl())->assertOk()
            ->assertSee(__('public.make_page.more', ['what' => 'Toyota Aqua']))
            ->assertSee($this->url('toyota', 'aqua'), false);
        $urls = app(MakePages::class)->urlsFor($this->lk);
        $this->assertContains($this->url('toyota'), $urls);
        $this->assertContains($this->url('toyota', 'aqua'), $urls);
        $this->assertNotNull(app(SitemapBuilder::class));
    }
}
