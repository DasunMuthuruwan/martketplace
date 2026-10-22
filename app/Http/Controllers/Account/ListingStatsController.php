<?php

namespace App\Http\Controllers\Account;

use App\Enums\BoostType;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingBoost;
use App\Services\Listings\ListingViews;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The seller's statistics for one ad (B3, ADR-026 proposal §7): views per day for the last 30 days,
 * the days a Top ad ran, the ad's boosts, and for the latest Top ad the average daily views in the
 * week before it against the days it has run. Only the owner sees it (ListingPolicy::update, like the
 * other pages of one's own ad).
 */
class ListingStatsController extends Controller
{
    public const DAYS = 30;

    public function __invoke(Request $request, Listing $listing, ListingViews $views): View
    {
        Gate::authorize('update', $listing);

        $series = $views->daily($listing, self::DAYS);
        $boosts = $listing->boosts()->latest('starts_at')->latest('id')->limit(20)->get();
        $tops = $boosts->filter(fn (ListingBoost $b) => $b->type === BoostType::Top && $b->starts_at->isPast());

        $days = [];
        foreach ($series as $date => $count) {
            $day = Carbon::parse($date);
            $days[] = [
                'date' => $day,
                'views' => $count,
                'top' => $tops->contains(fn (ListingBoost $b) => $this->covers($b, $day)),
            ];
        }

        return view('account.listing-stats', [
            'listing' => $listing,
            'days' => array_reverse($days), // newest first
            'max' => max(1, ...array_values($series)),
            'total' => array_sum($series),
            'boosts' => $boosts,
            'comparison' => $this->comparison($listing, $tops->first(), $views),
        ]);
    }

    private function covers(ListingBoost $boost, Carbon $day): bool
    {
        $end = $boost->ended_at ?? $boost->ends_at;

        return $boost->starts_at->copy()->startOfDay()->lte($day) && ($end === null || $end->gt($day));
    }

    /**
     * Average views per day in the 7 days before the latest Top ad and on the days it has run.
     *
     * @return array{before: float, during: float, days: int}|null
     */
    private function comparison(Listing $listing, ?ListingBoost $top, ListingViews $views): ?array
    {
        if ($top === null) {
            return null;
        }
        $series = $views->daily($listing, 120);
        $start = $top->starts_at->copy()->startOfDay();
        $end = min($top->ended_at ?? $top->ends_at ?? now(), now());
        $before = $during = [];
        foreach ($series as $date => $count) {
            $day = Carbon::parse($date);
            if ($day->lt($start) && $day->gte($start->copy()->subDays(7))) {
                $before[] = $count;
            } elseif ($day->gte($start) && $day->lte($end)) {
                $during[] = $count;
            }
        }

        return $during === [] ? null : [
            'before' => $before === [] ? 0.0 : round(array_sum($before) / count($before), 1),
            'during' => round(array_sum($during) / count($during), 1),
            'days' => count($during),
        ];
    }
}
