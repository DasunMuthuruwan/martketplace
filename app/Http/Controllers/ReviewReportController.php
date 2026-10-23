<?php

namespace App\Http\Controllers;

use App\Models\SellerReview;
use App\Services\Reviews\ReviewModeration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** `POST /reviews/{review}/report` (G5b, ADR-027): a signed-in user reports a review once. */
class ReviewReportController extends Controller
{
    public function __invoke(Request $request, SellerReview $review, ReviewModeration $moderation): RedirectResponse
    {
        $seller = $review->seller;
        abort_if($seller === null || $review->status !== SellerReview::PUBLISHED, 404);
        try {
            $moderation->report($request->user(), $review, $request->only(['reason', 'details']));
        } catch (ValidationException $e) {
            return redirect()->to($seller->sellerUrl().'#reviews')->withErrors($e->errors(), 'report-'.$review->id);
        }

        return redirect()->to($seller->sellerUrl().'#reviews')->with('status', __('reviews.reported'));
    }
}
