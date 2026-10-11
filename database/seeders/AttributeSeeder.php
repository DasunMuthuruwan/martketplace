<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Services\Catalogue\CatalogueCache;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Seeds attributes, options and category links (database/data/attributes.php). Run after
 * CategorySeeder and MeasurementUnitSeeder. Create-only: existing attributes (by key) and options
 * (by attribute + parent + value) are left as they are, and a category's links are only seeded
 * while it has none, so admin edits (including removed links) survive a re-run.
 */
class AttributeSeeder extends Seeder
{
    /** Flags allowed in a link definition. */
    private const FLAGS = ['required', 'card', 'filter'];

    public function run(): void
    {
        /** @var array{attributes: array<string, array<string, mixed>>, links: array<string, list<array<int|string, mixed>>>, unit_price: array<string, string>} $data */
        $data = require database_path('data/attributes.php');

        $attributes = [];
        // Independent attributes first, so dependent ones can reference them.
        $definitions = $data['attributes'];
        uksort($definitions, fn (string $a, string $b) => isset($definitions[$a]['depends_on']) <=> isset($definitions[$b]['depends_on']));

        foreach ($definitions as $key => $definition) {
            $attributes[$key] = $this->attribute($key, $definition, $attributes);
            $this->options($attributes[$key], $definition, $attributes);
        }

        foreach ($data['links'] as $slug => $links) {
            $category = Category::where('slug', $slug)->first();
            if ($category === null) {
                continue; // renamed or removed by an admin
            }
            $this->link($category, $links, $attributes);
        }

        foreach ($data['unit_price'] as $slug => $key) {
            Category::where('slug', $slug)->whereNull('unit_price_attribute_id')->update(['unit_price_attribute_id' => $attributes[$key]->id]);
        }

        // DatabaseSeeder mutes model events.
        CatalogueCache::flush();
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, Attribute>  $attributes
     */
    private function attribute(string $key, array $definition, array $attributes): Attribute
    {
        $dependsOn = $definition['depends_on'] ?? null;

        return Attribute::firstOrCreate(['key' => $key], [
            'label' => ['en' => $definition['label']],
            'type' => $definition['type'],
            'unit' => $definition['unit'] ?? null,
            'dimension' => $definition['dimension'] ?? null,
            'rules' => $definition['rules'] ?? null,
            'allows_other' => $definition['other'] ?? false,
            'help_text' => isset($definition['help']) ? ['en' => $definition['help']] : null,
            'depends_on_attribute_id' => $dependsOn === null ? null : ($attributes[$dependsOn] ?? throw new RuntimeException("Unknown parent attribute [{$dependsOn}]."))->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, Attribute>  $attributes
     */
    private function options(Attribute $attribute, array $definition, array $attributes): void
    {
        $options = $definition['options'] ?? [];
        $dependsOn = $definition['depends_on'] ?? null;

        if ($dependsOn === null) {
            $this->createOptions($attribute, $options, null);

            return;
        }

        foreach ($options as $parentValue => $children) {
            $parentId = AttributeOption::where('attribute_id', $attributes[$dependsOn]->id)->whereNull('parent_option_id')
                ->where('value', $parentValue)->value('id')
                ?? throw new RuntimeException("Unknown option [{$parentValue}] of [{$dependsOn}] for [{$attribute->key}].");
            $this->createOptions($attribute, $children, $parentId);
        }
    }

    /** @param  list<string|array<int|string, mixed>>  $options */
    private function createOptions(Attribute $attribute, array $options, ?int $parentId): void
    {
        foreach ($options as $order => $option) {
            $label = is_array($option) ? $option[0] : $option;

            AttributeOption::firstOrCreate(
                ['attribute_id' => $attribute->id, 'parent_option_id' => $parentId, 'value' => Str::slug($label)],
                ['label' => ['en' => $label], 'sort_order' => $order + 1, 'scope_countries' => is_array($option) ? ($option['scope'] ?? null) : null],
            );
        }
    }

    /**
     * @param  list<array<int|string, mixed>>  $links
     * @param  array<string, Attribute>  $attributes
     */
    private function link(Category $category, array $links, array $attributes): void
    {
        // A category that already has links is managed by admins: removed links must stay removed.
        if ($category->attributeDefinitions()->exists()) {
            return;
        }

        foreach ($links as $order => $link) {
            $key = $link[0];
            $attribute = $attributes[$key] ?? throw new RuntimeException("Unknown attribute [{$key}] linked to [{$category->slug}].");

            $flags = array_filter($link, fn ($value, $index) => is_int($index) && $index > 0, ARRAY_FILTER_USE_BOTH);
            if (($unknown = array_diff($flags, self::FLAGS)) !== []) {
                throw new RuntimeException('Unknown link flag(s) '.implode(', ', $unknown)." for [{$key}].");
            }

            $category->attributeDefinitions()->attach($attribute->id, [
                'is_required' => in_array('required', $flags, true),
                'required_for_conditions' => $link['required_for'] ?? null,
                'is_filterable' => in_array('filter', $flags, true),
                'show_on_card' => in_array('card', $flags, true),
                'sort_order' => $order + 1,
                'scope_countries' => $link['scope'] ?? null,
            ]);
        }
    }
}
