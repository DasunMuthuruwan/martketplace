<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\BoostOrder;
use App\Services\Boosts\BoostCatalogue;
use App\Services\Boosts\BoostOrders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The seller's promotion orders (B5): the list, one order with its payment instructions while it is
 * unpaid, and cancelling an unpaid order. Only the person who ordered sees an order.
 */
class BoostOrdersController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.orders', [
            'orders' => BoostOrder::where('user_id', $request->user()->id)
                ->with(['listing', 'country', 'currencyModel'])
                ->latest()->latest('id')->paginate(20),
        ]);
    }

    public function show(Request $request, BoostOrder $order, BoostCatalogue $catalogue): View
    {
        $this->authorizeOwner($request, $order);
        $order->loadMissing(['listing', 'country', 'currencyModel', 'boost', 'proofs']);

        return view('account.order', [
            'order' => $order,
            'bank' => $order->status->isOpen() ? $catalogue->bankAccount($order->country) : null,
        ]);
    }

    public function cancel(Request $request, BoostOrder $order, BoostOrders $orders): RedirectResponse
    {
        $this->authorizeOwner($request, $order);
        try {
            $orders->cancelBySeller($request->user(), $order);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('account.orders.show', $order)->with('status', __('boosts.order.cancelled'));
    }

    private function authorizeOwner(Request $request, BoostOrder $order): void
    {
        abort_unless($order->user_id !== null && $order->user_id === $request->user()->id, 403);
    }
}
