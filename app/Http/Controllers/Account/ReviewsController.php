<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\SellerReview;
use App\Services\Reviews\SellerReviews;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The seller's reviews (G5): every published rating they received, comments once approved, and a
 * single reply to each.
 */
class ReviewsController extends Controller
{
    public function index(Request $request, SellerReviews $reviews): View
    {
        $seller = $request->user();

        return view('account.reviews', [
            'summary' => $reviews->summary($seller),
            'reviews' => SellerReview::query()->published()->where('seller_id', $seller->id)->with('reviewer')
                ->latest()->latest('id')->paginate(20),
        ]);
    }

    public function reply(Request $request, SellerReview $review, SellerReviews $reviews): RedirectResponse
    {
        abort_unless($review->seller_id === $request->user()->id, 403);
        try {
            $reviews->reply($request->user(), $review, (string) $request->input('reply'));
        } catch (ValidationException $e) {
            return redirect()->route('account.reviews')->withErrors($e->errors(), 'reply-'.$review->id)->withInput();
        }

        return redirect()->route('account.reviews')->with('status', __('reviews.replied'));
    }
}
