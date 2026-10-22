<?php

namespace App\Models;

use App\Enums\BoostType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Bump or Top ad on a listing (ADR-026). Granted by staff (`granted_by`) or paid by an order
 * (`order_id`, B5). A boost is running between `starts_at` and `ends_at` unless `ended_at` stopped
 * it early (the ad stopped being public, a refund).
 *
 * @property int $id
 * @property int $listing_id
 * @property BoostType $type
 * @property int|null $order_id
 * @property int|null $granted_by
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $last_shown_at
 * @property Carbon|null $ending_reminder_sent_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ListingBoost extends Model
{
    /** @var list<string> */
    protected $fillable = ['type', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return [
            'type' => BoostType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_shown_at' => 'datetime',
            'ending_reminder_sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<BoostOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(BoostOrder::class, 'order_id');
    }

    /** @return BelongsTo<User, $this> */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * Running now: started, not past its end, not ended early.
     *
     * @param  Builder<ListingBoost>  $query
     */
    public function scopeRunning(Builder $query): void
    {
        $query->where('listing_boosts.starts_at', '<=', now())
            ->where('listing_boosts.ends_at', '>', now())
            ->whereNull('listing_boosts.ended_at');
    }

    public function isRunning(): bool
    {
        return $this->ended_at === null && $this->starts_at->isPast() && $this->ends_at !== null && $this->ends_at->isFuture();
    }
}
