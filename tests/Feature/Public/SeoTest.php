<?php

namespace Tests\Feature\Public;

use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Listing;
use App\Models\Region;
use App\Services\Seo\SitemapBuilder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** SEO-02/03/04, global scenario 13 (T5.3). */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    private Country $lk;

    private Listing $ad;

    protected function setUp(): void
    {
        parent::setUp();
        Currency::factory()->create(['code' => 'LKR', 'symbol_override' => 'Rs']);
        $this->lk = Country::factory()->create(['iso2' => 'LK', 'iso3' => 'LKA', 'name' => ['en' => 'Sri Lanka'], 'default_currency' => 'LKR', 'number_locale' => 'en_LK']);
        $vehicles = Category::factory()->create(['slug' => 'vehicles', 'name' => ['en' => 'Vehicles']]);
        $cars = Category::factory()->childOf($vehicles)->create(['slug' => 'cars', 'name' => ['en' => 'Cars']]);
        $western = Region::factory()->create(['country_id' => $this->lk->id, 'slug' => 'western', 'name' => ['en' => 'Western']]);
        $colombo = Region::factory()->childOf($western)->create(['slug' => 'colombo-district', 'name' => ['en' => 'Colombo District']]);
        $nugegoda = City::factory()->inRegion($colombo)->create(['slug' => 'nugegoda', 'name' => ['en' => 'Nugegoda']]);

        $this->ad = Listing::factory()->inCountry($this->lk)->inCategory($cars)->create([
            'title' => 'Toyota Aqua 2015 hybrid', 'description' => 'One owner. Full service history.', 'price' => '6500000', 'price_type' => 'fixed',
            'condition' => 'used', 'region_id' => $western->id, 'subregion_id' => $colombo->id, 'city_id' => $nugegoda->id,
            'locality_text' => 'Near the bus stand',
        ]);
        config(['marketplace.seo.indexing' => true]);
    }

    /** @return list<array<string, mixed>> */
    private function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $matches);

        return array_map(fn (string $json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $matches[1]);
    }

    // ---- Listing page -----------------------------------------------------------------------

    public function test_listing_meta_tags(): void
    {
        $html = $this->get($this->ad->publicUrl())->assertOk()->getContent();

        $this->assertStringContainsString('<title>Toyota Aqua 2015 hybrid – Nugegoda, Sri Lanka | '.e(config('app.name')).'</title>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.$this->ad->publicUrl().'">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="product">', $html);
        $this->assertStringContainsString('<meta property="og:url" content="'.$this->ad->publicUrl().'">', $html);
        $this->assertStringContainsString('<meta name="robots" content="index,follow">', $html);
        $this->assertMatchesRegularExpression('#<meta name="description" content="Rs[^"]*6,500,000 · Near the bus stand, Nugegoda, Colombo District, Sri Lanka\. One owner#u', $html);
    }

    public function test_listing_structured_data(): void
    {
        [$product, $breadcrumbs] = $this->jsonLd($this->get($this->ad->publicUrl())->getContent());

        $this->assertSame('Product', $product['@type']);
        $this->assertSame($this->ad->publicUrl(), $product['url']);
        $this->assertSame('6500000', $product['offers']['price']);
        $this->assertSame('LKR', $product['offers']['priceCurrency'], 'Listing currency');
        $this->assertSame('https://schema.org/UsedCondition', $product['offers']['itemCondition']);
        $this->assertSame([
            '@type' => 'PostalAddress', 'addressLocality' => 'Nugegoda', 'addressRegion' => 'Colombo District', 'addressCountry' => 'LK',
        ], $product['offers']['availableAtOrFrom']['address'], 'Region and locality, never a street');

        $this->assertSame('BreadcrumbList', $breadcrumbs['@type']);
        $this->assertSame(['Sri Lanka', 'Vehicles', 'Cars', 'Toyota Aqua 2015 hybrid'], array_column($breadcrumbs['itemListElement'], 'name'));
        $this->assertSame(url('/lk/ads/vehicles/cars'), $breadcrumbs['itemListElement'][2]['item']);
    }

    public function test_structured_data_cannot_break_out_of_its_script(): void
    {
        $this->ad->forceFill(['title' => 'Bargain </script><script>alert(1)</script>'])->save();

        $response = $this->get($this->ad->fresh()->publicUrl())->assertOk();

        $this->assertStringNotContainsString('<script>alert(1)', $response->getContent());
        $this->assertSame('Bargain </script><script>alert(1)</script>', $this->jsonLd($response->getContent())[0]['name']);
    }

    public function test_wanted_ads_have_no_offer(): void
    {
        $this->ad->forceFill(['type' => 'wanted'])->save();

        $this->assertArrayNotHasKey('offers', $this->jsonLd($this->get($this->ad->publicUrl())->getContent())[0]);
    }

    // ---- Search pages -------------------------------------------------------------------------

    public function test_landing_pages_are_indexable_and_filtered_pages_point_to_them(): void
    {
        $landing = url('/lk/ads/vehicles/cars/in/colombo-district/nugegoda');

        $html = $this->get($landing)->assertOk()->getContent();
        $this->assertStringContainsString('<title>Cars in Nugegoda, Colombo District, Sri Lanka |', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.$landing.'">', $html);
        $this->assertStringContainsString('content="index,follow"', $html);
        $this->assertStringContainsString('1 ad for Cars in Nugegoda', $html);

        $filtered = $this->get($landing.'?condition=used&page=1')->assertOk()->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="'.$landing.'">', $filtered);
        $this->assertStringContainsString('content="noindex,follow"', $filtered);
    }

    public function test_nothing_is_indexed_while_indexing_is_off(): void
    {
        config(['marketplace.seo.indexing' => false]);

        $this->get($this->ad->publicUrl())->assertSee('content="noindex,nofollow"', false);
        $this->get('/lk')->assertSee('content="noindex,nofollow"', false);
    }

    // ---- robots.txt ---------------------------------------------------------------------------

    public function test_robots_txt_follows_the_indexing_switch(): void
    {
        $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /account')->assertSee('Disallow: /admin')->assertSee('Sitemap: '.url('sitemap.xml'))
            ->assertDontSee("Disallow: /\n", false);

        config(['marketplace.seo.indexing' => false]);
        $this->assertSame("User-agent: *\nDisallow: /\n", $this->get('/robots.txt')->getContent());
    }

    // ---- Sitemaps -----------------------------------------------------------------------------

    public function test_sitemaps_list_landing_pages_and_public_ads_only(): void
    {
        $root = sys_get_temp_dir().'/sitemap-test-'.uniqid();
        Listing::factory()->inCountry($this->lk)->inCategory($this->ad->category)->draft()->create(['title' => 'Draft ad']);
        Country::factory()->inactive()->create(['iso2' => 'DE', 'iso3' => 'DEU']);
        mkdir($root.'/sitemaps', 0777, true);
        file_put_contents($root.'/sitemaps/zz-ads-9.xml', 'stale');

        try {
            $files = app(SitemapBuilder::class)->build($root);

            $this->assertSame(['sitemap.xml', 'sitemaps/lk-pages.xml', 'sitemaps/lk-ads-1.xml'], $files);
            $this->assertFileDoesNotExist($root.'/sitemaps/zz-ads-9.xml', 'Stale file removed');

            $index = (string) file_get_contents($root.'/sitemap.xml');
            $this->assertStringContainsString('<loc>'.url('sitemaps/lk-pages.xml').'</loc>', $index);

            $pages = (string) file_get_contents($root.'/sitemaps/lk-pages.xml');
            foreach (['/lk', '/lk/ads', '/lk/ads/vehicles', '/lk/ads/vehicles/cars', '/lk/ads/in/western', '/lk/ads/in/colombo-district'] as $path) {
                $this->assertStringContainsString('<loc>'.url($path).'</loc>', $pages);
            }

            $ads = (string) file_get_contents($root.'/sitemaps/lk-ads-1.xml');
            $this->assertStringContainsString('<loc>'.$this->ad->publicUrl().'</loc><lastmod>', $ads);
            $this->assertSame(1, substr_count($ads, '<url>'), 'Draft not listed');
            $this->assertNotFalse(simplexml_load_string($ads), 'Valid XML');
        } finally {
            (new Filesystem)->deleteDirectory($root);
        }
    }

    public function test_ads_are_split_into_chunks(): void
    {
        config(['marketplace.seo.sitemap_chunk' => 2]);
        Listing::factory()->count(2)->inCountry($this->lk)->inCategory($this->ad->category)->create();
        $root = sys_get_temp_dir().'/sitemap-test-'.uniqid();

        try {
            $files = app(SitemapBuilder::class)->build($root);

            $this->assertContains('sitemaps/lk-ads-2.xml', $files);
            $this->assertSame(1, substr_count((string) file_get_contents($root.'/sitemaps/lk-ads-2.xml'), '<url>'));
            config(['marketplace.seo.sitemap_root' => $root]); // never write into the real public/ from a test
            $this->artisan('sitemap:generate')->expectsOutputToContain('Sitemap files written: 4.')->assertSuccessful();
        } finally {
            (new Filesystem)->deleteDirectory($root);
        }
    }
}
