<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;

/**
 * Seller-side listing checks (authorization doc §2). Staff abilities (edit-any, moderate) are
 * added with the moderation work in T4.4/M6, always through permissions, never role names.
 */
class ListingPolicy
{
    /** Posting needs an active account with a verified email (AUTH acceptance criteria). */
    public function create(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail();
    }

    /** Only the owner edits their listing; a deleted listing is read-only. */
    public function update(User $user, Listing $listing): bool
    {
        return $this->create($user) && $listing->user_id === $user->id && ! $listing->trashed();
    }
}
