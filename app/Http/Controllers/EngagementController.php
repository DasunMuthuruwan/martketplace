<?php

namespace App\Http\Controllers;

use App\Actions\Engagement\SubmitReport;
use App\Actions\Engagement\ToggleFavourite;
use App\Exceptions\DuplicateReport;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** JSON endpoints for favourites and reports (routes doc §4.3, §4.4). */
class EngagementController extends Controller
{
    public function favourite(Request $request, Listing $listing, ToggleFavourite $toggle): JsonResponse
    {
        return response()->json($toggle->handle($request->user(), $listing));
    }

    public function report(Request $request, SubmitReport $submit): JsonResponse
    {
        try {
            $report = $submit->handle($request->user(), $request->all());
        } catch (DuplicateReport $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['id' => $report->id, 'status' => $report->status->value, 'message' => __('reports.received')], 201);
    }
}
