<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentProof;
use App\Services\Audit\AuditLogger;
use App\Services\Boosts\PaymentProofs;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A seller's proof of payment for staff (B7, `boosts.manage`, checked by the route): images shown
 * in Admin → Orders, PDFs downloaded. Each view is audited, as the files hold bank details.
 */
class PaymentProofController extends Controller
{
    public function __invoke(Request $request, PaymentProof $proof, PaymentProofs $proofs, AuditLogger $audit): StreamedResponse
    {
        $response = $proofs->response($proof);
        $audit->record($request->user(), 'boost_order.proof_viewed', $proof->order, null, ['order' => $proof->order->reference, 'proof' => $proof->id]);

        return $response;
    }
}
