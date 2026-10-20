<?php

namespace App\View;

use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Locality;
use App\Models\Region;
use App\Services\Search\SearchCriteria;
use App\Services\Search\SearchCriteriaFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * View model for the search page (layout doc §3.2, global UX §5). It keeps the current search as a
 * normalised state and builds every link from it: removable filter chips, category links, sort and
 * view switches. Category goes in the path on country pages; everything else in the query (filter
 * results are noindex, so the location stays in the query here).
 */
final class SearchPage
{
    /** @var array<string, mixed> */
    private array $state;

    /**
     * @param  list<array<string, mixed>>  $filterFields  filterable attribute fields of the category
     * @param  Collection<int, Category>  $tree  categories offered (roots with children)
     */
    public function __construct(
        private readonly Request $request,
        public readonly SearchCriteria $criteria,
        public readonly ?Country $country,
        public readonly array $filterFields,
        private readonly Collection $tree,
    ) {
        $this->state = $this->buildState();
    }

    public function view(): string
    {
        return $this->request->query('view') === 'list' ? 'list' : 'grid';
    }

    /** Where the filter form submits: the category page without a location in the path. */
    public function formAction(): string
    {
        return $this->baseUrl($this->state['category'] ?? null);
    }

    /** @return array<string, mixed> current values for the form */
    public function state(): array
    {
        return $this->state;
    }

    /** @param  array<string, mixed>  $changes  null removes a key */
    public function url(array $changes = []): string
    {
        $state = $this->state;
        foreach ($changes as $key => $value) {
            if ($value === null) {
                unset($state[$key]);
            } else {
                $state[$key] = $value;
            }
        }
        // Country pages put the category in the path; global search keeps it in the query.
        $category = $this->country !== null ? ($state['category'] ?? null) : null;
        unset($state['page']);
        if ($this->country !== null) {
            unset($state['category']);
        }
        if (($state['view'] ?? 'grid') === 'grid') {
            unset($state['view']);
        }
        if (($state['sort'] ?? SearchCriteria::SORT_NEWEST) === SearchCriteria::SORT_NEWEST) {
            unset($state['sort']);
        }

        $query = http_build_query(array_filter($state, fn ($value) => $value !== '' && $value !== [] && $value !== null));

        return $this->baseUrl($category).($query !== '' ? '?'.$query : '');
    }

    /**
     * Category links for the current branch (components §5.3): top-level when none is chosen, the
     * subcategories of a top-level category, or the siblings of a subcategory. Attribute filters
     * belong to one category, so they are dropped when the category changes.
     *
     * @return array{back: array{label: string, url: string}|null, items: list<array{label: string, url: string, active: bool}>}
     */
    public function categoryLinks(): array
    {
        $current = $this->criteria->category;
        $link = fn (Category $c) => ['label' => $c->name, 'url' => $this->url(['category' => $c->slug, 'a' => null]), 'active' => $current?->is($c) ?? false];

        if ($current === null) {
            return ['back' => null, 'items' => $this->tree->map($link)->values()->all()];
        }

        $root = $current->parent_id === null ? $current : $this->tree->first(fn (Category $c) => $c->id === $current->parent_id);
        $children = $root->children ?? collect();
        $back = $current->parent_id === null
            ? ['label' => __('search.all_categories'), 'url' => $this->url(['category' => null, 'a' => null])]
            : ['label' => $root->name ?? __('search.all_categories'), 'url' => $this->url(['category' => $root?->slug, 'a' => null])];

        return ['back' => $back, 'items' => $children->map($link)->values()->all()];
    }

