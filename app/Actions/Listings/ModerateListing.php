<?php

namespace App\Actions\Listings;

use App\Authorization\Permissions;
use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\ListingApproved;
use App\Notifications\ListingRejected;
use App\Services\Audit\AuditLogger;
use App\Services\Listings\ListingPublisher;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Moderation decisions on ads waiting for review (MOD-01, MOD-04, MOD-05): approve, reject with a
 * reason, or request changes (also back to the seller as "rejected", with what to change). Needs
 * `listings.moderate`; staff never moderate their own ads. The listing row is locked so two
 * moderators cannot decide at once. Every decision is audited and the seller is notified.
 */
class ModerateListing
{
    public const REASON_MAX = 500;

    public function __construct(
        private readonly ListingPublisher $publisher,
        private readonly AuditLogger $audit,
    ) {}

    public function approve(User $moderator, Listing $listing): Listing
    {
        return $this->decide($moderator, $listing, 'listing.approved', null, function (Listing $locked): void {
            $this->publisher->publish($locked);
        });
    }

    /** @throws ValidationException */
    public function reject(User $moderator, Listing $listing, ?string $reason): Listing
    {
        return $this->decide($moderator, $listing, 'listing.rejected', $this->reason($reason), function (Listing $locked) use ($reason): void {
            $locked->forceFill(['status' => ListingStatus::Rejected, 'moderation_note' => trim((string) $reason)])->save();
        });
    }

    /** @throws ValidationException */
    public function requestChanges(User $moderator, Listing $listing, ?string $changes): Listing
    {
        return $this->decide($moderator, $listing, 'listing.changes_requested', $this->reason($changes), function (Listing $locked) use ($changes): void {
            $locked->forceFill(['status' => ListingStatus::Rejected, 'moderation_note' => trim((string) $changes)])->save();
        });
    }

    /**
     * @param  callable(Listing): void  $apply
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    private function decide(User $moderator, Listing $listing, string $action, ?string $reason, callable $apply): Listing
    {
        if (! $moderator->can(Permissions::LISTINGS_MODERATE) || $listing->user_id === $moderator->id) {
            throw new AuthorizationException;
        }

        $decided = DB::transaction(function () use ($moderator, $listing, $action, $reason, $apply): Listing {
            $locked = Listing::whereKey($listing->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== ListingStatus::PendingReview) {
                throw ValidationException::withMessages(['status' => __('moderation.already_decided')]);
            }

            $apply($locked);
            $locked->forceFill(['moderated_by' => $moderator->id, 'moderated_at' => now()])->save();
            $this->audit->record($moderator, $action, $locked, $reason, ['status' => [ListingStatus::PendingReview->value, $locked->status->value]]);

            return $locked;
        });

        $decided->user->notify($action === 'listing.approved' ? new ListingApproved($decided) : new ListingRejected($decided, $action === 'listing.changes_requested'));

        return $decided;
    }

    /** @throws ValidationException */
    private function reason(?string $reason): string
    {
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:'.self::REASON_MAX]])->validate();

        return trim((string) $reason);
    }
}
