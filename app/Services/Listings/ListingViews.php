<?php

namespace App\Services\Listings;

use App\Models\Listing;
use App\Models\User;
use App\Support\Bots;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Counts views of a public ad (B1, ADR-026): the seller's statistics compare views per day before
 * and during a boost. One view per visitor session, ad and day; the owner, staff and bots are not
 * counted. Only counts are kept (no visitor data).
 */
class ListingViews
{
    public const SESSION_KEY = 'listing_views';

    /** Ads remembered per session and day; beyond this a visitor's further views still count once each. */
    private const REMEMBER = 300;

    public function record(Request $request, Listing $listing): void
    {
        if (Bots::is($request) || $this->isInsider($request->user(), $listing)) {
            return;
        }

        $today = now()->toDateString();
        if ($request->hasSession()) {
            $seen = (array) $request->session()->get(self::SESSION_KEY, []);
            if (($seen['date'] ?? null) !== $today) {
                $seen = ['date' => $today, 'ids' => []];
            }
            if (in_array($listing->id, $seen['ids'], true)) {
                return;
            }
            $seen['ids'] = array_slice([...$seen['ids'], $listing->id], -self::REMEMBER);
            $request->session()->put(self::SESSION_KEY, $seen);
        }

        DB::table('listing_daily_views')->upsert(
            [['listing_id' => $listing->id, 'date' => $today, 'views' => 1]],
            ['listing_id', 'date'],
            ['views' => DB::raw('listing_daily_views.views + 1')],
        );
        // Query builder increment: does not touch updated_at (that means "edited by the seller").
        DB::table('listings')->where('id', $listing->id)->increment('view_count');
    }

    /**
     * Views per day for the last $days days (oldest first), zero-filled.
     *
     * @return array<string, int> date => views
     */
    public function daily(Listing $listing, int $days = 30): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $counts = DB::table('listing_daily_views')->where('listing_id', $listing->id)
            ->where('date', '>=', $from->toDateString())
            ->pluck('views', 'date')
            ->mapWithKeys(fn ($views, $date) => [substr((string) $date, 0, 10) => (int) $views]);

        $series = [];
        for ($day = $from->copy(); $day->lte(now()); $day->addDay()) {
            $series[$day->toDateString()] = $counts[$day->toDateString()] ?? 0;
        }

        return $series;
    }

    private function isInsider(?User $user, Listing $listing): bool
    {
        return $user !== null && ($user->id === $listing->user_id || $user->isStaff());
    }
}
