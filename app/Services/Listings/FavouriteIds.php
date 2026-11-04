<?php

namespace App\Services\Listings;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Saved state for the heart on listing cards (U2): the signed-in user's saved ad ids, loaded once
 * per request (scoped binding) however many cards the page shows.
 */
class FavouriteIds
{
    /** @var array<int, true>|null */
    private ?array $ids = null;

    public function has(int $listingId): bool
    {
        if ($this->ids === null) {
            $user = Auth::user();
            $this->ids = $user instanceof User
                ? array_fill_keys($user->favourites()->pluck('listings.id')->map(fn ($id) => (int) $id)->all(), true)
                : [];
        }

        return isset($this->ids[$listingId]);
    }
}
