<?php

namespace App\Actions\Listings\Concerns;

use App\Enums\ListingStatus;
use App\Exceptions\Listings\ListingNotEditable;
use App\Models\Listing;
use App\Models\User;
use App\Services\Moderation\ListingScreener;
use App\Services\Moderation\ModerationRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

trait EditsDrafts
{
    /**
     * Owner only (ListingPolicy::update) and only while the listing is a draft, or rejected
     * (the seller fixes it and submits again, architecture §4.2). Used by submission.
     *
     * @throws AuthorizationException
     * @throws ListingNotEditable
     */
    protected function authorizeDraft(User $user, Listing $listing): void
    {
        $this->authorizeStatus($user, $listing, [ListingStatus::Draft, ListingStatus::Rejected]);
    }

    /**
     * The wizard steps also edit ads that are waiting for review or live (LST-06). Sold, expired
     * and suspended ads are not edited (renew or ask staff first).
     *
     * @throws AuthorizationException
     * @throws ListingNotEditable
     */
    protected function authorizeEdit(User $user, Listing $listing): void
    {
        $this->authorizeStatus($user, $listing, [ListingStatus::Draft, ListingStatus::Rejected, ListingStatus::PendingReview, ListingStatus::Active]);
    }

    /** Submitted ads (waiting for review or live) are already checked as a whole. */
    protected static function isSubmitted(Listing $listing): bool
    {
        return in_array($listing->status, [ListingStatus::PendingReview, ListingStatus::Active], true);
    }

    /**
     * After a material change (title, description, type, attributes, category, location, photos)
     * of a submitted ad (LST-06, CD-06): screen it again (blocked terms refuse the change: call
     * inside the step's transaction so it rolls back) and, when the rules require it, take a live
     * ad back to review until it is approved again.
     *
     * @throws ValidationException
     */
    protected function afterMaterialChange(Listing $listing): void
    {
        if (! self::isSubmitted($listing)) {
            return;
        }

        $screening = app(ListingScreener::class)->screen($listing);
        if ($screening->isBlocked()) {
            throw ValidationException::withMessages(['description' => __('moderation.blocked_terms', ['terms' => implode(', ', $screening->blocked)])]);
        }

        $listing->flagged = $screening->isFlagged();
        if ($listing->status === ListingStatus::Active && app(ModerationRules::class)->requiresReview($listing->user, $listing, $screening->isFlagged())) {
            $listing->forceFill(['status' => ListingStatus::PendingReview, 'submitted_at' => now()]);
        }
        $listing->save();
    }

    /** Rejects text containing HTML tags (LST-01: plain text only). */
    protected static function plainText(): string
    {
        return 'not_regex:/<[^>]*>/';
    }

    /**
     * @param  list<ListingStatus>  $allowed
     *
     * @throws AuthorizationException
     * @throws ListingNotEditable
     */
    private function authorizeStatus(User $user, Listing $listing, array $allowed): void
    {
        Gate::forUser($user)->authorize('update', $listing);

        if (! in_array($listing->status, $allowed, true)) {
            throw new ListingNotEditable($listing->status);
        }
    }
}
