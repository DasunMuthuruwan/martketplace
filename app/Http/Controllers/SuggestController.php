<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Services\Search\Suggestions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /search/suggest?q=…&cc=lk` (G1, ADR-027): live search suggestions as JSON for the search box.
 * Public, rate-limited, cached; an unknown or inactive country gives suggestions for all countries.
 */
class SuggestController extends Controller
{
    public function __invoke(Request $request, Suggestions $suggestions): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:100'],
            'cc' => ['nullable', 'string', 'size:2'],
        ]);
        $country = isset($validated['cc'])
            ? Country::where('iso2', strtoupper($validated['cc']))->where('is_active', true)->first()
            : null;

        return response()->json(['items' => $suggestions->for($country, $validated['q'])])
            ->header('Cache-Control', 'public, max-age=300');
    }
}
