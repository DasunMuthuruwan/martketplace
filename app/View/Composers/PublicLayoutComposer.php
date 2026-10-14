<?php

namespace App\View\Composers;

use App\Models\Country;
use App\Models\Page;
use App\Services\Catalogue\CatalogueCache;
use App\Services\Catalogue\CategoryTree;
use App\Services\Geo\CountryContext;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Data for the public layout (navigation doc §2): the visitor's country, the categories offered
 * there (category bar and footer), active countries for the switcher and the published pages for
 * the footer. Everything is cached; a page that already passes these values keeps its own.
 */
class PublicLayoutComposer
{
    public function __construct(
        private readonly Request $request,
        private readonly CountryContext $countries,
        private readonly CategoryTree $tree,
    ) {}

    public function compose(View $view): void
    {
        try {
            $country = $view->offsetExists('currentCountry') ? $view->offsetGet('currentCountry') : $this->countries->current($this->request);

            $view->with([
                'currentCountry' => $country,
                'navCategories' => $this->tree->forCountry($country?->iso2),
                'switcherCountries' => CatalogueCache::remember('layout:active-countries', fn () => Country::active()->get(['id', 'iso2', 'name'])->sortBy('name')->values()),
                'footerPages' => Page::footerLinks(),
            ]);
        } catch (Throwable $e) {
            // The layout must still render (e.g. before migrations, or a database outage on an error page).
            report($e);
            $view->with(['currentCountry' => null, 'navCategories' => collect(), 'switcherCountries' => collect(), 'footerPages' => collect()]);
        }
    }
}
