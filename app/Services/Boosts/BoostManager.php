<?php

namespace App\Services\Boosts;

use App\Enums\BoostType;
use App\Models\Listing;
use App\Models\ListingBoost;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Starts and ends boosts (ADR-026). Only live ads can be boosted (moderation first). A Bump moves
 * the ad back to the top of the newest order once; a Top ad or Urgent badge runs for a number of
 * days, queued after one of its type already running on the same ad, and never past the ad's expiry
 * (renew first). `listings.urgent_until` follows the Urgent boosts so cards need no query. Staff
 * grants and early ends are audited; paid boosts (B5) carry their order instead.
 */
class BoostManager
{
    public const MAX_DAYS = 90;

    public function __construct(private readonly AuditLogger $audit) {}

    /** @throws ValidationException */
    public function bump(Listing $listing, ?User $grantedBy = null, ?int $orderId = null): ListingBoost
    {
        $this->assertLive($listing);

        return DB::transaction(function () use ($listing, $grantedBy, $orderId): ListingBoost {
            // Query builder: moving the sort time is not an edit by the seller (updated_at stays).
            DB::table('listings')->where('id', $listing->id)->update(['sorted_at' => now()]);
            $listing->sorted_at = now();

            return $this->create($listing, BoostType::Bump, now(), null, $grantedBy, $orderId);
        });
    }

    /** @throws ValidationException */
    public function top(Listing $listing, int $days, ?User $grantedBy = null, ?int $orderId = null): ListingBoost
    {
        return $this->timed(BoostType::Top, $listing, $days, $grantedBy, $orderId);
    }

    /** @throws ValidationException */
    public function urgent(Listing $listing, int $days, ?User $grantedBy = null, ?int $orderId = null): ListingBoost
    {
        return DB::transaction(function () use ($listing, $days, $grantedBy, $orderId): ListingBoost {
            $boost = $this->timed(BoostType::Urgent, $listing, $days, $grantedBy, $orderId);
            $this->syncUrgent($listing->id);
            $listing->urgent_until = $boost->ends_at;

            return $boost;
        });
    }

    /** @throws ValidationException */
    private function timed(BoostType $type, Listing $listing, int $days, ?User $grantedBy, ?int $orderId): ListingBoost
    {
        $this->assertLive($listing);
        if ($days < 1 || $days > self::MAX_DAYS) {
            throw ValidationException::withMessages(['days' => __('boosts.days_range', ['max' => self::MAX_DAYS])]);
        }

        return DB::transaction(function () use ($type, $listing, $days, $grantedBy, $orderId): ListingBoost {
            Listing::whereKey($listing->id)->lockForUpdate()->first();
            $queuedUntil = $listing->boosts()->where('type', $type->value)->whereNull('ended_at')
                ->where('ends_at', '>', now())->max('ends_at');
            $start = $queuedUntil !== null ? Carbon::parse($queuedUntil) : now();
            $end = $start->copy()->addDays($days);
            if ($listing->expires_at !== null && $end->isAfter($listing->expires_at)) {
                throw ValidationException::withMessages(['days' => __('boosts.past_expiry', ['date' => $listing->expires_at->toFormattedDayDateString()])]);
            }

            return $this->create($listing, $type, $start, $end, $grantedBy, $orderId);
        });
    }

    /** Stop a boost before its end (refund, staff decision). */
    public function end(ListingBoost $boost, ?User $by = null, ?string $reason = null): void
    {
        if ($boost->ended_at !== null) {
            return;
        }
        $boost->forceFill(['ended_at' => now()])->save();
        if ($boost->type === BoostType::Urgent) {
            $this->syncUrgent($boost->listing_id);
        }
        if ($by !== null) {
            $this->audit->record($by, 'boost.ended', $boost->listing, $reason, ['boost' => $boost->id, 'type' => $boost->type->value]);
        }
    }

    /**
     * End every unfinished boost whose ad is no longer public (sold, deleted, expired, removed by a
     * moderator, owner suspended): the boost ends with the ad and does not come back with it.
     */
    public function endWhereAdNotLive(): int
    {
        $ended = ListingBoost::query()
            ->whereNull('ended_at')
            ->where('ends_at', '>', now())
            ->whereNotIn('listing_id', Listing::query()->public()->select('listings.id'))
            ->update(['ended_at' => now()]);

        // Includes badges simply past their end, so the column does not keep stale dates.
        DB::table('listings')->whereNotNull('urgent_until')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('listing_boosts')
                ->whereColumn('listing_boosts.listing_id', 'listings.id')
                ->where('listing_boosts.type', BoostType::Urgent->value)
                ->whereNull('listing_boosts.ended_at')
                ->where('listing_boosts.ends_at', '>', now()))
            ->update(['urgent_until' => null]);

        return $ended;
    }

    /** Query builder: a badge starting or ending is not an edit by the seller (updated_at stays). */
    private function syncUrgent(int $listingId): void
    {
        $until = ListingBoost::where('listing_id', $listingId)->where('type', BoostType::Urgent->value)
            ->whereNull('ended_at')->where('ends_at', '>', now())->max('ends_at');
        DB::table('listings')->where('id', $listingId)->update(['urgent_until' => $until]);
    }

    private function create(Listing $listing, BoostType $type, Carbon $start, ?Carbon $end, ?User $grantedBy, ?int $orderId): ListingBoost
    {
        $boost = new ListingBoost(['type' => $type, 'starts_at' => $start, 'ends_at' => $end]);
        $boost->listing()->associate($listing);
        $boost->forceFill(['granted_by' => $grantedBy?->id, 'order_id' => $orderId])->save();

        if ($grantedBy !== null && $orderId === null) {
            $this->audit->record($grantedBy, 'boost.granted', $listing, null, ['boost' => $boost->id, 'type' => $type->value, 'ends_at' => $end?->toIso8601String()]);
        }

        return $boost;
    }

    /** @throws ValidationException */
    private function assertLive(Listing $listing): void
    {
        if (! Listing::query()->public()->whereKey($listing->id)->exists()) {
            throw ValidationException::withMessages(['listing' => __('boosts.not_live')]);
        }
    }
}
