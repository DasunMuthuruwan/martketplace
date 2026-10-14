<?php

namespace Tests\Feature\Search;

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Listing;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** F2.2: search page UI (layout doc §3.2, components §5.3, global UX §5). */
class SearchPageTest extends TestCase
{
    use RefreshDatabase;

    private Country $country;

    private Category $vehicles;

    private Category $cars;

    private string $cc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->country = Country::factory()->create(['name' => ['en' => 'Testland'], 'region_levels' => 1]);
        $this->cc = strtolower($this->country->iso2);
        $this->vehicles = Category::factory()->create(['name' => ['en' => 'Vehicles'], 'slug' => 'vehicles', 'icon' => 'car']);
        $this->cars = Category::factory()->create(['parent_id' => $this->vehicles->id, 'name' => ['en' => 'Cars'], 'slug' => 'cars']);
        Category::factory()->create(['parent_id' => $this->vehicles->id, 'name' => ['en' => 'Vans'], 'slug' => 'vans']);
    }

    private function ad(array $attributes = []): Listing
    {
        return Listing::factory()->inCountry($this->country)->create(['category_id' => $this->cars->id] + $attributes);
    }

    private function carsUrl(array $query = []): string
    {
        return route('ads.category', ['cc' => $this->cc, 'category' => 'vehicles', 'subcategory' => 'cars']).($query ? '?'.http_build_query($query) : '');
    }

    public function test_page_has_heading_count_filters_sidebar_drawer_and_breadcrumbs(): void
    {
        $this->ad(['title' => 'Blue hatchback']);

        $html = $this->get($this->carsUrl())->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('1 ad', $html);
        $this->assertStringContainsString('data-search-form', $html);
        $this->assertSame(2, substr_count($html, 'data-search-form'));          // sidebar + phone drawer
        $this->assertStringContainsString('data-modal-open="search-filters"', $html);
        $this->assertStringContainsString('aria-label="'.__('search.breadcrumbs').'"', $html);
        $this->assertStringContainsString('Blue hatchback', $html);
        $this->assertStringContainsString('listing-grid', $html);
    }

    public function test_category_links_show_the_current_branch(): void
    {
        $html = $this->get($this->carsUrl())->getContent();

        $this->assertStringContainsString(route('ads.category', ['cc' => $this->cc, 'category' => 'vehicles', 'subcategory' => 'vans']), $html);
        $this->assertMatchesRegularExpression('/aria-current="page"\s+class="[^"]*"\s*>Cars</', $html);

        $top = $this->get(route('ads.country', ['cc' => $this->cc]))->getContent();
        $this->assertStringContainsString(route('ads.category', ['cc' => $this->cc, 'category' => 'vehicles']), $top);
    }

    public function test_active_filters_become_chips_that_remove_one_filter_each(): void
    {
        $html = $this->get($this->carsUrl(['q' => 'hybrid', 'type' => 'sale', 'condition' => 'used']))->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="Remove filter: “hybrid”"', $html);
        // Removing the condition keeps the keyword and type.
        $this->assertStringContainsString('href="'.e($this->carsUrl(['q' => 'hybrid', 'type' => 'sale'])).'"', $html);
        // Clear all keeps the keyword (it defines the search).
        $this->assertStringContainsString(e($this->carsUrl(['q' => 'hybrid'])), $html);
    }

    public function test_location_selects_follow_the_chosen_region(): void
    {
        $west = Region::factory()->create(['country_id' => $this->country->id, 'slug' => 'west', 'name' => ['en' => 'West']]);
        Region::factory()->create(['country_id' => $this->country->id, 'slug' => 'east', 'name' => ['en' => 'East']]);
        City::factory()->inRegion($west)->create(['slug' => 'port-town', 'name' => ['en' => 'Port Town']]);

        $before = $this->get($this->carsUrl())->getContent();
        $this->assertStringContainsString('<option value="west"', $before);
        $this->assertStringNotContainsString('Port Town', $before);

        $after = $this->get($this->carsUrl(['region' => 'west']))->getContent();
        $this->assertStringContainsString('<option value="port-town"', $after);
        $this->assertStringContainsString('data-clears="subregion,city"', $after);
        $this->assertStringContainsString('Remove filter: West', $after);
    }

    public function test_country_prices_use_its_currency_and_global_prices_need_a_currency(): void
    {
        $country = $this->get($this->carsUrl())->getContent();
        $this->assertStringContainsString(__('search.prices_in', ['currency' => $this->country->default_currency]), $country);

        $global = $this->get(route('ads.global'))->assertOk()->getContent();
        $this->assertStringContainsString('data-price-currency', $global);
        $this->assertMatchesRegularExpression('/name="min_price"[^>]*disabled/', $global);
        $this->assertStringContainsString('<option value="'.$this->country->default_currency.'"', $global);

        $chosen = $this->get(route('ads.global', ['currency' => $this->country->default_currency]))->getContent();
        $this->assertDoesNotMatchRegularExpression('/name="min_price"[^>]*disabled/', $chosen);

        // Asking for a price without a currency explains why it was ignored.
        $this->get(route('ads.global', ['min_price' => 100]))->assertSee(__('public.price_needs_currency'));
    }

    public function test_category_filters_render_as_checkboxes_ranges_and_dependent_lists(): void
    {
        $make = Attribute::factory()->select(['ford', 'hino'])->create(['key' => 'make', 'label' => ['en' => 'Make']]);
        $model = Attribute::factory()->create(['key' => 'model', 'label' => ['en' => 'Model'], 'type' => AttributeType::Select, 'depends_on_attribute_id' => $make->id]);
        AttributeOption::create(['attribute_id' => $model->id, 'parent_option_id' => $make->options()->where('value', 'ford')->value('id'), 'value' => 'ranger', 'label' => ['en' => 'Ranger']]);
        $year = Attribute::factory()->ofType(AttributeType::Year)->create(['key' => 'year', 'label' => ['en' => 'Year']]);
        foreach ([$make, $model, $year] as $attribute) {
            $this->cars->attributeDefinitions()->attach($attribute->id, ['is_filterable' => true]);
        }

        $html = $this->get($this->carsUrl())->getContent();
        $this->assertStringContainsString('name="a[make][]"', $html);
        $this->assertStringContainsString('name="a[year][min]"', $html);
        $this->assertStringNotContainsString('Ranger', $html); // models appear once a make is chosen

        $withMake = $this->get($this->carsUrl(['a' => ['make' => ['ford']]]))->getContent();
        $this->assertStringContainsString('Ranger', $withMake);
        $this->assertStringContainsString('Remove filter: Make: Ford', $withMake);
    }

    public function test_list_view_uses_row_cards_with_key_facts(): void
    {
        $mileage = Attribute::factory()->ofType(AttributeType::Number)->create(['key' => 'mileage']);
        $this->cars->attributeDefinitions()->attach($mileage->id, ['show_on_card' => true]);
        $this->ad(['title' => 'Row car', 'attributes_summary' => ['mileage' => 85000]]);

        $html = $this->get($this->carsUrl(['view' => 'list']))->getContent();

        $this->assertStringContainsString('w-32 sm:w-40', $html);       // row variant photo column
        $this->assertStringContainsString('85000', $html);              // key fact on the card
        $this->assertMatchesRegularExpression('/aria-label="'.__('search.view_list').'"\s+aria-current="true"/', $html);
    }

    public function test_empty_results_offer_to_clear_filters(): void
    {
        $html = $this->get($this->carsUrl(['type' => 'wanted']))->assertOk()->getContent();

        $this->assertStringContainsString(__('search.empty_title'), $html);
        $this->assertStringContainsString(__('search.empty_filters'), $html);
    }

    public function test_sort_links_and_pagination_stop_at_the_browsable_limit(): void
    {
        config(['marketplace.listings.per_page' => 1, 'marketplace.listings.max_page' => 2]);
        foreach (range(1, 4) as $i) {
            $this->ad();
        }

        $page2 = $this->get($this->carsUrl(['page' => 2]))->assertOk()->getContent();
        $this->assertStringContainsString(e($this->carsUrl(['sort' => 'price_asc'])), $page2);
        $this->assertStringContainsString(__('ui.page_of', ['page' => 2, 'last' => 2]), $page2);
        $this->assertStringNotContainsString('rel="next"', $page2);
    }

    public function test_global_search_keeps_the_category_in_the_query(): void
    {
        Currency::query()->update(['is_active' => true]);

        $html = $this->get(route('ads.global', ['category' => 'cars', 'q' => 'van']))->assertOk()->getContent();

        $this->assertStringContainsString('<input type="hidden" name="category" value="cars">', $html);
        $this->assertStringContainsString('aria-label="Remove filter: “van”"', $html);
        $this->assertStringNotContainsString('Remove filter: Cars', $html); // the category is the page, not a chip
    }
}
