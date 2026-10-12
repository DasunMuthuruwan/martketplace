<?php

namespace App\Actions\Listings;

use App\Authorization\Permissions;
use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\ListingSuspended;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Staff take a live or pending ad down (e.g. after a report) and put it back (architecture §4.2).
 * Needs `listings.suspend`, never on one's own ad; audited; the seller is told why.
 * Reinstating restores a published ad as live (still hidden if it expired meanwhile: public scope)
 * and sends a never-approved ad back to review.
 */
class SuspendListing
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @throws AuthorizationException|ValidationException */
    public function suspend(User $staff, Listing $listing, ?string $reason): Listing
    {
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:'.ModerateListing::REASON_MAX]])->validate();

        $suspended = $this->change($staff, $listing, [ListingStatus::Active, ListingStatus::PendingReview], ListingStatus::Suspended, 'listing.suspended', trim((string) $reason));
        $suspended->user->notify(new ListingSuspended($suspended));

        return $suspended;
    }

    /** @throws AuthorizationException|ValidationException */
    public function reinstate(User $staff, Listing $listing, ?string $reason = null): Listing
    {
        return $this->change($staff, $listing, [ListingStatus::Suspended], ListingStatus::Active, 'listing.reinstated', $reason === null ? null : trim($reason));
    }

    /**
     * @param  list<ListingStatus>  $from
     *
     * @throws AuthorizationException|ValidationException
     */
    private function change(User $staff, Listing $listing, array $from, ListingStatus $to, string $action, ?string $reason): Listing
    {
        if (! $staff->can(Permissions::LISTINGS_SUSPEND) || $listing->user_id === $staff->id) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($staff, $listing, $from, $to, $action, $reason): Listing {
            $locked = Listing::whereKey($listing->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, $from, true)) {
                throw ValidationException::withMessages(['status' => __('moderation.already_decided')]);
            }

            // An ad suspended while waiting for review was never approved: it goes back to review.
            if ($to === ListingStatus::Active && $locked->published_at === null) {
                $to = ListingStatus::PendingReview;
            }

            $previous = $locked->status;
            $locked->forceFill([
                'status' => $to,
                'moderation_note' => $to === ListingStatus::Suspended ? $reason : null,
                'moderated_by' => $staff->id,
                'moderated_at' => now(),
            ])->save();

            $this->audit->record($staff, $action, $locked, $reason, ['status' => [$previous->value, $to->value]]);

            return $locked;
        });
    }
}