    /**
     * Options for the dependent location selects (country pages only).
     *
     * @return array{region: array<string, string>, subregion: array<string, string>, city: array<string, string>, levels: int}
     */
    public function locationOptions(): array
    {
        $none = ['region' => [], 'subregion' => [], 'city' => [], 'levels' => 0];
        if ($this->country === null) {
            return $none;
        }

        $levels = $this->country->region_levels;
        $regions = Region::where('country_id', $this->country->id)->where('level', 1)->where('is_active', true)->orderBy('sort_order')->get()->sortBy('name');
        $subregions = $levels >= 2 && $this->criteria->regionId !== null
            ? Region::where('parent_id', $this->criteria->regionId)->where('is_active', true)->orderBy('sort_order')->get()->sortBy('name')
            : collect();
        $cityParent = $levels >= 2 ? $this->criteria->subregionId : $this->criteria->regionId;
        $cities = $levels === 0
            ? City::where('country_id', $this->country->id)->where('is_active', true)->orderBy('ascii_name')->limit(500)->get()
            : ($cityParent !== null ? City::where('region_id', $cityParent)->where('is_active', true)->orderBy('ascii_name')->get() : collect());

        return [
            'region' => $regions->mapWithKeys(fn (Region $r) => [$r->slug => $r->name])->all(),
            'subregion' => $subregions->mapWithKeys(fn (Region $r) => [$r->slug => $r->name])->all(),
            'city' => $cities->mapWithKeys(fn (City $c) => [$c->slug => $c->name])->all(),
            'levels' => $levels,
        ];
    }

    /** @return array<string, string> code => name, for the global price filter */
    public function currencies(): array
    {
        if ($this->country !== null) {
            return [];
        }

        return Currency::where('is_active', true)
            ->whereIn('code', Country::where('is_active', true)->whereNotNull('default_currency')->select('default_currency'))
            ->orderBy('code')->get()
            ->mapWithKeys(fn (Currency $c) => [$c->code => $c->code.' · '.$c->name])->all();
    }

    /**
     * Active filters as removable chips (components §5.3: "Remove filter: Toyota").
     *
     * @return list<array{label: string, url: string}>
     */
    public function chips(): array
    {
        $c = $this->criteria;
        $chips = [];
        $add = function (string $label, array $without) use (&$chips): void {
            $chips[] = ['label' => $label, 'url' => $this->url($without)];
        };

        if ($c->keyword !== null) {
            $add('“'.$c->keyword.'”', ['q' => null]);
        }
        // The category is the page itself (changed through the category links), not a filter chip.
        foreach ([['locality', $c->localityId, Locality::class], ['city', $c->cityIds[0] ?? null, City::class], ['subregion', $c->subregionId, Region::class], ['region', $c->regionId, Region::class]] as [$key, $id, $model]) {
            if ($id !== null && isset($this->state[$key])) {
                // Removing a place also removes the places inside it.
                $inner = array_slice(['region', 'subregion', 'city', 'locality'], (int) array_search($key, ['region', 'subregion', 'city', 'locality'], true));
                $add((string) $model::find($id)?->name, array_fill_keys($inner, null));
            }
        }
        if ($c->type !== null) {
            $add((string) __('public.types.'.$c->type), ['type' => null]);
        }
        if ($c->condition !== null) {
            $add((string) __('public.conditions.'.$c->condition), ['condition' => null]);
        }
        if ($c->minPrice !== null || $c->maxPrice !== null) {
            $add(__('search.chip_price', ['range' => $this->range($c->minPrice, $c->maxPrice), 'currency' => (string) $c->currency]), ['min_price' => null, 'max_price' => null]);
        }

        foreach ($this->filterFields as $field) {
            $value = $this->state['a'][$field['key']] ?? null;
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $without = ['a' => array_diff_key($this->state['a'], [$field['key'] => true]) ?: null];
            $add($field['label'].': '.$this->fieldValue($field, $value), $without);
        }

        return $chips;
    }

    /** "Clear all" keeps the keyword and category: they define the page, the rest are filters. */
    public function clearUrl(): string
    {
        return $this->url(array_fill_keys(['region', 'subregion', 'city', 'locality', 'type', 'condition', 'min_price', 'max_price', 'currency', 'a', 'sort'], null));
    }

