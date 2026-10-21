<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\User;
use App\Services\Listings\ListingPresenter;
use App\Services\Seo\PageSeo;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * `/seller/{id}-{slug}` (SRCH-07): name, member since, verified badge and public ads. Never the
 * email or phone. Accounts that are deleted or not allowed to act are 404; a different slug 301s.
 */
class SellerController extends Controller
{
    public function show(string $idSlug, ListingPresenter $present): View|RedirectResponse
    {
        $id = (int) explode('-', $idSlug, 2)[0];
        $seller = User::activeAccounts()->find($id);
        if ($seller === null) {
            abort(404);
        }

        if ($idSlug !== $seller->id.'-'.$seller->sellerSlug()) {
            return redirect()->to($seller->sellerUrl(), 301);
        }

        $listings = Listing::public()->where('user_id', $seller->id)
            ->with(['country', 'currencyModel', 'coverImage', 'city', 'region', 'subregion', 'locality', 'category.parent'])
            ->withCount('images')
            ->orderByDesc('sorted_at')->orderByDesc('id')
            ->paginate((int) config('marketplace.listings.per_page'));

        return view('public.seller', [
            'seller' => $seller,
            'listings' => $listings,
            'present' => $present,
            'seo' => PageSeo::make(
                __('public.seo.seller_title', ['name' => $seller->name]),
                __('public.seo.seller_description', ['name' => $seller->name]),
                $seller->sellerUrl(),
                $listings->currentPage() === 1 && $listings->isNotEmpty(),
            ),
        ]);
    }
}
