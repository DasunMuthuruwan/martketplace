<?php

namespace App\Http\Controllers;

use App\Enums\BoostType;
use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use App\Models\ListingBoost;
use App\Models\Region;
use App\Services\Catalogue\CategoryTree;
use App\Services\Geo\CountryContext;
use App\Services\Insights\Trends;
use App\Services\Listings\ListingPresenter;
use App\Services\Seo\PageSeo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** `/` and `/{cc}` (routes doc §1, G-2). */
class HomeController extends Controller
{
    public const LATEST = 12;

    public const FEATURED = 8;

    /** Known country → its home (302, it depends on the visitor); otherwise the country chooser. */
    public function index(Request $request, CountryContext $context, ListingPresenter $present): RedirectResponse|View
    {
        $country = $context->current($request);
        if ($country !== null) {
            return redirect()->route('country.home', ['cc' => strtolower($country->iso2)]);
        }

        return view('public.home', [
            'countries' => Country::active()->get()->sortBy('name')->values(),
            'latest' => $this->latest(null),
            'present' => $present,
            // Only shown when no country is known; crawlers normally get the redirect.
            'seo' => PageSeo::make(__('public.choose_country_title'), __('public.seo.countries_description'), route('home'), false),
        ]);
    }

    public function country(Request $request, CountryContext $context, CategoryTree $tree, ListingPresenter $present, Trends $trends): View
    {
        $country = $context->current($request);

        return view('public.country', [
            'country' => $country,
            'categories' => $tree->forCountry($country->iso2),
            'categoryCounts' => $this->categoryCounts($country),
            'latest' => $this->latest($country),
            'featured' => $this->featured($country),
            'regions' => $this->regions($country),
            // G2 (ADR-027): each list is empty until there is enough data.
            'popular' => $trends->popularAds($country),
            'trendingSearches' => $trends->trendingSearches($country),
            'trendingModels' => $trends->trendingModels($country),
            'mostSold' => $trends->mostMarkedSold($country),
            'present' => $present,
            'seo' => PageSeo::make(
                __('public.seo.country_title', ['country' => $country->name]),
                config('marketplace.seo.country_description')
                    ? str_replace(':country', $country->name, (string) config('marketplace.seo.country_description'))
                    : __('public.seo.country_description', ['country' => $country->name]),
                route('country.home', ['cc' => strtolower($country->iso2)]),
            ),
        ]);
    }

    /**
     * Live ads per top-level category in the country (home tiles), cached for 15 minutes like the
     * country counts.
     *
     * @return array<int, int> root category id => ads
     */
    private function categoryCounts(Country $country): array
    {
        return Cache::remember('public:category-ad-counts:'.$country->id, now()->addMinutes(15), function () use ($country): array {
            $perCategory = Listing::public()->where('listings.country_id', $country->id)->toBase()
                ->selectRaw('listings.category_id, count(*) as total')->groupBy('listings.category_id')->pluck('total', 'category_id');
            $parents = Category::whereIn('id', $perCategory->keys())->pluck('parent_id', 'id');

            $counts = [];
            foreach ($perCategory as $categoryId => $total) {
                $root = $parents[$categoryId] ?? $categoryId;
                $counts[$root] = ($counts[$root] ?? 0) + (int) $total;
            }

            return $counts;
        });
    }

    /**
     * Featured ads (U2): public ads of the country with a running Top-ad boost (ADR-026), newest
     * boost first. Empty when none run, so the home page hides the section.
     *
     * @return Collection<int, Listing>
     */
    private function featured(Country $country): Collection
    {
        $ids = ListingBoost::query()->running()
            ->where('listing_boosts.type', BoostType::Top->value)
            ->join('listings', 'listings.id', '=', 'listing_boosts.listing_id')
            ->where('listings.country_id', $country->id)
            ->orderByDesc('listing_boosts.starts_at')
            ->limit(self::FEATURED * 2)
            ->pluck('listing_boosts.listing_id')
            ->unique()->values()->all();
        if ($ids === []) {
            return new Collection;
        }

        return Listing::public()->whereIn('listings.id', $ids)
            ->with(['country', 'currencyModel', 'coverImage', 'city', 'region', 'subregion', 'locality', 'category.parent'])
            ->withCount('images')
            ->get()
            ->sortBy(fn (Listing $listing) => array_search($listing->id, $ids, true))
            ->take(self::FEATURED)
            ->values();
    }

    /**
     * Top-level places for the hero location selector (slug => name), using the country's own
     * hierarchy; empty when the country has none.
     *
     * @return array<string, string>
     */
    private function regions(Country $country): array
    {
        if ($country->region_levels < 1) {
            return [];
        }

        return Region::where('country_id', $country->id)->where('level', 1)->active()->get(['slug', 'name'])
            ->sortBy('name')->mapWithKeys(fn (Region $r) => [$r->slug => $r->name])->all();
    }

    /** @return Collection<int, Listing> */
    private function latest(?Country $country): Collection
    {
        return Listing::public()
            ->when($country !== null, fn ($q) => $q->where('country_id', $country->id))
            ->with(['country', 'currencyModel', 'coverImage', 'city', 'region', 'subregion', 'locality', 'category.parent'])
            ->withCount('images')
            ->orderByDesc('sorted_at')->orderByDesc('id')
            ->limit(self::LATEST)
            ->get();
    }
}
