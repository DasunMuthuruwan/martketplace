<?php

namespace App\Services\Moderation;

use App\Models\Listing;
use App\Models\User;

/**
 * When an ad must be reviewed (CD-06, LST-05), for a submission and for a material edit of a live
 * ad: mode `all` (launch) always; `new_users` until the seller has had an ad published; `none`
 * never. A flagged ad is always reviewed.
 */
class ModerationRules
{
    public const MODE_ALL = 'all';

    public const MODE_NEW_USERS = 'new_users';

    public const MODE_NONE = 'none';

    public function requiresReview(User $seller, Listing $listing, bool $flagged): bool
    {
        return match (config('marketplace.moderation_mode')) {
            self::MODE_NONE => $flagged,
            self::MODE_NEW_USERS => $flagged || ! Listing::withTrashed()->where('user_id', $seller->id)
                ->whereKeyNot($listing->id)->whereNotNull('published_at')->exists(),
            default => true,
        };
    }
}
