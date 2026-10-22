<?php

namespace App\Http\Controllers;

use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Services\Attributes\AttributeSchema;
use App\Services\Boosts\TopAds;
use App\Services\Catalogue\CategoryTree;
use App\Services\Geo\CountryContext;
use App\Services\Insights\PriceGuide;
use App\Services\Insights\Trends;
use App\Services\Listings\ListingPresenter;
use App\Services\Money\Money;
use App\Services\Search\ListingSearch;
use App\Services\Search\MysqlListingSearch;
use App\Services\Search\SearchCriteria;
use App\Services\Search\SearchCriteriaFactory;
use App\Services\Search\SearchTerms;
use App\Services\Seo\PageSeo;
use App\View\SearchPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Search and landing pages (routes doc §1, §1.1, global design §6). Category and location come
 * from the path on country routes; everything else from the query string.
 *   - An unknown slug in the path is a 404; a renamed category or a leaf given without its
 *     parent answers 301 to the canonical path.
 *   - An invalid query value is dropped with a 302 to the same page (no error page for a
 *     hand-edited or outdated URL).
 */
class SearchController extends Controller
{
    /** Query keys that the path sets on country routes. */
    private const PATH_KEYS = ['category', 'region', 'subregion', 'city'];

    public function __construct(
        private readonly SearchCriteriaFactory $factory,
        private readonly ListingSearch $search,
        private readonly CategoryTree $tree,
        private readonly AttributeSchema $schemas,
        private readonly ListingPresenter $present,
        private readonly TopAds $topAds,
        private readonly SearchTerms $terms,
        private readonly Trends $trends,
        private readonly PriceGuide $prices,
        private readonly Money $money,
    ) {}

    public function country(Request $request, CountryContext $context): View|RedirectResponse
    {
        $country = $context->current($request);
        // Read by name: the routes have different optional segments, and Laravel passes route
        // parameters to controller methods by position.
        [$category, $subcategory, $region, $city] = array_map(
            fn (string $name) => is_string($request->route($name)) ? $request->route($name) : null,
            ['category', 'subcategory', 'region', 'city'],
        );

        [$leaf, $redirect] = $this->pathCategory($category, $subcategory, $country, $request);
        if ($redirect !== null) {
            return $redirect;
        }

        // The category only comes from the path here; a location in the path replaces any in the query.
        $input = Arr::except($request->query(), $region !== null ? self::PATH_KEYS : ['category']);
        if ($leaf !== null) {
            $input['category'] = $leaf->slug;
        }
        $input = $this->pathLocation($region, $city, $country) + $input;

        return $this->results($request, $input, $country, $leaf);
    }

    /**
     * `/{cc}/ads/{category}/{subcategory}/make/{make}/{model?}` (G4, ADR-027): the category's results
     * for one make (and model), with its own title and a price guide, indexable for search engines.
     * Unknown makes or models, or a category without makes, answer 404; old category slugs 301.
     */
    public function make(Request $request, CountryContext $context): View|RedirectResponse
    {
        $country = $context->current($request);
        [$parentSlug, $leafSlug, $makeSlug, $modelSlug] = array_map(
            fn (string $name) => is_string($request->route($name)) ? $request->route($name) : null,
            ['category', 'subcategory', 'make', 'model'],
        );
        $parent = Category::findBySlugOrRedirect((string) $parentSlug);
        $leaf = Category::findBySlugOrRedirect((string) $leafSlug);
        if ($parent === null || $leaf === null || $leaf->parent_id !== $parent->id || ! $leaf->isAvailableIn($country->iso2)) {
            abort(404);
        }
        if ([$parentSlug, $leafSlug] !== [$parent->slug, $leaf->slug]) {
            return redirect()->to(route('ads.make', array_filter(['cc' => strtolower($country->iso2), 'category' => $parent->slug,
                'subcategory' => $leaf->slug, 'make' => $makeSlug, 'model' => $modelSlug])), 301);
        }

        $filterable = array_flip($this->schemas->for($leaf, $country->iso2)->filterableKeys());
        $make = isset($filterable['make']) ? $this->option('make', (string) $makeSlug) : null;
        $model = $modelSlug === null ? null : (isset($filterable['model']) ? $this->option('model', $modelSlug, $make) : null);
        if ($make === null || ($modelSlug !== null && $model === null)) {
            abort(404);
        }

        $input = Arr::except($request->query(), ['category', 'a']);
        $input['category'] = $leaf->slug;
        $input[SearchCriteriaFactory::ATTRIBUTE_INPUT] = array_filter(['make' => $make->value, 'model' => $model?->value]);

        return $this->results($request, $input, $country, $leaf, ['make' => $make, 'model' => $model]);
    }

