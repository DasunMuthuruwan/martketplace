<?php

namespace App\Actions\Listings;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Seller lifecycle actions that apply at once (LST-06, LST-07, LST-08): mark sold, renew, delete.
 * Owner only (ListingPolicy::update). Renewal keeps the listing's moderation state: a live ad is
 * extended, an expired ad that was approved before comes back live without a new review.
 */
class ListingLifecycle
{
    /** @throws AuthorizationException|ValidationException */
    public function markSold(User $user, Listing $listing): Listing
    {
        Gate::forUser($user)->authorize('update', $listing);

        if ($listing->status !== ListingStatus::Active) {
            throw ValidationException::withMessages(['status' => __('listings.cannot_sell')]);
        }

        $listing->forceFill(['status' => ListingStatus::Sold, 'sold_at' => now()])->save();

        return $listing->fresh();
    }

    /**
     * Live ads can be renewed in the last `renew_window_days` before expiry; expired ads (by
     * status or by date) within `retention.expired_listing_renewable` days after expiry.
     *
     * @throws AuthorizationException|ValidationException
     */
    public function renew(User $user, Listing $listing): Listing
    {
        Gate::forUser($user)->authorize('update', $listing);

        $expired = $listing->status === ListingStatus::Expired
            || ($listing->status === ListingStatus::Active && $listing->expires_at !== null && $listing->expires_at->isPast());
        $window = (int) config('marketplace.listings.renew_window_days');

        if ($listing->expires_at === null || ! in_array($listing->status, [ListingStatus::Active, ListingStatus::Expired], true)) {
            throw ValidationException::withMessages(['status' => __('listings.cannot_renew')]);
        }
        if (! $expired && $listing->expires_at->isAfter(now()->addDays($window))) {
            throw ValidationException::withMessages(['status' => __('listings.renew_too_early', ['days' => $window])]);
        }
        if ($expired && $listing->expires_at->isBefore(now()->subDays((int) config('marketplace.retention.expired_listing_renewable')))) {
            throw ValidationException::withMessages(['status' => __('listings.cannot_renew')]);
        }

        return DB::transaction(function () use ($user, $listing, $expired): Listing {
            if ($expired) {
                // Coming back counts toward the live-ad limit (LST-09).
                User::whereKey($user->id)->lockForUpdate()->first();
                $limit = (int) config('marketplace.listings.max_active_per_user');
                $live = Listing::where('user_id', $user->id)->whereKeyNot($listing->id)
                    ->whereIn('status', [ListingStatus::Active->value, ListingStatus::PendingReview->value])->count();
                if ($live >= $limit) {
                    throw ValidationException::withMessages(['status' => __('moderation.active_limit', ['limit' => $limit])]);
                }
            }

            $listing->forceFill([
                'status' => ListingStatus::Active,
                'expires_at' => now()->addDays((int) config('marketplace.listings.expiry_days')),
                'renewed_at' => now(),
                'expiry_reminder_sent_at' => null,
            ])->save();

            return $listing->fresh();
        });
    }

    /**
     * Whether renew() would accept this listing now (ignoring the live-ad limit), so screens only
     * offer the action when it can work. Mirrors the checks in renew().
     */
    public function renewable(Listing $listing): bool
    {
        if ($listing->expires_at === null || ! in_array($listing->status, [ListingStatus::Active, ListingStatus::Expired], true)) {
            return false;
        }
        $expired = $listing->status === ListingStatus::Expired || $listing->expires_at->isPast();

        return $expired
            ? ! $listing->expires_at->isBefore(now()->subDays((int) config('marketplace.retention.expired_listing_renewable')))
            : ! $listing->expires_at->isAfter(now()->addDays((int) config('marketplace.listings.renew_window_days')));
    }

    /** Soft delete (LST-07); purged later by the retention job. Any status, owner only. */
    public function delete(User $user, Listing $listing): void
    {
        Gate::forUser($user)->authorize('update', $listing);

        $listing->delete();
    }
}
