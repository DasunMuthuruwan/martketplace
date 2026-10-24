<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\BoostOrder;
use App\Models\PaymentProof;
use App\Services\Boosts\PaymentProofs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The seller's proof of payment on an unpaid promotion order (B7): attach a slip (one file per
 * request, so several files never exceed the server's request size), view or remove one. Only the
 * person who ordered can do any of this; staff see proofs in Admin → Orders.
 */
class PaymentProofsController extends Controller
{
    public function store(Request $request, BoostOrder $order, PaymentProofs $proofs): RedirectResponse
    {
        $this->authorizeOwner($request, $order);
        $data = $request->validateWithBag('proof', [
            'proof' => ['required', 'file'],
            'transfer_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:'.$order->created_at->copy()->subDay()->toDateString()],
            'transfer_note' => ['nullable', 'string', 'max:300'],
        ], [], [
            'proof' => __('boosts.proof.file'),
            'transfer_date' => __('boosts.proof.transfer_date'),
            'transfer_note' => __('boosts.proof.transfer_note'),
        ]);

        try {
            $proofs->store($request->user(), $order, $request->file('proof'), $data['transfer_date'] ?? null,
                isset($data['transfer_note']) ? trim((string) $data['transfer_note']) : null);
        } catch (ValidationException $e) {
            return redirect()->route('account.orders.show', $order)->withErrors($e->errors(), 'proof')->withInput($request->except('proof'));
        }

        return redirect()->route('account.orders.show', $order)->with('status', __('boosts.proof.saved'));
    }

    public function show(Request $request, BoostOrder $order, PaymentProof $proof, PaymentProofs $proofs): StreamedResponse
    {
        $this->authorizeOwner($request, $order, $proof);

        return $proofs->response($proof);
    }

    public function destroy(Request $request, BoostOrder $order, PaymentProof $proof, PaymentProofs $proofs): RedirectResponse
    {
        $this->authorizeOwner($request, $order, $proof);
        try {
            $proofs->remove($request->user(), $proof);
        } catch (ValidationException $e) {
            return redirect()->route('account.orders.show', $order)->withErrors($e->errors(), 'proof');
        }

        return redirect()->route('account.orders.show', $order)->with('status', __('boosts.proof.removed'));
    }

    private function authorizeOwner(Request $request, BoostOrder $order, ?PaymentProof $proof = null): void
    {
        abort_unless($order->user_id !== null && $order->user_id === $request->user()->id, 403);
        abort_if($proof !== null && $proof->boost_order_id !== $order->id, 404);
    }
}