    private function option(string $key, string $value, ?AttributeOption $parent = null): ?AttributeOption
    {
        return AttributeOption::query()
            ->whereHas('attribute', fn ($q) => $q->where('key', $key)->where('is_active', true))
            ->where('value', $value)->where('is_active', true)
            ->when($parent !== null, fn ($q) => $q->where('parent_option_id', $parent->id))
            ->first();
    }

    /** `/ads`: across all active countries; `?country=xx` is the country page itself (301). */
    public function global(Request $request, CountryContext $context): View|RedirectResponse
    {
        $code = $request->query('country');
        if (is_string($code) && ($country = $context->find($code)) !== null) {
            $query = Arr::except($request->query(), ['country']);

            return redirect()->to(route('ads.country', ['cc' => strtolower($country->iso2)]).($query ? '?'.http_build_query($query) : ''), 301);
        }

        $input = Arr::except($request->query(), ['country', 'region', 'subregion', 'city', 'locality']);
        $category = is_string($input['category'] ?? null) ? Category::where('slug', $input['category'])->first() : null;

        return $this->results($request, $input, null, $category);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array{make: AttributeOption, model: AttributeOption|null}|null  $landing  make/model page (G4)
     */
    private function results(Request $request, array $input, ?Country $country, ?Category $category, ?array $landing = null): View|RedirectResponse
    {
        try {
            $criteria = $this->factory->fromInput($input, $country);
        } catch (ValidationException $e) {
            // Values from the path were already resolved, so any error here is in the query string.
            return redirect()->to($request->url().$this->queryWithout($request->query(), array_keys($e->errors())));
        }

        $results = $this->search->search($criteria);
        if ($criteria->page === 1) {
            $this->terms->record($request, $country, $criteria->keyword); // counts only (G1, ADR-027 E4)
        }
        $categories = $this->tree->forCountry($country?->iso2);
        $filters = $criteria->category !== null && $country !== null ? $this->filterFields($criteria->category, $country) : [];
        $seo = $this->seo($request, $criteria, $results->total());
        if ($landing !== null && $country !== null) {
            $landing['name'] = trim($landing['make']->label.' '.($landing['model']->label ?? ''));
            $landing['guide'] = $this->prices->for($criteria, $country);
            $seo = $this->landingSeo($request, $landing, $country, $results->total());
        }

        return view('public.search', [
            'landing' => $landing,
            'country' => $country,
            'currentCountry' => $country,
            'criteria' => $criteria,
            'results' => $results,
            'topAds' => $this->topAds->for($criteria),
            // G2: makes and models trending in this category (country category pages, first page).
            'trendingInCategory' => $country !== null && $criteria->category !== null && $criteria->keyword === null && $criteria->page === 1
                ? $this->trends->trendingModels($country, $criteria->category, 6) : [],
            'categories' => $categories,
            'filters' => $filters,
            'page' => new SearchPage($request, $criteria, $country, $filters, $categories),
            'present' => $this->present,
            'seo' => $seo,
            'countCap' => MysqlListingSearch::countCap(),
        ]);
    }

    /**
     * "{Make} {Model} for sale in {Country}" with the count and, when known, the typical price range.
     * Indexable without a query string; canonical is the make/model address itself.
     *
     * @param  array{name: string, guide: array<string, mixed>|null}  $landing
     */
    private function landingSeo(Request $request, array $landing, Country $country, int $total): PageSeo
    {
        $count = trans_choice('public.ads_count', $total, ['count' => number_format($total)]);
        $guide = $landing['guide'];
        $description = $guide === null
            ? __('public.make_page.description', ['count' => $count, 'what' => $landing['name'], 'place' => $country->name])
            : __('public.make_page.description_prices', [
                'count' => $count, 'what' => $landing['name'], 'place' => $country->name,
                'low' => (string) $this->money->forCountry($guide['low'], $guide['currency'], $country),
                'high' => (string) $this->money->forCountry($guide['high'], $guide['currency'], $country),
            ]);

        return PageSeo::make(__('public.make_page.title', ['what' => $landing['name'], 'place' => $country->name]), $description, $request->url(), $request->query() === []);
    }

    /**
     * "{Category} in {City}, {Region}, {Country}". Landing pages (category / location in the path, no query) may
     * be indexed; any keyword, filter or later page is `noindex,follow` with the canonical pointing
     * at its landing page, which avoids crawl traps (architecture §8).
     */
    private function seo(Request $request, SearchCriteria $criteria, int $total): PageSeo
    {
        $what = $criteria->category->name ?? __('public.seo.search_title');
        $place = implode(', ', array_filter([
            $criteria->cityIds !== null ? City::find($criteria->cityIds[0])?->name : null,
            $criteria->subregionId !== null ? Region::find($criteria->subregionId)?->name : ($criteria->regionId !== null ? Region::find($criteria->regionId)?->name : null),
            $criteria->country?->name,
        ]));

        $title = $place === '' ? $what : __('public.seo.in_place', ['what' => $what, 'place' => $place]);
        $description = __('public.seo.search_description', [
            'count' => trans_choice('public.ads_count', $total, ['count' => number_format($total)]),
            'what' => $what,
            'place' => $place === '' ? __('public.all_countries') : $place,
        ]);

        return PageSeo::make($title, $description, $request->url(), $request->query() === []);
    }

    /**
     * Resolves `/{category}/{subcategory}`. Returns the leaf (or top-level) category to search, or a
     * 301 to the canonical path for renamed slugs and leaves given without their parent.
     *
     * @return array{0: Category|null, 1: RedirectResponse|null}
     */
    private function pathCategory(?string $slug, ?string $subSlug, Country $country, Request $request): array
    {
        if ($slug === null) {
            return [null, null];
        }

        $category = Category::findBySlugOrRedirect($slug);
        $sub = $subSlug === null ? null : Category::findBySlugOrRedirect($subSlug);
        if ($category === null || ($subSlug !== null && $sub === null)) {
            abort(404);
        }

        // A subcategory must belong to the category before it.
        if ($sub !== null && $sub->parent_id !== $category->id) {
            abort(404);
        }

        // Canonical form: /{parent}/{leaf} for leaves, /{top} for top-level categories.
        $target = $sub ?? $category;
        $canonical = $target->parent_id === null ? [$target->slug, null] : [$target->parent->slug, $target->slug];
        if (! $target->isAvailableIn($country->iso2)) {
            abort(404);
        }

        if ([$slug, $subSlug] !== $canonical) {
            $params = array_filter(['cc' => strtolower($country->iso2), 'category' => $canonical[0], 'subcategory' => $canonical[1],
                'region' => $request->route('region'), 'city' => $request->route('city')]);
            $name = $request->route('region') !== null
                ? ($canonical[1] === null ? 'ads.category.location' : 'ads.subcategory.location')
                : 'ads.category';
            $query = $request->getQueryString();

            return [null, redirect()->to(route($name, $params).($query ? '?'.$query : ''), 301)];
        }

        return [$target, null];
    }

    /**
     * `/in/{region}/{city}`: the region slug may be level 1 or level 2 (routes doc §1).
     *
     * @return array<string, string>
     */
    private function pathLocation(?string $regionSlug, ?string $citySlug, Country $country): array
    {
        if ($regionSlug === null) {
            return [];
        }

        $region = Region::where('country_id', $country->id)->where('slug', $regionSlug)->where('is_active', true)->first();
        if ($region === null) {
            abort(404);
        }
        $input = [$region->level === 2 ? 'subregion' : 'region' => $region->slug];

        if ($citySlug !== null) {
            $exists = City::where('country_id', $country->id)->where('slug', $citySlug)->where('is_active', true)
                ->where(fn ($q) => $q->where('region_id', $region->id)->orWhereIn('region_id', Region::where('parent_id', $region->id)->select('id')))
                ->exists();
            if (! $exists) {
                abort(404);
            }
            $input['city'] = $citySlug;
        }

        return $input;
    }

    /**
     * Filterable attributes for the filter form (same schema as validation).
     *
     * @return list<array<string, mixed>>
     */
    private function filterFields(Category $category, Country $country): array
    {
        $schema = $this->schemas->for($category, $country->iso2);
        $keys = array_flip($schema->filterableKeys());

        return array_values(array_filter($schema->form(), fn (array $field) => isset($keys[$field['key']]) && $field['type'] !== 'text'));
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  list<string>  $bad  error keys, e.g. "min_price" or "a.make"
     */
    private function queryWithout(array $query, array $bad): string
    {
        foreach ($bad as $key) {
            Arr::forget($query, $key);
        }
        $query = array_filter($query, fn ($value) => $value !== [] && $value !== null);

        return $query === [] ? '' : '?'.http_build_query($query);
    }
}
