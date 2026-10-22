<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Services\Boosts\BoostCatalogue;
use App\Services\Boosts\BoostOrders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "Promote" (B5, ADR-026): the seller chooses a promotion for their own live ad and orders it; the
 * order page then shows how to pay by bank transfer. Prices come from the server's price table.
 */
class PromoteController extends Controller
{
    public function show(Request $request, Listing $listing, BoostCatalogue $catalogue): View|RedirectResponse
    {
        Gate::authorize('update', $listing);
        if (! $listing->isPublic()) {
            return redirect()->route('account.listings')->with('error', __('boosts.not_live'));
        }
        $listing->loadMissing(['country', 'currencyModel']);
        $open = $catalogue->isOpen($listing->country);

        return view('account.promote', [
            'listing' => $listing,
            'open' => $open,
            'offers' => $open ? $catalogue->offers($listing->country) : collect(),
        ]);
    }

    public function store(Request $request, Listing $listing, BoostOrders $orders): RedirectResponse
    {
        Gate::authorize('update', $listing); // before reading the form: strangers get 403, not a validation error
        $validated = $request->validate(['price' => ['required', 'integer']]);
        try {
            $order = $orders->place($request->user(), $listing, (int) $validated['price']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('account.orders.show', $order)->with('status', __('boosts.order.placed'));
    }
}
