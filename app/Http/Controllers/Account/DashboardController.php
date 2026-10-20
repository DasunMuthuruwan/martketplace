<?php

namespace App\Http\Controllers\Account;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Services\Listings\ListingPresenter;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** `/account` (routes doc §3): counts per status, ads expiring soon, saved ads, quick links. */
class DashboardController extends Controller
{
    public function __invoke(Request $request, ListingPresenter $present): View
    {
        $user = $request->user();
        $soon = (int) config('marketplace.listings.renew_window_days');

        $expiring = Listing::where('user_id', $user->id)->where('status', ListingStatus::Active)
            ->whereBetween('expires_at', [now(), now()->addDays($soon)])
            ->with(['country', 'currencyModel', 'coverImage', 'category.parent'])->withCount('images')
            ->orderBy('expires_at')->limit(5)->get();

        return view('account.overview', [
            'user' => $user,
            'counts' => ListingsController::counts($user->id),
            'saved' => $user->favourites()->count(),
            'expiring' => $expiring,
            'publicIds' => ListingsController::publicIds($expiring),
            'present' => $present,
        ]);
    }
}
