<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Reviews\SellerReviews;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** `POST /seller/{seller}/reviews` (G5, ADR-027): a buyer rates a seller they contacted. */
class SellerReviewController extends Controller
{
    public function store(Request $request, int $seller, SellerReviews $reviews): RedirectResponse
    {
        $user = User::activeAccounts()->findOrFail($seller);
        try {
            $reviews->submit($request->user(), $user, $request->only(['rating', 'comment', 'deal']));
        } catch (ValidationException $e) {
            return redirect()->to($user->sellerUrl().'#rate')->withErrors($e->errors(), 'review')->withInput();
        }

        return redirect()->to($user->sellerUrl().'#reviews')->with('status', __('reviews.saved'));
    }
}