    /** @return array<string, mixed> */
    private function buildState(): array
    {
        $q = $this->request->query();
        $c = $this->criteria;
        $slug = fn (?int $id, string $model) => $id === null ? null : $model::whereKey($id)->value('slug');
        $cityInput = $q['city'] ?? $this->request->route('city');

        $state = array_filter([
            'q' => $c->keyword,
            'category' => $c->category?->slug,
            'region' => $slug($c->regionId, Region::class),
            'subregion' => $slug($c->subregionId, Region::class),
            'city' => $c->cityIds !== null && is_string($cityInput) ? $cityInput : null,
            'locality' => $slug($c->localityId, Locality::class),
            'type' => $c->type,
            'condition' => $c->condition,
            'currency' => $this->country === null ? $c->currency : null,
            'min_price' => $c->minPrice,
            'max_price' => $c->maxPrice,
            'sort' => $c->sort,
            'view' => $this->request->query('view') === 'list' ? 'list' : null,
        ], fn ($value) => $value !== null && $value !== '');

        $attributes = $q[SearchCriteriaFactory::ATTRIBUTE_INPUT] ?? [];
        if (is_array($attributes) && $attributes !== [] && $this->filterFields !== []) {
            $known = array_flip(array_column($this->filterFields, 'key'));
            $state['a'] = array_filter(array_intersect_key($attributes, $known), fn ($v) => $v !== '' && $v !== null && $v !== []);
            if ($state['a'] === []) {
                unset($state['a']);
            }
        }

        return $state;
    }

    private function baseUrl(?string $categorySlug): string
    {
        if ($this->country === null) {
            return route('ads.global');
        }

        $cc = strtolower($this->country->iso2);
        if ($categorySlug === null) {
            return route('ads.country', ['cc' => $cc]);
        }
        // Category links use the cached tree (one query per link otherwise, F-08); the database only
        // for a category outside it.
        [$parentSlug, $leafSlug] = $this->treePath($categorySlug) ?? $this->databasePath($categorySlug) ?? [null, null];
        if ($parentSlug === null) {
            return route('ads.country', ['cc' => $cc]);
        }

        return $leafSlug === null
            ? route('ads.category', ['cc' => $cc, 'category' => $parentSlug])
            : route('ads.category', ['cc' => $cc, 'category' => $parentSlug, 'subcategory' => $leafSlug]);
    }

    /** @return array{0: string, 1: string|null}|null [top-level slug, subcategory slug or null] from the cached tree */
    private function treePath(string $slug): ?array
    {
        foreach ($this->tree as $root) {
            if ($root->slug === $slug) {
                return [$root->slug, null];
            }
            foreach ($root->children ?? [] as $child) {
                if ($child->slug === $slug) {
                    return [$root->slug, $child->slug];
                }
            }
        }

        return null;
    }

    /** @return array{0: string, 1: string|null}|null */
    private function databasePath(string $slug): ?array
    {
        $category = $slug === $this->criteria->category?->slug ? $this->criteria->category : Category::with('parent')->where('slug', $slug)->first();
        if ($category === null) {
            return null;
        }

        return $category->parent_id === null ? [$category->slug, null] : [$category->parent->slug, $category->slug];
    }

    private function range(?string $min, ?string $max): string
    {
        return match (true) {
            $min !== null && $max !== null => $min.'–'.$max,
            $min !== null => '≥ '.$min,
            default => '≤ '.$max,
        };
    }

    /** @param  array<string, mixed>  $field */
    private function fieldValue(array $field, mixed $value): string
    {
        if (in_array($field['type'], ['select', 'multiselect'], true)) {
            $labels = array_column($field['options'], 'label', 'value');
            $values = is_array($value) ? $value : explode(',', (string) $value);

            return implode(', ', array_map(fn ($v) => $labels[$v] ?? (string) $v, $values));
        }
        if ($field['type'] === 'boolean') {
            return (string) __('Yes');
        }
        if (is_array($value)) {
            $unit = $value['unit'] ?? null;

            return $this->range(filled($value['min'] ?? null) ? (string) $value['min'] : null, filled($value['max'] ?? null) ? (string) $value['max'] : null).($unit ? ' '.$unit : '');
        }

        return (string) $value;
    }
}
