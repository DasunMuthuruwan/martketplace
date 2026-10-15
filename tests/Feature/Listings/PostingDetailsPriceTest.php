<?php

namespace Tests\Feature\Listings;

use App\Enums\AttributeType;
use App\Enums\ListingType;
use App\Enums\PriceType;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Listing;
use App\Models\Region;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** F4.1 part 2: details and price steps (layout doc §3.4, global UX §3). */
class PostingDetailsPriceTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private Country $country;

    private Category $cars;

    private Listing $draft;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->create();
        $currency = Currency::factory()->create(['minor_units' => 2]);
        $this->country = Country::factory()->create(['default_currency' => $currency->code, 'number_locale' => 'en', 'region_levels' => 1]);
        $root = Category::factory()->create();
        $this->cars = Category::factory()->create(['parent_id' => $root->id, 'allowed_types' => ['sale', 'rent', 'wanted'], 'show_condition' => true]);
        $region = Region::factory()->create(['country_id' => $this->country->id]);
        $city = City::factory()->inRegion($region)->create();
        $this->draft = Listing::factory()->inCountry($this->country)->for($this->seller)->draft()->create([
            'category_id' => $this->cars->id, 'region_id' => $region->id, 'city_id' => $city->id,
            'title' => null, 'description' => null, 'type' => null, 'condition' => null, 'price' => null, 'price_type' => null,
        ]);
    }

    private function details(array $overrides = []): array
    {
        return $overrides + ['title' => 'Toyota Aqua 2015 hybrid', 'description' => 'One owner, full service history, very clean.', 'type' => 'sale', 'condition' => 'used'];
    }

    public function test_details_step_renders_type_condition_and_schema_fields(): void
    {
        $make = Attribute::factory()->select(['toyota', 'honda'])->create(['key' => 'make', 'label' => ['en' => 'Make']]);
        $model = Attribute::factory()->create(['key' => 'model', 'label' => ['en' => 'Model'], 'type' => AttributeType::Select, 'depends_on_attribute_id' => $make->id]);
        AttributeOption::create(['attribute_id' => $model->id, 'parent_option_id' => $make->options()->where('value', 'toyota')->value('id'), 'value' => 'aqua', 'label' => ['en' => 'Aqua']]);
        $mileage = Attribute::factory()->ofType(AttributeType::Number)->create(['key' => 'mileage', 'label' => ['en' => 'Mileage'], 'unit' => 'km']);
        $ac = Attribute::factory()->ofType(AttributeType::Boolean)->create(['key' => 'ac', 'label' => ['en' => 'Air conditioning']]);
        foreach ([$make, $model, $mileage, $ac] as $i => $attribute) {
            $this->cars->attributeDefinitions()->attach($attribute->id, ['sort_order' => $i, 'is_required' => $attribute->is($make)]);
        }

        $html = $this->actingAs($this->seller)->get(route('listings.edit', [$this->draft, 'details']))->assertOk()->getContent();

        $this->assertStringContainsString('name="type" value="rent"', $html);
        $this->assertStringContainsString('name="condition" value="used"', $html);
        $this->assertStringContainsString('name="attributes[make]"', $html);
        $this->assertStringContainsString('data-depends-on="attributes[make]"', $html);
        $this->assertStringContainsString('<optgroup label="Toyota" data-parent-value="toyota">', $html);
        $this->assertStringContainsString('>km</span>', $html);
        $this->assertStringContainsString('role="switch"', $html);
        $this->assertStringContainsString('<input type="hidden" name="attributes[ac]" value="0">', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_details_save_and_show_again_including_attribute_values(): void
    {
        $make = Attribute::factory()->select(['toyota', 'honda'], allowsOther: true)->create(['key' => 'make', 'label' => ['en' => 'Make']]);
        $this->cars->attributeDefinitions()->attach($make->id);

        $this->actingAs($this->seller)->post(route('listings.update', [$this->draft, 'details']), $this->details(['attributes' => ['make' => 'other', 'make__other' => 'Tata']]))
            ->assertRedirect(route('listings.edit', [$this->draft, 'price']));

        $listing = $this->draft->fresh();
        $this->assertSame(['Toyota Aqua 2015 hybrid', ListingType::Sale], [$listing->title, $listing->type]);

        $html = $this->get(route('listings.edit', [$listing, 'details']))->getContent();
        $this->assertStringContainsString('value="Toyota Aqua 2015 hybrid"', $html);
        $this->assertMatchesRegularExpression('/<option value="other"\s+selected/', $html);
        $this->assertStringContainsString('value="Tata"', $html);
    }

    public function test_details_errors_return_to_the_step_with_the_input(): void
    {
        $html = $this->actingAs($this->seller)->from(route('listings.edit', [$this->draft, 'details']))
            ->followingRedirects()->post(route('listings.update', [$this->draft, 'details']), $this->details(['title' => 'x', 'description' => '<b>bold</b> text that is long enough to pass']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('data-error-summary', $html);
        $this->assertStringContainsString('id="f-title-error"', $html);
        $this->assertNull($this->draft->fresh()->title);
    }

    public function test_price_step_shows_the_country_currency_and_saves_a_grouped_amount(): void
    {
        $this->draft->forceFill($this->details(['type' => ListingType::Sale]))->save();

        $html = $this->actingAs($this->seller)->get(route('listings.edit', [$this->draft, 'price']))->assertOk()->getContent();
        $this->assertStringContainsString('>'.$this->country->default_currency.'</span>', $html); // code as the prefix
        $this->assertStringContainsString('name="price_type" value="negotiable"', $html);
        $this->assertStringNotContainsString('name="rent_period"', $html);

        $this->post(route('listings.update', [$this->draft, 'price']), ['price_type' => 'negotiable', 'price' => '1,250,000.50'])
            ->assertRedirect(route('listings.edit', [$this->draft, 'photos']));

        $listing = $this->draft->fresh();
        $this->assertSame(PriceType::Negotiable, $listing->price_type);
        $this->assertSame('1250000.5', Decimal::trim((string) $listing->price)); // grouping removed, decimals kept
        $this->get(route('listings.edit', [$listing, 'price']))->assertSee('value="1,250,000.5"', false); // shown grouped again
    }

    public function test_amounts_follow_the_countrys_number_format(): void
    {
        $this->country->update(['number_locale' => 'de']); // "." groups, "," is the decimal sign
        $this->draft->forceFill($this->details(['type' => ListingType::Sale]))->save();

        $this->actingAs($this->seller)->post(route('listings.update', [$this->draft, 'price']), ['price_type' => 'fixed', 'price' => '1.250.000,50'])->assertRedirect();

        $this->assertSame('1250000.5', Decimal::trim((string) $this->draft->fresh()->price));
    }

    public function test_rent_ads_need_a_period_and_too_many_decimals_are_refused(): void
    {
        $this->draft->forceFill($this->details(['type' => ListingType::Rent]))->save();

        $html = $this->actingAs($this->seller)->get(route('listings.edit', [$this->draft, 'price']))->getContent();
        $this->assertStringContainsString('name="rent_period"', $html);

        $this->from(route('listings.edit', [$this->draft, 'price']))
            ->post(route('listings.update', [$this->draft, 'price']), ['price_type' => 'fixed', 'price' => '85000.555'])
            ->assertSessionHasErrors(['price', 'rent_period']);
        $this->assertNull($this->draft->fresh()->price);
    }

    public function test_free_items_save_without_an_amount(): void
    {
        $this->cars->update(['allow_free' => true]);
        $this->draft->forceFill($this->details(['type' => ListingType::Sale]))->save();

        $this->actingAs($this->seller)->post(route('listings.update', [$this->draft, 'price']), ['price_type' => 'free', 'price' => ''])->assertRedirect();

        $this->assertSame([PriceType::Free, null], [$this->draft->fresh()->price_type, $this->draft->fresh()->price]);
    }

    public function test_price_step_needs_details_first(): void
    {
        $this->actingAs($this->seller)->get(route('listings.edit', [$this->draft, 'price']))
            ->assertRedirect(route('listings.edit', [$this->draft, 'details']));
    }
}
