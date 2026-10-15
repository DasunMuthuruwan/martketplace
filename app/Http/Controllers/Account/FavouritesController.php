<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Services\Listings\ListingPresenter;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `/account/favourites` (routes doc §3): saved ads, newest saved first. Ads that are no longer
 * public stay listed (so the person knows) but show as unavailable, without a link.
 */
class FavouritesController extends Controller
{
    public function __invoke(Request $request, ListingPresenter $present): View
    {
        $favourites = $request->user()->favourites()
            ->with(['country', 'currencyModel', 'coverImage', 'category.parent', 'city', 'region', 'subregion', 'locality'])
            ->withCount('images')
            ->paginate((int) config('marketplace.listings.per_page'));

        return view('account.favourites', [
            'favourites' => $favourites,
            'publicIds' => Listing::public()->whereIn('listings.id', $favourites->getCollection()->modelKeys())->pluck('listings.id')->flip(),
            'present' => $present,
        ]);
    }
}
