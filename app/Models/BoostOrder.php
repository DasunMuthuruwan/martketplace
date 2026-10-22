<?php

namespace App\Models;

use App\Enums\BoostOrderStatus;
use App\Enums\BoostType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A seller's order for a boost, paid by bank transfer (ADR-026, D3). Product, price and currency are
 * copied at ordering time. Set through BoostOrders only (no mass assignment).
 *
 * @property int $id
 * @property string $reference
 * @property int|null $user_id
 * @property int|null $listing_id
 * @property int|null $boost_product_id
 * @property BoostType $product_type
 * @property int|null $duration_days
 * @property int $country_id
 * @property string $currency
 * @property string $amount
 * @property string $method
 * @property BoostOrderStatus $status
 * @property Carbon|null $expires_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $closed_at
 * @property int|null $handled_by
 * @property string|null $note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BoostOrder extends Model
{
    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'product_type' => BoostType::class,
            'duration_days' => 'integer',
            'country_id' => 'integer',
            'status' => BoostOrderStatus::class,
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class)->withTrashed();
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<Currency, $this> */
    public function currencyModel(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }

    /** @return BelongsTo<User, $this> */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** @return HasOne<ListingBoost, $this> */
    public function boost(): HasOne
    {
        return $this->hasOne(ListingBoost::class, 'order_id');
    }

    /** "Bump", "Top ad · 7 days" (from the copied product, not the current one). */
    public function productName(): string
    {
        return $this->product_type === BoostType::Bump
            ? (string) __('boosts.products.bump')
            : (string) trans_choice('boosts.products.top', (int) $this->duration_days, ['days' => $this->duration_days]);
    }
}
