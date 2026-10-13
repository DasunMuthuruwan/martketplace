<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Listing;
use App\Services\Geo\CountryContext;
use App\Services\Seo\PageSeo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * `POST /country` — the visitor picks a country (country chooser or switcher). The choice is
 * remembered in a cookie and the visitor goes to that country's home `/{cc}` (built in T5.2).
 */
class CountryController extends Controller
{
    /** `/countries`: active countries with their number of public ads (cached 15 minutes). */
    public function index(): View
    {
        $counts = Cache::remember('public:country-ad-counts', now()->addMinutes(15), fn () => Listing::public()
            ->selectRaw('listings.country_id, count(*) as aggregate')
            ->groupBy('listings.country_id')
            ->pluck('aggregate', 'country_id')
            ->map(fn ($count) => (int) $count)
            ->all());

        return view('public.countries', [
            'countries' => Country::active()->get()->sortBy('name')->values(),
            'counts' => $counts,
            'seo' => PageSeo::make(__('public.seo.countries_title'), __('public.seo.countries_description'), route('countries')),
        ]);
    }

    public function choose(Request $request, CountryContext $context): RedirectResponse
    {
        $country = $context->find(is_string($request->input('country')) ? $request->input('country') : null);
        if ($country === null) {
            throw ValidationException::withMessages(['country' => __('geo.validation.unavailable', ['label' => __('geo.labels.country')])]);
        }

        return redirect()->to(url('/'.strtolower($country->iso2)))->withCookie($context->rememberCookie($country));
    }
}
