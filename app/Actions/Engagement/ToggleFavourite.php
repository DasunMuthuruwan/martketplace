<?php

namespace App\Actions\Engagement;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Save or unsave a listing (SRCH-08, routes doc §4.3). Only public listings can be saved;
 * removing always works (the ad may have expired or been sold since).
 */
class ToggleFavourite
{
    /**
     * @return array{favourited: bool, count: int}
     *
     * @throws ModelNotFoundException listing not public
     */
    public function handle(User $user, Listing $listing): array
    {
        $saved = $user->favourites()->whereKey($listing->id)->exists();

        if ($saved) {
            $user->favourites()->detach($listing->id);
        } else {
            if (! $listing->isPublic()) {
                throw (new ModelNotFoundException)->setModel(Listing::class, [$listing->id]);
            }
            $user->favourites()->syncWithoutDetaching([$listing->id => ['created_at' => now()]]);
        }

        return [
            'favourited' => ! $saved,
            'count' => $listing->favouritedBy()->count(),
        ];
    }
}
