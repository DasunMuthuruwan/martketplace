<?php

namespace App\Services\Search;

use App\Enums\AttributeType;
use App\Enums\ItemCondition;
use App\Enums\ListingType;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Locality;
use App\Models\MeasurementUnit;
use App\Models\Region;
use App\Services\Attributes\AttributeSchema;
use App\Services\Money\AreaConverter;
use App\Support\Decimal;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Turns query-string filters into SearchCriteria (URL state, global design §6):
 *   q, category, region, subregion, city, locality (slugs), type, condition, currency, min_price,
 *   max_price, sort, page, attr[key] (option value or list, or [min, max] and for areas [unit]).
 * Rules: location and attribute filters need a country (global search covers keywords and
 * categories, G-7); attribute filters are limited to the category's filterable attributes (at
 * most MAX_ATTRIBUTE_FILTERS); prices are compared in one currency only, the country's, or in
 * global search the one the visitor picked; without it, price filters and sort are ignored and
 * flagged. Unknown or invalid values raise a ValidationException.
 */
class SearchCriteriaFactory
{
    public const MAX_ATTRIBUTE_FILTERS = 10;

    public const KEYWORD_MAX = 100;

    public function __construct(
        private readonly AttributeSchema $schemas,
        private readonly AreaConverter $converter,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function fromInput(array $input, ?Country $country): SearchCriteria
    {
        $sorts = [SearchCriteria::SORT_NEWEST, SearchCriteria::SORT_PRICE_ASC, SearchCriteria::SORT_PRICE_DESC];
        $v = Validator::make($input, [
            'q' => ['nullable', 'string', 'max:'.self::KEYWORD_MAX],
            'category' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'subregion' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'locality' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::enum(ListingType::class)],
            'condition' => ['nullable', Rule::enum(ItemCondition::class)],
            'currency' => ['nullable', 'string', 'size:3'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'sort' => ['nullable', Rule::in($sorts)],
            'page' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('marketplace.listings.max_page')],
            'attr' => ['nullable', 'array', 'max:'.self::MAX_ATTRIBUTE_FILTERS],
        ])->validate();

        $category = $this->category($v['category'] ?? null, $country);
        [$regionId, $subregionId, $cityIds, $localityId] = $this->location($v, $country);
        [$currency, $minPrice, $maxPrice, $sort, $priceIgnored] = $this->price($v, $country);

        if ($minPrice !== null && $maxPrice !== null && Decimal::compare($minPrice, $maxPrice) > 0) {
            [$minPrice, $maxPrice] = [$maxPrice, $minPrice];
        }

        return new SearchCriteria(
            country: $country,
            keyword: filled($v['q'] ?? null) ? trim((string) preg_replace('/\s+/u', ' ', (string) $v['q'])) : null,
            category: $category,
            categoryIds: $category === null ? [] : $this->leafIds($category, $country),
            regionId: $regionId,
            subregionId: $subregionId,
            cityIds: $cityIds,
            localityId: $localityId,
            type: $v['type'] ?? null,
            condition: $v['condition'] ?? null,
            currency: $currency,
            minPrice: $minPrice,
            maxPrice: $maxPrice,
            attributeFilters: $this->attributes((array) ($v['attr'] ?? []), $category, $country),
            sort: $sort,
            page: (int) ($v['page'] ?? 1),
            priceIgnored: $priceIgnored,
        );
    }

    private function category(?string $slug, ?Country $country): ?Category
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        $category = Category::with('parent')->where('slug', $slug)->first();
        if ($category === null || ($country !== null ? ! $category->isAvailableIn($country->iso2) : ! $category->is_active)) {
            $this->fail('category');
        }

