<?php

namespace App\Services\Boosts;

use App\Enums\BoostType;
use App\Models\Listing;
use App\Models\ListingBoost;
use App\Services\Search\ListingSearch;
use App\Services\Search\SearchCriteria;
use Illuminate\Database\Eloquent\Collection;

/**
 * The labelled top-ad block above search results (ADR-026, D5, proposal §4):
 * - at most `boosts.top_slots` ads, only with the default "newest" order, only on country pages;
 * - relevant: run through the same query as the results, so country, category subtree, keyword
 *   and every filter must match, and the ad must be public;
 * - rotating: the least recently shown top ads come first, so every paying seller is seen.
 * The ads also keep their normal place in the results.
 */
class TopAds
{
    public function __construct(private readonly ListingSearch $search) {}

    /** @return Collection<int, Listing> */
    public function for(SearchCriteria $criteria): Collection
    {
        $slots = (int) config('marketplace.boosts.top_slots');
        if ($slots < 1 || $criteria->country === null || $criteria->sort !== SearchCriteria::SORT_NEWEST) {
            return new Collection;
        }

        $boosts = ListingBoost::query()->running()
            ->where('listing_boosts.type', BoostType::Top->value)
            ->join('listings', 'listings.id', '=', 'listing_boosts.listing_id')
            ->where('listings.country_id', $criteria->country->id)
            ->orderByRaw('listing_boosts.last_shown_at IS NOT NULL')
            ->orderBy('listing_boosts.last_shown_at')
            ->orderBy('listing_boosts.id')
            ->get(['listing_boosts.id', 'listing_boosts.listing_id']);
        if ($boosts->isEmpty()) {
            return new Collection;
        }

        $matching = $this->search->among($criteria, $boosts->pluck('listing_id')->unique()->values()->all());

        $chosen = $boosts->filter(fn (ListingBoost $b) => $matching->has($b->listing_id))->unique('listing_id')->take($slots);
        // Shown ads move to the back in the order shown (a second apart), so ties never favour the
        // same ad: with three top ads and two slots each one is shown twice in three page views.
        foreach ($chosen->values() as $position => $boost) {
            ListingBoost::whereKey($boost->id)->update(['last_shown_at' => now()->addSeconds($position)]);
        }

        $position = $chosen->pluck('listing_id')->values()->flip();

        return $matching->only($chosen->pluck('listing_id')->all())
            ->sortBy(fn (Listing $listing) => $position[$listing->id])
            ->values();
    }
}
