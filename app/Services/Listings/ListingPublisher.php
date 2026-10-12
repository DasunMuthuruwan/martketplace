<?php

namespace App\Services\Listings;

use App\Enums\ListingStatus;
use App\Models\Listing;

/**
 * Makes a listing public: status active, published now, expiring after the configured period
 * (LST-08). Used by approval and by submission when moderation lets it straight through.
 */
class ListingPublisher
{
    public function publish(Listing $listing): void
    {
        $listing->forceFill([
            'status' => ListingStatus::Active,
            'published_at' => now(),
            'expires_at' => now()->addDays((int) config('marketplace.listings.expiry_days')),
            'moderation_note' => null,
        ])->save();
    }
}
