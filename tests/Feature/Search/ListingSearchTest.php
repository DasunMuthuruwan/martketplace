<?php

namespace Tests\Feature\Search;

use App\Enums\AttributeType;
use App\Enums\ListingStatus;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Listing;
use App\Models\Locality;
use App\Models\MeasurementUnit;
use App\Models\Region;
use App\Services\Search\ListingSearch;
use App\Services\Search\SearchCriteria;
use App\Services\Search\SearchCriteriaFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Filters, currency rule, sorting and paging (T5.1, SRCH-03–05, global scenarios 5–7).
 * Keyword search is in KeywordSearchTest (it needs committed rows for FULLTEXT).
 */
class ListingSearchTest extends TestCase
{
    use RefreshDatabase;

    private Country $lk;

    private Country $in;

    private Category $vehicles;

    private Category $cars;

    private Category $vans;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lk = Country::factory()->create(['iso2' => 'LK', 'iso3' => 'LKA', 'default_currency' => Currency::factory()->create(['code' => 'LKR'])->code]);
        $this->in = Country::factory()->create(['iso2' => 'IN', 'iso3' => 'IND', 'default_currency' => Currency::factory()->create(['code' => 'INR'])->code]);
        $this->vehicles = Category::factory()->create(['slug' => 'vehicles']);
        $this->cars = Category::factory()->childOf($this->vehicles)->create(['slug' => 'cars']);
        $this->vans = Category::factory()->childOf($this->vehicles)->create(['slug' => 'vans']);
    }

    private function ad(array $attributes = [], ?Country $country = null, ?Category $category = null): Listing
    {
        return Listing::factory()->inCountry($country ?? $this->lk)->inCategory($category ?? $this->cars)->create($attributes);
    }

    /** @return list<int> ids in result order */
    private function search(array $input, ?Country $country = null, bool $global = false): array
    {
        $criteria = app(SearchCriteriaFactory::class)->fromInput($input, $global ? null : ($country ?? $this->lk));

        return app(ListingSearch::class)->search($criteria)->getCollection()->pluck('id')->all();
    }

    private function rejected(array $input, ?Country $country = null, bool $global = false): string
    {
        try {
            app(SearchCriteriaFactory::class)->fromInput($input, $global ? null : ($country ?? $this->lk));
        } catch (ValidationException $e) {
            return (string) array_key_first($e->errors());
        }
        $this->fail('Filter accepted');
    }

    // ---- Scope -------------------------------------------------------------------------------

    public function test_only_public_listings_of_the_country_newest_first(): void
    {
        $older = $this->ad(['published_at' => now()->subDays(3)]);
        $newer = $this->ad(['published_at' => now()->subDay()]);
        $this->ad([], $this->in);
        $this->ad(['status' => ListingStatus::PendingReview]);
        $this->ad(['expires_at' => now()->subMinute()]);

        $this->assertSame([$newer->id, $older->id], $this->search([]));
        $this->assertCount(3, $this->search([], global: true), 'Global search covers every active country');
    }

    public function test_category_includes_its_subcategories(): void
    {
        $car = $this->ad();
        $van = $this->ad([], category: $this->vans);

        $this->assertEqualsCanonicalizing([$car->id, $van->id], $this->search(['category' => 'vehicles']));
        $this->assertSame([$van->id], $this->search(['category' => 'vans']));
        $this->assertSame('category', $this->rejected(['category' => 'no-such-category']));

        $scoped = Category::factory()->childOf($this->vehicles)->scopedTo(['IN'])->create(['slug' => 'three-wheelers']);
        $this->assertSame('category', $this->rejected(['category' => $scoped->slug]), 'Not offered in LK');
    }

    public function test_type_and_condition(): void
    {
        $sale = $this->ad(['type' => 'sale', 'condition' => 'used']);
        $rent = $this->ad(['type' => 'rent', 'condition' => 'new']);

        $this->assertSame([$rent->id], $this->search(['type' => 'rent']));
        $this->assertSame([$sale->id], $this->search(['condition' => 'used']));
        $this->assertSame('type', $this->rejected(['type' => 'auction']));
    }

    // ---- Location (scenario 5) ----------------------------------------------------------------

    public function test_each_location_level_narrows_the_next(): void
    {
        $western = Region::factory()->create(['country_id' => $this->lk->id, 'slug' => 'western']);
        $colomboDistrict = Region::factory()->childOf($western)->create(['slug' => 'colombo-district']);
        $southern = Region::factory()->create(['country_id' => $this->lk->id, 'slug' => 'southern']);
        $nugegoda = City::factory()->inRegion($colomboDistrict)->create(['slug' => 'nugegoda']);
        $galle = City::factory()->inRegion($southern)->create(['slug' => 'galle']);
        $junction = Locality::factory()->create(['city_id' => $nugegoda->id, 'slug' => 'junction']);

        $inNugegoda = $this->ad(['region_id' => $western->id, 'subregion_id' => $colomboDistrict->id, 'city_id' => $nugegoda->id, 'locality_id' => $junction->id]);
        $inNugegodaElsewhere = $this->ad(['region_id' => $western->id, 'subregion_id' => $colomboDistrict->id, 'city_id' => $nugegoda->id]);
        $inGalle = $this->ad(['region_id' => $southern->id, 'city_id' => $galle->id]);

        $this->assertEqualsCanonicalizing([$inNugegoda->id, $inNugegodaElsewhere->id], $this->search(['region' => 'western']));
        $this->assertEqualsCanonicalizing([$inNugegoda->id, $inNugegodaElsewhere->id], $this->search(['region' => 'western', 'subregion' => 'colombo-district']));
        $this->assertSame([$inGalle->id], $this->search(['city' => 'galle']));
        $this->assertEqualsCanonicalizing([$inNugegoda->id, $inNugegodaElsewhere->id], $this->search(['region' => 'western', 'city' => 'nugegoda']), 'City under the region via its district');
        $this->assertSame([$inNugegoda->id], $this->search(['city' => 'nugegoda', 'locality' => 'junction']));

        // A renamed city keeps its listings (filters use ids, global scenario 5).
        $nugegoda->update(['name' => ['en' => 'Nugegoda Town']]);
        $this->assertCount(2, $this->search(['city' => 'nugegoda']));

        $this->assertSame('city', $this->rejected(['region' => 'southern', 'city' => 'nugegoda']), 'City not in that region');
        $this->assertSame('subregion', $this->rejected(['region' => 'southern', 'subregion' => 'colombo-district']));
        $this->assertSame('locality', $this->rejected(['locality' => 'junction']), 'Locality needs a city');
        $this->assertSame('region', $this->rejected(['region' => 'western'], global: true), 'Location needs a country');
    }

    // ---- Currency rule and price (scenarios 6, 7) ---------------------------------------------

    public function test_price_range_and_sort_within_the_country_currency(): void
    {
        $cheap = $this->ad(['price' => '1500000']);
        $mid = $this->ad(['price' => '3000000']);
        $dear = $this->ad(['price' => '9000000']);
        $onRequest = $this->ad(['price' => null, 'price_type' => 'on_request']);
        $foreign = $this->ad(['price' => '2000000', 'currency' => 'INR']); // future multi-currency country

        $this->assertSame([$mid->id], $this->search(['min_price' => '2000000', 'max_price' => '5000000']));
        $this->assertSame([$mid->id], $this->search(['min_price' => '5000000', 'max_price' => '2000000']), 'Swapped bounds');
        $this->assertSame([$cheap->id, $mid->id, $dear->id, $onRequest->id], $this->search(['sort' => 'price_asc']));
        $this->assertSame([$dear->id, $mid->id, $cheap->id, $onRequest->id], $this->search(['sort' => 'price_desc']));
        $this->assertNotContains($foreign->id, $this->search(['sort' => 'price_asc']), 'Other currencies never mixed in');
        $this->assertContains($foreign->id, $this->search([]), 'Without a price filter all ads show');

        $this->assertSame('currency', $this->rejected(['currency' => 'USD']), 'Not a currency of this country');
    }

    public function test_global_search_needs_a_currency_for_prices(): void
    {
        $lkr = $this->ad(['price' => '1000000']);
        $inr = $this->ad(['price' => '500000'], $this->in);
        $inrDear = $this->ad(['price' => '900000'], $this->in);

        $criteria = app(SearchCriteriaFactory::class)->fromInput(['min_price' => '1', 'sort' => 'price_asc'], null);
        $this->assertTrue($criteria->priceIgnored);
        $this->assertSame(SearchCriteria::SORT_NEWEST, $criteria->sort);
        $this->assertCount(3, $this->search(['min_price' => '1', 'sort' => 'price_asc'], global: true), 'Ignored, not applied');

        $this->assertSame([$inr->id, $inrDear->id], $this->search(['currency' => 'inr', 'sort' => 'price_asc'], global: true));
        $this->assertSame([$lkr->id], $this->search(['currency' => 'LKR', 'max_price' => '2000000'], global: true));
        $this->assertSame('currency', $this->rejected(['currency' => 'XYZ'], global: true));
    }

    // ---- Attribute filters ------------------------------------------------------------------

    private function attach(Attribute $attribute, Category $category, bool $filterable = true): Attribute
    {
        $category->attributeDefinitions()->attach($attribute->id, ['is_filterable' => $filterable]);

        return $attribute;
    }

    private function value(Listing $listing, Attribute $attribute, array $columns): void
    {
        $listing->attributeValues()->create(['attribute_id' => $attribute->id] + $columns);
    }

    public function test_select_range_and_boolean_filters(): void
    {
        $make = $this->attach(Attribute::factory()->select(['toyota', 'honda', 'ford'])->create(['key' => 'make']), $this->vehicles);
        $year = $this->attach(Attribute::factory()->ofType(AttributeType::Year)->create(['key' => 'year']), $this->cars);
        $parking = $this->attach(Attribute::factory()->ofType(AttributeType::Boolean)->create(['key' => 'parking']), $this->cars);
        $trim = $this->attach(Attribute::factory()->create(['key' => 'trim']), $this->cars, filterable: false);
        $option = fn (string $value) => AttributeOption::where('attribute_id', $make->id)->where('value', $value)->value('id');

        $toyota2015 = $this->ad();
        $this->value($toyota2015, $make, ['value_option_id' => $option('toyota')]);
        $this->value($toyota2015, $year, ['value_number' => '2015']);
        $this->value($toyota2015, $parking, ['value_bool' => true]);
        $honda2019 = $this->ad();
        $this->value($honda2019, $make, ['value_option_id' => $option('honda')]);
        $this->value($honda2019, $year, ['value_number' => '2019']);

        $this->assertSame([$toyota2015->id], $this->search(['category' => 'cars', 'a' => ['make' => 'toyota']]));
        $this->assertEqualsCanonicalizing([$toyota2015->id, $honda2019->id], $this->search(['category' => 'cars', 'a' => ['make' => ['toyota', 'honda']]]), 'OR within one attribute');
        $this->assertSame([$honda2019->id], $this->search(['category' => 'cars', 'a' => ['year' => ['min' => '2017']]]));
        $this->assertSame([$toyota2015->id], $this->search(['category' => 'cars', 'a' => ['make' => 'toyota', 'year' => ['max' => '2016'], 'parking' => '1']]), 'AND across attributes');
        $this->assertSame([], $this->search(['category' => 'cars', 'a' => ['make' => 'ford']]));
        $this->assertSame([$toyota2015->id], $this->search(['category' => 'vehicles', 'a' => ['make' => 'toyota']]), 'Parent category uses its own attributes');

        $this->assertEqualsCanonicalizing([$toyota2015->id, $honda2019->id], $this->search(['category' => 'cars', 'a' => ['make' => 'toyota,honda']]), 'Comma-separated values');

        // Ignored, never passed to SQL (routes doc §1.1): unknown, not filterable, no category or country.
        $both = [$toyota2015->id, $honda2019->id];
        $this->assertEqualsCanonicalizing($both, $this->search(['category' => 'cars', 'a' => ['trim' => 'GL', 'nope' => 'x']]));
        $this->assertEqualsCanonicalizing($both, $this->search(['a' => ['make' => 'toyota']]));
        $this->assertCount(2, $this->search(['category' => 'cars', 'a' => ['make' => 'toyota']], global: true));

        // Invalid values of a filterable attribute are rejected under their query key.
        $this->assertSame('a.make', $this->rejected(['category' => 'cars', 'a' => ['make' => 'tesla']]));
        $this->assertSame('a.year', $this->rejected(['category' => 'cars', 'a' => ['year' => '2015']]), 'Ranges need min/max');
        $this->assertSame('a.year', $this->rejected(['category' => 'cars', 'a' => ['year' => ['min' => 'old']]]));
        $this->assertSame('a', $this->rejected(['category' => 'cars', 'a' => array_fill_keys(range('a', 'k'), 'x')]), 'At most 10 filters');
    }

    public function test_dependent_select_filter_is_limited_to_the_chosen_parent(): void
    {
        $make = $this->attach(Attribute::factory()->select(['ford', 'hino'])->create(['key' => 'make']), $this->cars);
        $model = $this->attach(Attribute::factory()->create(['key' => 'model', 'type' => AttributeType::Select, 'depends_on_attribute_id' => $make->id]), $this->cars);
        $fordRanger = AttributeOption::create(['attribute_id' => $model->id, 'parent_option_id' => $make->options()->where('value', 'ford')->value('id'), 'value' => 'ranger', 'label' => ['en' => 'Ranger']]);
        $hinoRanger = AttributeOption::create(['attribute_id' => $model->id, 'parent_option_id' => $make->options()->where('value', 'hino')->value('id'), 'value' => 'ranger', 'label' => ['en' => 'Ranger']]);

        $pickup = $this->ad();
        $this->value($pickup, $make, ['value_option_id' => $fordRanger->parent_option_id]);
        $this->value($pickup, $model, ['value_option_id' => $fordRanger->id]);
        $truck = $this->ad();
        $this->value($truck, $make, ['value_option_id' => $hinoRanger->parent_option_id]);
        $this->value($truck, $model, ['value_option_id' => $hinoRanger->id]);

        $this->assertEqualsCanonicalizing([$pickup->id, $truck->id], $this->search(['category' => 'cars', 'a' => ['model' => 'ranger']]), 'Without a make: every "Ranger"');
        // With a make chosen, the model filter only matches that make's option, even on its own.
        $criteria = app(SearchCriteriaFactory::class)->fromInput(['category' => 'cars', 'a' => ['make' => 'ford', 'model' => 'ranger']], $this->lk);
        $this->assertSame([$fordRanger->id], $criteria->attributeFilters[1]->optionIds);
        $this->assertSame([$pickup->id], app(ListingSearch::class)->search($criteria)->getCollection()->pluck('id')->all());
    }

    public function test_area_filters_convert_to_square_metres(): void
    {
        $perch = MeasurementUnit::create(['code' => 'perch', 'name' => ['en' => 'perch'], 'symbol' => 'P', 'factor_to_base' => '25.29285264']);
        $acre = MeasurementUnit::create(['code' => 'acre', 'name' => ['en' => 'acre'], 'symbol' => 'ac', 'factor_to_base' => '4046.8564224']);
        $this->lk->areaUnits()->attach($perch->id, ['is_default' => true, 'sort_order' => 1]);
        $this->lk->areaUnits()->attach($acre->id, ['sort_order' => 2]);
        $land = Category::factory()->childOf(Category::factory()->create())->create(['slug' => 'land']);
        $size = $this->attach(Attribute::factory()->ofType(AttributeType::Number)->create(['key' => 'land_size', 'dimension' => 'area']), $land);

        $small = $this->ad([], category: $land);
        $this->value($small, $size, ['value_number' => '505.8571', 'input_value' => '20', 'input_unit_id' => $perch->id]); // 20 perches
        $big = $this->ad([], category: $land);
        $this->value($big, $size, ['value_number' => '4046.8564', 'input_value' => '1', 'input_unit_id' => $acre->id]); // 1 acre = 160 perches

        $this->assertSame([$big->id], $this->search(['category' => 'land', 'a' => ['land_size' => ['min' => '100', 'unit' => 'perch']]]));
        $this->assertSame([$small->id], $this->search(['category' => 'land', 'a' => ['land_size' => ['max' => '0.5', 'unit' => 'acre']]]));
        $this->assertSame([$small->id], $this->search(['category' => 'land', 'a' => ['land_size' => ['max' => '50']]]), 'Default unit (perch)');
        $this->assertSame('a.land_size', $this->rejected(['category' => 'land', 'a' => ['land_size' => ['min' => '1', 'unit' => 'sqft']]]), 'Unit not offered in LK');
    }

    // ---- Paging -------------------------------------------------------------------------------

    public function test_pages_of_24_with_a_cap(): void
    {
        Listing::factory()->count(25)->inCountry($this->lk)->inCategory($this->cars)->create();
        $criteria = app(SearchCriteriaFactory::class)->fromInput(['page' => '2', 'category' => 'cars'], $this->lk);

        $page = app(ListingSearch::class)->search($criteria);

        $this->assertSame(25, $page->total());
        $this->assertCount(1, $page->items());
        $this->assertSame('page', $this->rejected(['page' => (string) (config('marketplace.listings.max_page') + 1)]));
    }
}
