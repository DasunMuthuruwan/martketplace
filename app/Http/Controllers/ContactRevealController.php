<?php

namespace App\Http\Controllers;

use App\Actions\Listings\RevealContact;
use App\Exceptions\Listings\ContactChallengeRequired;
use App\Models\Listing;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `POST /listings/{listing}/contact` (routes doc §4.1). JSON only; error bodies use fixed codes the
 * frontend maps to messages: 401 login_required, 403 unavailable, 404, 428 challenge_required, 429.
 */
class ContactRevealController extends Controller
{
    public function __invoke(Request $request, Listing $listing, RevealContact $reveal): JsonResponse
    {
        try {
            $result = $reveal->handle($listing, $request->user(), (string) $request->ip(), $request->only(['channel', 'turnstile_token']));
        } catch (AuthenticationException) {
            return response()->json(['message' => 'login_required'], 401);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'unavailable'], 403);
        } catch (ContactChallengeRequired) {
            return response()->json(['message' => 'challenge_required'], 428);
        }

        return response()->json($result)->header('Cache-Control', 'no-store, private');
    }
}
