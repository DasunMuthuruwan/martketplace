<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Listing;
use App\Services\Catalogue\CategoryTree;
use App\Services\Geo\CountryContext;
use App\Services\Listings\ListingPresenter;
use App\Services\Seo\PageSeo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** `/` and `/{cc}` (routes doc §1, G-2). */
class HomeController extends Controller
{
    public const LATEST = 12;

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

    public function country(Request $request, CountryContext $context, CategoryTree $tree, ListingPresenter $present): View
    {
        $country = $context->current($request);

        return view('public.country', [
            'country' => $country,
            'categories' => $tree->forCountry($country->iso2),
            'latest' => $this->latest($country),
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

    /** @return Collection<int, Listing> */
    private function latest(?Country $country): Collection
    {
        return Listing::public()
            ->when($country !== null, fn ($q) => $q->where('country_id', $country->id))
            ->with(['country', 'currencyModel', 'coverImage', 'city', 'region', 'subregion', 'category'])
            ->orderByDesc('published_at')->orderByDesc('id')
            ->limit(self::LATEST)
            ->get();
    }
}