        return $category;
    }

    /** @return list<int> the category itself if it is a leaf, otherwise its active (in-scope) subcategories */
    private function leafIds(Category $category, ?Country $country): array
    {
        $children = $category->children()->active()->when($country !== null, fn ($q) => $q->inScopeFor($country->iso2))->pluck('id')->all();

        return $children === [] ? [$category->id] : $children;
    }

    /**
     * @param  array<string, mixed>  $v
     * @return array{0: int|null, 1: int|null, 2: list<int>|null, 3: int|null}
     */
    private function location(array $v, ?Country $country): array
    {
        $given = array_filter(['region', 'subregion', 'city', 'locality'], fn (string $key) => filled($v[$key] ?? null));
        if ($given === []) {
            return [null, null, null, null];
        }
        if ($country === null) {
            $this->fail(array_values($given)[0]);
        }

        $region = $this->region($v['region'] ?? null, $country, 1, null, 'region');
        $subregion = $this->region($v['subregion'] ?? null, $country, 2, $region, 'subregion');

        $cityIds = null;
        if (filled($v['city'] ?? null)) {
            $parent = $subregion ?? $region;
            $cityIds = City::where('country_id', $country->id)->where('slug', $v['city'])->where('is_active', true)
                ->when($parent !== null, fn ($q) => $q->where(fn ($q) => $q->where('region_id', $parent->id)
                    ->orWhereIn('region_id', Region::where('parent_id', $parent->id)->select('id'))))
                ->pluck('id')->all();
            if ($cityIds === []) {
                $this->fail('city');
            }
        }

        $localityId = null;
        if (filled($v['locality'] ?? null)) {
            if ($cityIds === null || count($cityIds) !== 1) {
                $this->fail('locality');
            }
            $localityId = Locality::where('city_id', $cityIds[0])->where('slug', $v['locality'])->where('is_active', true)->value('id');
            if ($localityId === null) {
                $this->fail('locality');
            }
        }

        return [$region?->id, $subregion?->id, $cityIds, $localityId];
    }

    private function region(?string $slug, Country $country, int $level, ?Region $parent, string $field): ?Region
    {
        if (! filled($slug)) {
            return null;
        }

        $region = Region::where('country_id', $country->id)->where('slug', $slug)->where('level', $level)->where('is_active', true)
            ->when($parent !== null, fn ($q) => $q->where('parent_id', $parent->id))
            ->first();
        if ($region === null) {
            $this->fail($field);
        }

        return $region;
    }

    /**
     * @param  array<string, mixed>  $v
     * @return array{0: string|null, 1: string|null, 2: string|null, 3: string, 4: bool}
     */
    private function price(array $v, ?Country $country): array
    {
        $sort = $v['sort'] ?? SearchCriteria::SORT_NEWEST;
        $min = filled($v['min_price'] ?? null) ? Decimal::parse($v['min_price']) : null;
        $max = filled($v['max_price'] ?? null) ? Decimal::parse($v['max_price']) : null;
        $requested = strtoupper((string) ($v['currency'] ?? ''));

        if ($country !== null) {
            // MVP: one currency per country (G-4). Another currency is not offered in this country.
            if ($requested !== '' && $requested !== $country->default_currency) {
                $this->fail('currency');
            }
            $currency = $country->default_currency;
        } elseif ($requested !== '') {
            if (! Currency::where('code', $requested)->where('is_active', true)->exists()) {
                $this->fail('currency');
            }
            $currency = $requested;
        } else {
            $currency = null;
        }

        $wantsPrice = $min !== null || $max !== null || $sort !== SearchCriteria::SORT_NEWEST;
        if ($currency === null && $wantsPrice) {
            return [null, null, null, SearchCriteria::SORT_NEWEST, true];
        }

        return [$currency, $min, $max, $sort, false];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<AttributeFilter>
     */
    private function attributes(array $input, ?Category $category, ?Country $country): array
    {
        $input = array_filter($input, fn ($value) => $value !== null && $value !== '' && $value !== []);
        if ($input === []) {
            return [];
        }
        if ($category === null || $country === null) {
            $this->fail('attr');
        }

        $schema = $this->schemas->for($category, $country->iso2);
        $filterable = array_flip($schema->filterableKeys());
        $ids = Attribute::whereIn('key', array_keys($input))->pluck('id', 'key');

        $filters = [];
        foreach ($input as $key => $value) {
            $field = isset($filterable[$key]) ? $schema->field((string) $key) : null;
            if ($field === null || ! isset($ids[$key])) {
                $this->fail("attr.{$key}");
            }
            $filters[] = $this->attributeFilter((int) $ids[$key], $field, $value, $input);
        }

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $all
     */
    private function attributeFilter(int $attributeId, array $field, mixed $value, array $all): AttributeFilter
    {
        $key = 'attr.'.$field['key'];

        switch (AttributeType::from($field['type'])) {
            case AttributeType::Select:
            case AttributeType::MultiSelect:
                $values = array_values(array_unique(array_map('strval', (array) $value)));
                $allowed = array_column($field['options'], 'value');
                if ($values === [] || array_diff($values, $allowed) !== []) {
                    $this->fail($key);
                }
                $query = AttributeOption::where('attribute_id', $attributeId)->whereIn('value', $values);
                // Dependent select (Model under Make): with one parent value chosen, only its options.
                $parentValue = $field['depends_on'] !== null ? ($all[$field['depends_on']] ?? null) : null;
                if (is_string($parentValue)) {
                    $query->whereHas('parentOption', fn ($q) => $q->where('value', $parentValue));
                }

                return new AttributeFilter($attributeId, optionIds: $query->pluck('id')->map(fn ($id) => (int) $id)->all());

            case AttributeType::Boolean:
                $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($bool === null) {
                    $this->fail($key);
                }

                return new AttributeFilter($attributeId, bool: $bool);

            case AttributeType::Number:
            case AttributeType::Year:
                if (! is_array($value)) {
                    $this->fail($key);
                }
                $min = filled($value['min'] ?? null) ? Decimal::parse($value['min']) : null;
                $max = filled($value['max'] ?? null) ? Decimal::parse($value['max']) : null;
                if (($min === null && $max === null) || (filled($value['min'] ?? null) && $min === null) || (filled($value['max'] ?? null) && $max === null)) {
                    $this->fail($key);
                }
                if ($field['dimension'] === MeasurementUnit::AREA) {
                    [$min, $max] = $this->toSquareMetres($min, $max, $value['unit'] ?? null, $field, $key);
                }

                return new AttributeFilter($attributeId, min: $min, max: $max);

            default:
                $this->fail($key); // text attributes are not filterable
        }
    }

    /**
     * Area filters are entered in one of the country's units and compared in m² (global design §6).
     *
     * @param  array<string, mixed>  $field
     * @return array{0: string|null, 1: string|null}
     */
    private function toSquareMetres(?string $min, ?string $max, mixed $unitCode, array $field, string $key): array
    {
        $codes = array_column($field['units'], 'code');
        $unitCode = is_string($unitCode) && $unitCode !== '' ? $unitCode : ($codes[0] ?? null);
        if (! in_array($unitCode, $codes, true)) {
            $this->fail($key);
        }
        $unit = MeasurementUnit::where('code', $unitCode)->firstOrFail();

        return [
            $min === null ? null : $this->converter->toBase($min, $unit),
            $max === null ? null : $this->converter->toBase($max, $unit),
        ];
    }

    private function fail(string $field): never
    {
        throw ValidationException::withMessages([$field => __('search.invalid_filter')]);
    }
}
