<?php

namespace Tests\Feature\Search;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Services\Search\SearchTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** G1 (ADR-027): counted search terms and live search suggestions. */
class LiveSearchTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36';

    private Country $lk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lk = Country::factory()->create(['iso2' => 'LK', 'is_active' => true]);
        $vehicles = Category::factory()->create(['slug' => 'vehicles', 'name' => ['en' => 'Vehicles']]);
        Category::factory()->create(['parent_id' => $vehicles->id, 'slug' => 'cars', 'name' => ['en' => 'Cars']]);
        Category::factory()->create(['parent_id' => $vehicles->id, 'slug' => 'vans', 'name' => ['en' => 'Vans']]);
        config(['marketplace.search.min_searches' => 2]);
    }

    private function searches(): array
    {
        return DB::table('search_terms')->orderBy('term')->get(['country_id', 'term', 'searches'])->map(fn ($r) => [$r->country_id, $r->term, $r->searches])->all();
    }

    private function search(string $url): void
    {
        $this->withHeader('User-Agent', self::BROWSER)->get($url)->assertOk();
    }

    // ---- Counting search terms --------------------------------------------------------------

    public function test_terms_are_normalised(): void
    {
        $this->assertSame('toyota aqua', SearchTerms::normalise('  Toyota   AQUA!! '));
        $this->assertNull(SearchTerms::normalise('!'));
        $this->assertNull(SearchTerms::normalise('a'));
        $this->assertLessThanOrEqual(SearchTerms::MAX_LENGTH, mb_strlen((string) SearchTerms::normalise(str_repeat('word ', 30))));
    }

    public function test_a_search_counts_once_per_visitor_term_and_day_without_personal_data(): void
    {
        $this->search('/lk/ads?q=Toyota+Aqua');
        $this->search('/lk/ads?q=toyota%20aqua');
        $this->search('/lk/ads?q=toyota+aqua&page=2');
        $this->search('/ads?q=honda');

        $this->assertSame([[0, 'honda', 1], [$this->lk->id, 'toyota aqua', 1]], $this->searches());
        $this->assertEqualsCanonicalizing(['id', 'country_id', 'term', 'date', 'searches'], Schema::getColumnListing('search_terms'), 'no user, session or IP');

        $this->flushSession();
        $this->search('/lk/ads?q=toyota+aqua');
        $this->assertSame([[0, 'honda', 1], [$this->lk->id, 'toyota aqua', 2]], $this->searches());
    }

    public function test_bots_and_empty_searches_are_not_counted(): void
    {
        $this->withHeader('User-Agent', 'Googlebot/2.1')->get('/lk/ads?q=toyota')->assertOk();
        $this->search('/lk/ads');

        $this->assertSame([], $this->searches());
    }

    public function test_popular_terms_need_the_minimum_count(): void
    {
        DB::table('search_terms')->insert([
            ['country_id' => $this->lk->id, 'term' => 'toyota aqua', 'date' => now()->toDateString(), 'searches' => 3],
            ['country_id' => $this->lk->id, 'term' => 'toyota premio', 'date' => now()->subDays(3)->toDateString(), 'searches' => 1],
            ['country_id' => $this->lk->id, 'term' => 'toyota premio', 'date' => now()->toDateString(), 'searches' => 1],
            ['country_id' => $this->lk->id, 'term' => 'toyota rare', 'date' => now()->toDateString(), 'searches' => 1],
            ['country_id' => $this->lk->id, 'term' => 'toyota old', 'date' => now()->subDays(60)->toDateString(), 'searches' => 50],
        ]);

        $this->assertSame([['term' => 'toyota aqua', 'searches' => 3], ['term' => 'toyota premio', 'searches' => 2]], app(SearchTerms::class)->popular($this->lk, 'toyota'));
    }

    public function test_old_search_counts_are_pruned(): void
    {
        DB::table('search_terms')->insert([
            ['country_id' => $this->lk->id, 'term' => 'old', 'date' => now()->subDays(401)->toDateString(), 'searches' => 9],
            ['country_id' => $this->lk->id, 'term' => 'recent', 'date' => now()->subDays(30)->toDateString(), 'searches' => 9],
        ]);

        $this->artisan('marketplace:prune')->assertSuccessful();

        $this->assertSame(['recent'], DB::table('search_terms')->pluck('term')->all());
    }

    // ---- Suggestions ------------------------------------------------------------------------

    /** @return list<array{type: string, label: string, url: string}> */
    private function suggest(string $q, ?string $cc = 'lk'): array
    {
        return $this->getJson(route('search.suggest', array_filter(['q' => $q, 'cc' => $cc])))->assertOk()
            ->assertHeader('Cache-Control', 'max-age=300, public')->json('items');
    }

    public function test_categories_makes_models_places_and_popular_searches(): void
    {
        $make = Attribute::factory()->select([])->create(['key' => 'make']);
        $model = Attribute::factory()->select([])->create(['key' => 'model', 'depends_on_attribute_id' => $make->id]);
        $toyota = AttributeOption::factory()->create(['attribute_id' => $make->id, 'label' => ['en' => 'Toyota']]);
        AttributeOption::factory()->create(['attribute_id' => $model->id, 'parent_option_id' => $toyota->id, 'label' => ['en' => 'Aqua']]);
        $region = Region::factory()->create(['country_id' => $this->lk->id, 'slug' => 'western', 'name' => 'Western']);
        City::factory()->inRegion($region)->create(['name' => 'Vavuniya', 'ascii_name' => 'Vavuniya', 'slug' => 'vavuniya']);
        DB::table('search_terms')->insert(['country_id' => $this->lk->id, 'term' => 'van for hire', 'date' => now()->toDateString(), 'searches' => 4]);

        $items = $this->suggest('va');

        $this->assertContains(['type' => 'category', 'label' => 'Vehicles › Vans', 'url' => route('ads.category', ['cc' => 'lk', 'category' => 'vehicles', 'subcategory' => 'vans'])], $items);
        $this->assertContains(['type' => 'place', 'label' => 'Vavuniya, Western', 'url' => route('ads.location', ['cc' => 'lk', 'region' => 'western', 'city' => 'vavuniya'])], $items);
        $this->assertContains(['type' => 'popular', 'label' => 'van for hire', 'url' => route('ads.country', ['cc' => 'lk', 'q' => 'van for hire'])], $items);

        $this->assertContains(['type' => 'make', 'label' => 'Toyota Aqua', 'url' => route('ads.country', ['cc' => 'lk', 'q' => 'Toyota Aqua'])], $this->suggest('aqu'));
        $this->assertContains('Toyota', array_column($this->suggest('toy'), 'label'));
    }

    public function test_without_a_country_only_makes_and_popular_searches(): void
    {
        $items = $this->suggest('va', null);

        $this->assertSame([], array_values(array_filter($items, fn ($i) => in_array($i['type'], ['category', 'place'], true))));
    }

    public function test_short_or_missing_text(): void
    {
        $this->assertSame([], $this->suggest('v'));
        $this->getJson(route('search.suggest'))->assertStatus(422);
        $this->getJson(route('search.suggest', ['q' => str_repeat('x', 101)]))->assertStatus(422);
    }

    public function test_search_boxes_carry_the_suggestion_hooks(): void
    {
        $html = $this->get('/lk')->assertOk()->getContent();

        $this->assertStringContainsString('data-suggest-url="'.route('search.suggest').'"', $html);
        $this->assertStringContainsString('data-suggest-cc="lk"', $html);
    }
}
