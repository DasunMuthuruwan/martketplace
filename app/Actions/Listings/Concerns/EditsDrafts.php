<?php

namespace App\Actions\Listings\Concerns;

use App\Enums\ListingStatus;
use App\Exceptions\Listings\ListingNotEditable;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

trait EditsDrafts
{
    /**
     * Owner only (ListingPolicy::update) and only while the listing is a draft, or rejected
     * (the seller fixes it and submits again, architecture §4.2).
     *
     * @throws AuthorizationException
     * @throws ListingNotEditable
     */
    protected function authorizeDraft(User $user, Listing $listing): void
    {
        Gate::forUser($user)->authorize('update', $listing);

        if (! in_array($listing->status, [ListingStatus::Draft, ListingStatus::Rejected], true)) {
            throw new ListingNotEditable($listing->status);
        }
    }

    /** Rejects text containing HTML tags (LST-01: plain text only). */
    protected static function plainText(): string
    {
        return 'not_regex:/<[^>]*>/';
    }
}
