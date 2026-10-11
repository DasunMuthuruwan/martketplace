<?php

namespace App\Services\Attributes;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Country;
use App\Models\MeasurementUnit;
use App\Services\Catalogue\CatalogueCache;
use App\Services\Money\AreaUnits;
use App\Support\CountryScope;

/**
 * Builds the attribute schema of a leaf category for one country (architecture §5, routes doc
 * §3.1): the parent's attributes plus the leaf's own (the leaf's link wins), without inactive or
 * out-of-scope attributes and options. The same schema produces the form definition and the
 * validation rules (CategorySchema), so they cannot drift apart. Cached per category + country.
 */
class AttributeSchema
{
    public function __construct(private readonly AreaUnits $units) {}

    public function for(Category $category, string $iso2): CategorySchema
    {
        $iso2 = strtoupper($iso2);

        return new CategorySchema(
            CatalogueCache::remember("attribute-schema:{$category->id}:{$iso2}", fn () => $this->build($category, $iso2)),
        );
    }

    /**
     * @return list<array<string, mixed>> field definitions (plain arrays: safe to cache)
     */
    private function build(Category $category, string $iso2): array
    {
        /** @var array<int, array{attribute: Attribute, link: CategoryAttribute, inherited: bool}> $entries */
        $entries = [];
        foreach ([$category->parent, $category] as $owner) {
            if ($owner === null) {
                continue;
            }
            foreach ($owner->attributeDefinitions()->with('dependsOn')->get() as $attribute) {
                /** @var CategoryAttribute $link */
                $link = $attribute->getRelation('pivot');
                $entries[$attribute->id] = ['attribute' => $attribute, 'link' => $link, 'inherited' => ! $owner->is($category)];
            }
        }

        $entries = array_filter($entries, fn (array $entry) => $entry['attribute']->is_active
            && CountryScope::includes($entry['link']->scope_countries, $iso2));

        // Form order: sort_order, then inherited (parent) fields before the leaf's own, then id.
        $sortKey = fn (array $entry) => [$entry['link']->sort_order, $entry['inherited'] ? 0 : 1, $entry['attribute']->id];
        usort($entries, fn (array $a, array $b) => $sortKey($a) <=> $sortKey($b));

        $optionsByAttribute = $this->availableOptions(array_map(fn (array $entry) => $entry['attribute'], $entries), $iso2);
        $presentKeys = array_map(fn (array $entry) => $entry['attribute']->key, $entries);

        $fields = [];
        $areaUnits = null;
        foreach ($entries as $entry) {
            $attribute = $entry['attribute'];
            $dependsOn = $attribute->dependsOn?->key;

            // A dependent select is only usable together with the select it depends on.
            if ($dependsOn !== null && ! in_array($dependsOn, $presentKeys, true)) {
                continue;
            }

            $units = [];
            if ($attribute->dimension === MeasurementUnit::AREA) {
                $units = $this->unitsFor($attribute, $areaUnits ??= $this->areaUnits($iso2));
                if ($units === []) {
                    continue; // no unit to enter it in
                }
            }

            $fields[] = [
                'key' => $attribute->key,
                'type' => $attribute->type->value,
                'label' => $attribute->getTranslations('label'),
                'help' => $attribute->getTranslations('help_text'),
                'unit' => $attribute->unit,
                'dimension' => $attribute->dimension,
                'units' => $units,
                'rules' => $attribute->rules ?? [],
                'required' => $entry['link']->is_required,
                'required_for_conditions' => $entry['link']->required_for_conditions,
                'filterable' => $entry['link']->is_filterable,
                'show_on_card' => $entry['link']->show_on_card,
                'group' => $entry['link']->group,
                'allows_other' => $attribute->allows_other,
                'depends_on' => $dependsOn,
                'options' => $attribute->type->hasOptions() ? ($optionsByAttribute[$attribute->id] ?? []) : [],
            ];
        }

        return $fields;
    }

    /**
     * Area units of the country, default first.
     *
     * @return list<array{code: string, symbol: string, name: array<string, string>}>
     */
    private function areaUnits(string $iso2): array
    {
        return $this->units->forCountry(Country::where('iso2', $iso2)->first())
            ->map(fn (MeasurementUnit $unit) => $this->unitArray($unit))
            ->values()
            ->all();
    }

    /**
     * Units for one area attribute. `rules.units` limits an attribute to suitable units (floor
     * area: building units only, never land units): the country's units in that list, or, when the
     * country offers none of them, the attribute's own list in its order.
     *
     * @param  list<array{code: string, symbol: string, name: array<string, string>}>  $countryUnits
     * @return list<array{code: string, symbol: string, name: array<string, string>}>
     */
    private function unitsFor(Attribute $attribute, array $countryUnits): array
    {
        $allowed = $attribute->rules['units'] ?? null;
        if (! is_array($allowed) || $allowed === []) {
            return $countryUnits;
        }

        $matching = array_values(array_filter($countryUnits, fn (array $unit) => in_array($unit['code'], $allowed, true)));
        if ($matching !== []) {
            return $matching;
        }

        return MeasurementUnit::area()->active()->whereIn('code', $allowed)->get()
            ->sortBy(fn (MeasurementUnit $unit) => array_search($unit->code, $allowed, true))
            ->map(fn (MeasurementUnit $unit) => $this->unitArray($unit))
            ->values()
            ->all();
    }

    /** @return array{code: string, symbol: string, name: array<string, string>} */
    private function unitArray(MeasurementUnit $unit): array
    {
        return ['code' => $unit->code, 'symbol' => $unit->symbol, 'name' => $unit->getTranslations('name')];
    }

    /**
     * Active, in-scope options; a dependent option also needs its parent option to be available.
     *
     * @param  list<Attribute>  $attributes
     * @return array<int, list<array{value: string, label: array<string, string>, parent: string|null}>>
     */
    private function availableOptions(array $attributes, string $iso2): array
    {
        $ids = array_map(fn (Attribute $attribute) => $attribute->id, array_filter($attributes, fn (Attribute $a) => $a->type->hasOptions()));
        if ($ids === []) {
            return [];
        }

        $options = AttributeOption::whereIn('attribute_id', $ids)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (AttributeOption $option) => $option->isInScopeFor($iso2))
            ->keyBy('id');

        $result = [];
        foreach ($options as $option) {
            $parent = null;
            if ($option->parent_option_id !== null) {
                $parentOption = $options->get($option->parent_option_id);
                if ($parentOption === null) {
                    continue; // parent inactive or out of scope
                }
                $parent = $parentOption->value;
            }

            $result[$option->attribute_id][] = [
                'value' => $option->value,
                'label' => $option->getTranslations('label'),
                'parent' => $parent,
            ];
        }

        return $result;
    }
}
