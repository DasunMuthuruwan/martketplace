<?php

namespace Tests\Feature\Catalogue;

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Services\Attributes\AttributeSchema;
use App\Services\Attributes\CategorySchema;
use App\Services\Catalogue\CatalogueCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AttributeSchemaTest extends TestCase
{
    use RefreshDatabase;

    private Category $vehicles;

    private Category $cars;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vehicles = Category::factory()->create(['slug' => 'vehicles']);
        $this->cars = Category::factory()->childOf($this->vehicles)->create(['slug' => 'cars']);
    }

    private function attach(Category $category, Attribute $attribute, array $pivot = []): void
    {
        $category->attributeDefinitions()->attach($attribute->id, $pivot);
    }

    private function schema(string $iso2 = 'LK', ?Category $category = null): CategorySchema
    {
        return app(AttributeSchema::class)->for($category ?? $this->cars, $iso2);
    }

    /** @param  array<string, mixed>  $attributes */
    private function passes(CategorySchema $schema, array $attributes, array $extra = []): bool
    {
        return Validator::make(['attributes' => $attributes] + $extra, $schema->rules())->passes();
    }

    /** @return array{make: Attribute, model: Attribute} */
    private function makeAndModel(bool $modelAllowsOther = true): array
    {
        $make = Attribute::factory()->select(['toyota', 'honda'], allowsOther: true)->create(['key' => 'make']);
        $model = Attribute::factory()->create([
            'key' => 'model', 'type' => AttributeType::Select, 'depends_on_attribute_id' => $make->id, 'allows_other' => $modelAllowsOther,
        ]);
        $toyota = $make->options()->where('value', 'toyota')->firstOrFail();
        $honda = $make->options()->where('value', 'honda')->firstOrFail();
        AttributeOption::create(['attribute_id' => $model->id, 'parent_option_id' => $toyota->id, 'value' => 'aqua', 'label' => ['en' => 'Aqua']]);
        AttributeOption::create(['attribute_id' => $model->id, 'parent_option_id' => $honda->id, 'value' => 'vezel', 'label' => ['en' => 'Vezel']]);
        $this->attach($this->vehicles, $make, ['is_required' => true, 'sort_order' => 1]);
        $this->attach($this->vehicles, $model, ['is_required' => true, 'sort_order' => 2]);

        return ['make' => $make, 'model' => $model];
    }

    // ---- Merging, order and scope -------------------------------------------------------------

    public function test_leaf_inherits_parent_attributes_and_overrides_its_own_link(): void
    {
        $colour = Attribute::factory()->create(['key' => 'colour']);
        $mileage = Attribute::factory()->ofType(AttributeType::Number)->create(['key' => 'mileage']);
        $trim = Attribute::factory()->create(['key' => 'trim']);
        $this->attach($this->vehicles, $colour, ['sort_order' => 5]);
        $this->attach($this->vehicles, $mileage, ['sort_order' => 1, 'is_required' => false]);
        $this->attach($this->cars, $mileage, ['sort_order' => 1, 'is_required' => true, 'show_on_card' => true]);
        $this->attach($this->cars, $trim, ['sort_order' => 1]);

        $schema = $this->schema();

        $this->assertSame(['mileage', 'trim', 'colour'], $schema->keys());
        $this->assertTrue($schema->field('mileage')['required'], 'Leaf link overrides the parent link');
        $this->assertSame(['mileage'], $schema->cardKeys());

        // The parent category itself only has its own attributes.
        $this->assertSame(['mileage', 'colour'], $this->schema('LK', $this->vehicles)->keys());
    }

    public function test_inherited_fields_come_before_the_leafs_own_at_the_same_position(): void
    {
        $own = Attribute::factory()->create(['key' => 'own_field']);
        $inherited = Attribute::factory()->create(['key' => 'inherited_field']);
        $this->attach($this->cars, $own, ['sort_order' => 1]);
        $this->attach($this->vehicles, $inherited, ['sort_order' => 1]);

        $this->assertSame(['inherited_field', 'own_field'], $this->schema()->keys());
    }

    public function test_inactive_and_out_of_scope_attributes_are_left_out(): void
    {
        $this->attach($this->cars, Attribute::factory()->inactive()->create(['key' => 'retired']));
        $this->attach($this->cars, Attribute::factory()->create(['key' => 'deed_type']), ['scope_countries' => ['lk']]);
        $this->attach($this->cars, Attribute::factory()->create(['key' => 'tenure']), ['scope_countries' => ['GB', 'US']]);

        $this->assertSame(['deed_type'], $this->schema('LK')->keys());
        $this->assertSame(['tenure'], $this->schema('us')->keys());
    }

    public function test_option_scope_and_activity_filter_the_choices(): void
    {
        $deed = Attribute::factory()->select(['freehold'])->create(['key' => 'ownership']);
        AttributeOption::create(['attribute_id' => $deed->id, 'value' => 'local-deed', 'label' => ['en' => 'Local deed'], 'scope_countries' => ['LK']]);
        AttributeOption::create(['attribute_id' => $deed->id, 'value' => 'retired', 'label' => ['en' => 'Retired'], 'is_active' => false]);
        $this->attach($this->cars, $deed);

        $this->assertSame(['freehold', 'local-deed'], array_column($this->schema('LK')->field('ownership')['options'], 'value'));
        $this->assertSame(['freehold'], array_column($this->schema('GB')->field('ownership')['options'], 'value'));

        // Out-of-scope options are rejected, not just hidden.
        $this->assertTrue($this->passes($this->schema('LK'), ['ownership' => 'local-deed']));
        $this->assertFalse($this->passes($this->schema('GB'), ['ownership' => 'local-deed']));
        $this->assertFalse($this->passes($this->schema('LK'), ['ownership' => 'retired']));
    }

    // ---- Rules per type -------------------------------------------------------------------------

    /** @return array<string, array{AttributeType, array<string, mixed>|null, mixed, bool}> */
    public static function typeCases(): array
    {
        return [
            'text ok' => [AttributeType::Text, null, 'Limited edition', true],
            'text too long' => [AttributeType::Text, ['maxlength' => 5], 'Limited edition', false],
            'text not a string' => [AttributeType::Text, null, ['x'], false],
            'number ok' => [AttributeType::Number, ['min' => 0, 'max' => 1000000], '45000.5', true],
            'number below min' => [AttributeType::Number, ['min' => 0], '-1', false],
            'number above max' => [AttributeType::Number, ['max' => 10], '11', false],
            'number not numeric' => [AttributeType::Number, null, 'lots', false],
            'whole number step' => [AttributeType::Number, ['step' => 1], '2.5', false],
            'year ok' => [AttributeType::Year, ['min' => 1950, 'max_years_ahead' => 1], (string) (date('Y') + 1), true],
            'year too new' => [AttributeType::Year, ['min' => 1950, 'max_years_ahead' => 1], (string) (date('Y') + 2), false],
            'year too old' => [AttributeType::Year, ['min' => 1950], '1949', false],
            'boolean ok' => [AttributeType::Boolean, null, '1', true],
            'boolean invalid' => [AttributeType::Boolean, null, 'maybe', false],
        ];
    }

    #[DataProvider('typeCases')]
    public function test_rules_per_type(AttributeType $type, ?array $rules, mixed $value, bool $valid): void
    {
        $this->attach($this->cars, Attribute::factory()->ofType($type, $rules)->create(['key' => 'field']));

        $this->assertSame($valid, $this->passes($this->schema(), ['field' => $value]));
    }

    public function test_year_maximum_follows_the_calendar(): void
    {
        $this->attach($this->cars, Attribute::factory()->ofType(AttributeType::Year, ['max_years_ahead' => 1])->create(['key' => 'year']));
        $schema = $this->schema(); // built and cached now
        $nextYearPlusOne = (string) (date('Y') + 2);

        $this->assertFalse($this->passes($schema, ['year' => $nextYearPlusOne]));

        $this->travel(1)->years();
        $this->assertTrue($this->passes($this->schema(), ['year' => $nextYearPlusOne]), 'Cached schema, fresh maximum');
    }

    public function test_select_and_multiselect(): void
    {
        $this->attach($this->cars, Attribute::factory()->select(['petrol', 'diesel'])->create(['key' => 'fuel']));
        $features = Attribute::factory()->select(['sunroof', 'leather'])->create(['key' => 'features', 'type' => AttributeType::MultiSelect]);
        $this->attach($this->cars, $features);
        $schema = $this->schema();

        $this->assertTrue($this->passes($schema, ['fuel' => 'diesel', 'features' => ['sunroof', 'leather']]));
        $this->assertFalse($this->passes($schema, ['fuel' => 'steam']));
        $this->assertFalse($this->passes($schema, ['features' => ['sunroof', 'jetpack']]));
        $this->assertFalse($this->passes($schema, ['features' => ['sunroof', 'sunroof']]));
        $this->assertFalse($this->passes($schema, ['features' => 'sunroof']));
    }

    public function test_other_needs_free_text_and_only_where_allowed(): void
    {
        $this->attach($this->cars, Attribute::factory()->select(['acme'], allowsOther: true)->create(['key' => 'brand']));
        $this->attach($this->cars, Attribute::factory()->select(['red'])->create(['key' => 'colour']));
        $schema = $this->schema();

        $this->assertTrue($this->passes($schema, ['brand' => 'other', 'brand__other' => 'Zentek']));
        $this->assertFalse($this->passes($schema, ['brand' => 'other']), 'Other without text');
        $this->assertFalse($this->passes($schema, ['brand' => 'acme', 'brand__other' => 'Zentek']), 'Text without Other');
        $this->assertFalse($this->passes($schema, ['colour' => 'other']), 'Other not allowed here');
        $this->assertFalse($this->passes($schema, ['colour__other' => 'Teal']), 'No such input');
    }

    public function test_required_and_condition_dependent_fields(): void
    {
        $this->attach($this->cars, Attribute::factory()->create(['key' => 'trim']), ['is_required' => true]);
        $this->attach($this->cars, Attribute::factory()->ofType(AttributeType::Number)->create(['key' => 'mileage']), ['required_for_conditions' => ['used', 'reconditioned']]);
        $schema = $this->schema();

        $this->assertFalse($this->passes($schema, []));
        $this->assertTrue($this->passes($schema, ['trim' => 'GL'], ['condition' => 'new']));
        $this->assertFalse($this->passes($schema, ['trim' => 'GL'], ['condition' => 'used']));
        $this->assertTrue($this->passes($schema, ['trim' => 'GL', 'mileage' => '82000'], ['condition' => 'used']));
    }

    public function test_unknown_attribute_keys_are_rejected(): void
    {
        $this->attach($this->cars, Attribute::factory()->create(['key' => 'trim']));

        $this->assertFalse($this->passes($this->schema(), ['trim' => 'GL', 'is_featured' => '1']));
    }

    // ---- Dependent selects ----------------------------------------------------------------------

    public function test_dependent_select_must_match_its_parent(): void
    {
        $this->makeAndModel();
        $schema = $this->schema();

        $this->assertSame('make', $schema->field('model')['depends_on']);
        $this->assertTrue($this->passes($schema, ['make' => 'toyota', 'model' => 'aqua']));
        $this->assertFalse($this->passes($schema, ['make' => 'toyota', 'model' => 'vezel']), 'Model of another make');
        $this->assertFalse($this->passes($schema, ['make' => 'other', 'make__other' => 'Micro', 'model' => 'aqua']), 'Known model under Other make');
        $this->assertTrue($this->passes($schema, ['make' => 'other', 'make__other' => 'Micro', 'model' => 'other', 'model__other' => 'Panda']));
        $this->assertTrue($this->passes($schema, ['make' => 'honda', 'model' => 'other', 'model__other' => 'Grace']));
    }

    public function test_dependent_options_disappear_with_their_parent_option(): void
    {
        ['make' => $make] = $this->makeAndModel();
        $make->options()->where('value', 'honda')->update(['is_active' => false]);
        CatalogueCache::flush(); // a raw query fires no model events

        $models = array_column($this->schema()->field('model')['options'], 'value');

        $this->assertSame(['aqua'], $models);
    }

    public function test_a_dependent_select_without_its_parent_is_left_out(): void
    {
        ['make' => $make, 'model' => $model] = $this->makeAndModel();
        $this->vehicles->attributeDefinitions()->detach($make->id);

        $this->assertSame([], $this->schema()->keys());
        $this->assertNotNull($model->fresh());
    }

    // ---- Form output and cache ------------------------------------------------------------------

    public function test_form_definition_uses_the_current_locale_with_fallback(): void
    {
        $fuel = Attribute::factory()->select(['petrol'])->create(['key' => 'fuel', 'label' => ['en' => 'Fuel type', 'si' => 'ඉන්ධන වර්ගය'], 'unit' => null]);
        $this->attach($this->cars, $fuel, ['is_required' => true, 'group' => 'Specs']);
        $this->attach($this->cars, Attribute::factory()->ofType(AttributeType::Number, ['min' => 0, 'max' => 9000])->create(['key' => 'engine', 'unit' => 'cc']));

        app()->setLocale('si');
        $form = $this->schema()->form();

        $this->assertSame('ඉන්ධන වර්ගය', $form[0]['label']);
        $this->assertSame('Petrol', $form[0]['options'][0]['label'], 'Falls back to English');
        $this->assertTrue($form[0]['required']);
        $this->assertSame('Specs', $form[0]['group']);
        $this->assertSame(['cc', 0, 9000], [$form[1]['unit'], $form[1]['min'], $form[1]['max']]);
        $this->assertSame('ඉන්ධන වර්ගය', $this->schema()->validationAttributes()['attributes.fuel']);
    }

    public function test_schema_is_cached_and_refreshed_after_admin_changes(): void
    {
        $fuel = Attribute::factory()->select(['petrol'])->create(['key' => 'fuel']);
        $this->attach($this->cars, $fuel);
        $this->assertSame(['petrol'], array_column($this->schema()->field('fuel')['options'], 'value'));

        AttributeOption::create(['attribute_id' => $fuel->id, 'value' => 'hybrid', 'label' => ['en' => 'Hybrid']]);
        $this->assertSame(['petrol', 'hybrid'], array_column($this->schema()->field('fuel')['options'], 'value'), 'New option');

        $this->cars->attributeDefinitions()->updateExistingPivot($fuel->id, ['is_required' => true]);
        $this->assertTrue($this->schema()->field('fuel')['required'], 'Link change');

        $fuel->update(['is_active' => false]);
        $this->assertSame([], $this->schema()->keys(), 'Attribute deactivated');
    }

    // ---- Model guards ---------------------------------------------------------------------------

    public function test_attribute_and_option_guards(): void
    {
        $guarded = [
            'key must be snake_case' => fn () => Attribute::factory()->create(['key' => 'Bad Key']),
            'only select depends on select' => fn () => Attribute::factory()->create([
                'type' => AttributeType::Text, 'depends_on_attribute_id' => Attribute::factory()->select()->create()->id,
            ]),
            '"other" is reserved' => fn () => AttributeOption::create(['attribute_id' => Attribute::factory()->select([])->create()->id, 'value' => 'other', 'label' => ['en' => 'Other']]),
            'dependent option needs a parent' => function () {
                ['model' => $model] = $this->makeAndModel();
                AttributeOption::create(['attribute_id' => $model->id, 'value' => 'orphan', 'label' => ['en' => 'Orphan']]);
            },
        ];

        foreach ($guarded as $case => $callback) {
            try {
                $callback();
                $this->fail("Not guarded: {$case}");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_option_value_is_slugged_from_its_label_when_missing(): void
    {
        $attribute = Attribute::factory()->select([])->create();

        $option = AttributeOption::create(['attribute_id' => $attribute->id, 'value' => '', 'label' => ['en' => 'Mercedes-Benz E Class']]);

        $this->assertSame('mercedes-benz-e-class', $option->value);
    }
}
