<?php

namespace Tests\Feature\Catalogue;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Services\Attributes\AttributeSchema;
use App\Services\Attributes\CategorySchema;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AttributeSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function schema(string $slug, string $iso2): CategorySchema
    {
        return app(AttributeSchema::class)->for(Category::where('slug', $slug)->firstOrFail(), $iso2);
    }

    private function passes(CategorySchema $schema, array $attributes, array $extra = []): bool
    {
        return Validator::make(['attributes' => $attributes] + $extra, $schema->rules())->passes();
    }

    public function test_every_leaf_category_has_a_link_entry_and_the_data_is_consistent(): void
    {
        $data = require database_path('data/attributes.php');
        $leaves = Category::whereNotNull('parent_id')->pluck('slug')->all();

        $this->assertEqualsCanonicalizing($leaves, array_keys($data['links']), 'Links cover exactly the 48 leaves');
        $this->assertSame(count($data['attributes']), Attribute::count());
        $this->assertGreaterThan(700, AttributeOption::count());

        $linked = DB::table('category_attribute')->distinct()->pluck('attribute_id')->all();
        $this->assertEqualsCanonicalizing(Attribute::pluck('id')->all(), $linked, 'No attribute is left unused');
    }

    public function test_a_typical_car_listing_validates(): void
    {
        $cars = $this->schema('cars', 'LK');

        $valid = [
            'make' => 'toyota', 'model' => 'aqua', 'year_of_manufacture' => '2015', 'year_of_registration' => '2016',
            'mileage' => '82000', 'fuel_type' => 'hybrid', 'transmission' => 'automatic', 'engine_capacity' => '1500',
            'body_type' => 'hatchback', 'colour' => 'silver',
        ];
        $this->assertTrue($this->passes($cars, $valid, ['condition' => 'used']));
        $this->assertFalse($this->passes($cars, ['model' => 'vezel'] + $valid), 'Honda model under Toyota');
        $this->assertFalse($this->passes($cars, array_diff_key($valid, ['mileage' => 1]), ['condition' => 'used']), 'Mileage needed when used');
        $this->assertTrue($this->passes($cars, array_diff_key($valid, ['mileage' => 1]), ['condition' => 'new']));
        $this->assertTrue($this->passes($cars, ['make' => 'other', 'make__other' => 'Zotye', 'model' => 'other', 'model__other' => 'T600'] + $valid));
        $this->assertSame(['make', 'model', 'year_of_manufacture', 'mileage', 'fuel_type', 'transmission'], $cars->cardKeys());
    }

    public function test_country_scoped_options_and_attributes(): void
    {
        $makes = fn (string $iso2) => array_column($this->schema('cars', $iso2)->field('make')['options'], 'value');
        $this->assertContains('micro', $makes('LK'));
        $this->assertNotContains('micro', $makes('GB'));

        $this->assertContains('deed_type', $this->schema('land', 'LK')->keys());
        $this->assertNotContains('deed_type', $this->schema('land', 'GB')->keys());
        $this->assertContains('tenure', $this->schema('land', 'GB')->keys());

        $landTypes = fn (string $iso2) => array_column($this->schema('land', $iso2)->field('land_type')['options'], 'value');
        $this->assertContains('tea', $landTypes('LK'));
        $this->assertNotContains('tea', $landTypes('US'));
    }

    public function test_land_and_floor_area_units_per_country(): void
    {
        $land = Category::where('slug', 'land')->firstOrFail();
        $this->assertSame(Attribute::where('key', 'land_size')->value('id'), $land->unit_price_attribute_id);

        $units = fn (string $slug, string $iso2, string $key) => array_column($this->schema($slug, $iso2)->field($key)['units'], 'code');
        $this->assertSame(['perch', 'rood', 'acre', 'hectare'], $units('land', 'LK', 'land_size'));
        $this->assertSame(['sqft', 'sqm'], $units('houses', 'LK', 'floor_area'), 'Floors are not measured in perches');
        $this->assertSame(['sqft', 'sqm'], $units('houses', 'GB', 'floor_area'));
        $this->assertSame(['acre', 'hectare', 'sqft', 'sqm'], $units('land', 'GB', 'land_size'));

        $this->assertTrue($this->passes($this->schema('land', 'LK'), ['land_size' => '50', 'land_size__unit' => 'perch', 'land_type' => ['residential']]));
        $this->assertFalse($this->passes($this->schema('houses', 'LK'), ['bedrooms' => '3', 'floor_area' => '1800', 'floor_area__unit' => 'perch']));
    }

    public function test_phones_and_three_wheelers(): void
    {
        $this->assertTrue($this->passes($this->schema('mobile-phones', 'US'), ['phone_brand' => 'apple', 'phone_model' => 'iphone-15-pro', 'storage' => '256-gb']));
        $this->assertFalse($this->passes($this->schema('mobile-phones', 'US'), ['phone_brand' => 'samsung', 'phone_model' => 'iphone-15-pro']));
        $this->assertTrue($this->passes($this->schema('three-wheelers', 'LK'), [
            'three_wheeler_make' => 'bajaj', 'three_wheeler_model' => 're-4-stroke', 'year_of_manufacture' => '2018', 'fuel_type' => 'petrol',
        ]));
    }

    public function test_reseeding_keeps_admin_edits_and_adds_nothing_twice(): void
    {
        $counts = [Attribute::count(), AttributeOption::count(), DB::table('category_attribute')->count()];
        Attribute::where('key', 'make')->firstOrFail()->update(['label' => ['en' => 'Manufacturer']]);
        AttributeOption::where('value', 'tiptronic')->firstOrFail()->update(['is_active' => false]);
        $cars = Category::where('slug', 'cars')->firstOrFail();
        $cars->attributeDefinitions()->detach(Attribute::where('key', 'trim_edition')->value('id'));

        $this->seed(DatabaseSeeder::class);

        $this->assertSame('Manufacturer', Attribute::where('key', 'make')->firstOrFail()->label);
        $this->assertFalse(AttributeOption::where('value', 'tiptronic')->firstOrFail()->is_active);
        $this->assertSame([$counts[0], $counts[1]], [Attribute::count(), AttributeOption::count()]);
        $this->assertSame($counts[2] - 1, DB::table('category_attribute')->count(), 'A link removed by an admin stays removed');
    }
}
