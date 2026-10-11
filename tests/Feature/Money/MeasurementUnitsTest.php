<?php

namespace Tests\Feature\Money;

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Country;
use App\Models\MeasurementUnit;
use App\Services\Attributes\AttributeSchema;
use App\Services\Attributes\CategorySchema;
use App\Services\Money\AreaUnits;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\MeasurementUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MeasurementUnitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CurrencySeeder::class, CountrySeeder::class, MeasurementUnitSeeder::class]);
    }

    private function country(string $iso2): Country
    {
        return Country::where('iso2', $iso2)->firstOrFail();
    }

    /** @return list<string> */
    private function unitCodes(string $iso2): array
    {
        return app(AreaUnits::class)->forCountry($this->country($iso2))->pluck('code')->all();
    }

    // ---- Seed data and AreaUnits ------------------------------------------------------------

    public function test_seeds_units_and_the_g6_country_defaults(): void
    {
        $this->assertSame(8, MeasurementUnit::count());
        $this->assertSame('25.2928526400', MeasurementUnit::where('code', 'perch')->value('factor_to_base'));

        $this->assertSame(['perch', 'rood', 'acre', 'hectare'], $this->unitCodes('LK'));
        $this->assertSame(['sqft', 'sqm', 'acre', 'hectare', 'cent'], $this->unitCodes('IN'));
        $this->assertSame(['acre', 'hectare', 'sqft', 'sqm'], $this->unitCodes('GB'));
        $this->assertSame(['acre', 'sqft'], $this->unitCodes('US'));

        $this->assertSame('perch', app(AreaUnits::class)->landPriceUnit($this->country('LK'))?->code);
        $this->assertSame('acre', app(AreaUnits::class)->landPriceUnit($this->country('GB'))?->code);
    }

    public function test_countries_without_units_get_the_metric_fallback_and_no_price_per_unit(): void
    {
        $france = $this->country('FR');

        $this->assertSame(['sqm', 'hectare'], $this->unitCodes('FR'));
        $this->assertSame('sqm', app(AreaUnits::class)->defaultFor($france)?->code);
        $this->assertNull(app(AreaUnits::class)->landPriceUnit($france));
        $this->assertSame(['sqm', 'hectare'], app(AreaUnits::class)->forCountry(null)->pluck('code')->all());
    }

    public function test_the_default_unit_comes_first_and_inactive_units_are_not_offered(): void
    {
        $lk = $this->country('LK');
        $acre = MeasurementUnit::where('code', 'acre')->firstOrFail();
        $lk->areaUnits()->updateExistingPivot(MeasurementUnit::where('code', 'perch')->value('id'), ['is_default' => false]);
        $lk->areaUnits()->updateExistingPivot($acre->id, ['is_default' => true]);
        MeasurementUnit::where('code', 'rood')->firstOrFail()->update(['is_active' => false]);

        $this->assertSame(['acre', 'perch', 'hectare'], $this->unitCodes('LK'));

        // A deactivated price-per unit hides the per-unit price.
        MeasurementUnit::where('code', 'perch')->firstOrFail()->update(['is_active' => false]);
        $this->assertNull(app(AreaUnits::class)->landPriceUnit($lk->fresh()));
    }

    public function test_reseeding_keeps_admin_changes(): void
    {
        $lk = $this->country('LK');
        $lk->areaUnits()->detach(MeasurementUnit::where('code', 'hectare')->value('id'));
        $lk->forceFill(['land_price_unit_id' => MeasurementUnit::where('code', 'acre')->value('id')])->save();
        MeasurementUnit::where('code', 'perch')->firstOrFail()->update(['symbol' => 'p.']);

        $this->seed(MeasurementUnitSeeder::class);

        $this->assertSame(['perch', 'rood', 'acre'], $this->unitCodes('LK'));
        $this->assertSame('acre', $lk->fresh()->landPriceUnit?->code);
        $this->assertSame('p.', MeasurementUnit::where('code', 'perch')->value('symbol'));
        $this->assertSame(8, MeasurementUnit::count());
    }

    // ---- Area attributes in the schema -----------------------------------------------------

    private ?Category $land = null;

    /** A Land category with a required area attribute (created once per test). */
    private function landSchema(string $iso2): CategorySchema
    {
        if ($this->land === null) {
            $this->land = Category::factory()->childOf(Category::factory()->create())->create();
            $landSize = Attribute::factory()->ofType(AttributeType::Number, ['max' => 1000000])->create(['key' => 'land_size', 'dimension' => MeasurementUnit::AREA]);
            $this->land->attributeDefinitions()->attach($landSize->id, ['is_required' => true]);
        }

        return app(AttributeSchema::class)->for($this->land, $iso2);
    }

    private function passes(CategorySchema $schema, array $attributes): bool
    {
        return Validator::make(['attributes' => $attributes], $schema->rules())->passes();
    }

    public function test_area_field_offers_the_countrys_units(): void
    {
        $lk = $this->landSchema('LK');
        $form = $lk->form()[0];

        $this->assertSame('area', $form['dimension']);
        $this->assertSame(['perch', 'rood', 'acre', 'hectare'], array_column($form['units'], 'code'));
        $this->assertSame('perch', $form['default_unit']);
        $this->assertSame(['acre', 'hectare', 'sqft', 'sqm'], array_column($this->landSchema('GB')->form()[0]['units'], 'code'));
    }

    public function test_area_value_and_unit_are_validated(): void
    {
        $lk = $this->landSchema('LK');

        $this->assertTrue($this->passes($lk, ['land_size' => '50', 'land_size__unit' => 'perch']));
        $this->assertTrue($this->passes($lk, ['land_size' => '1.25', 'land_size__unit' => 'rood']));
        $this->assertFalse($this->passes($lk, ['land_size' => '50']), 'Unit required with a value');
        $this->assertFalse($this->passes($lk, ['land_size' => '50', 'land_size__unit' => 'sqft']), 'Unit not offered in this country');
        $this->assertFalse($this->passes($lk, ['land_size' => '0', 'land_size__unit' => 'perch']), 'Zero area');
        $this->assertFalse($this->passes($lk, ['land_size' => '-5', 'land_size__unit' => 'perch']), 'Negative area');
        $this->assertFalse($this->passes($lk, ['land_size' => 'fifty', 'land_size__unit' => 'perch']));
        $this->assertFalse($this->passes($lk, []), 'Required for Land');

        $this->assertTrue($this->passes($this->landSchema('US'), ['land_size' => '0.5', 'land_size__unit' => 'acre']));
    }

    public function test_unit_changes_refresh_cached_schemas(): void
    {
        $schema = $this->landSchema('LK');
        $this->assertContains('rood', array_column($schema->form()[0]['units'], 'code'));

        MeasurementUnit::where('code', 'rood')->firstOrFail()->update(['is_active' => false]);

        $this->assertNotContains('rood', array_column($this->landSchema('LK')->form()[0]['units'], 'code'));
    }

    public function test_only_number_attributes_can_be_areas(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Attribute::factory()->create(['type' => AttributeType::Text, 'dimension' => MeasurementUnit::AREA]);
    }
}
