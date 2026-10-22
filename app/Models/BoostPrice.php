<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The price of a boost product in one country, in that country's currency (ADR-026, D2). No row:
 * not offered there.
 *
 * @property int $id
 * @property int $boost_product_id
 * @property int $country_id
 * @property string $currency
 * @property string $amount
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BoostPrice extends Model
{
    /** @var list<string> */
    protected $fillable = ['boost_product_id', 'country_id', 'currency', 'amount'];

    protected function casts(): array
    {
        return ['country_id' => 'integer', 'boost_product_id' => 'integer'];
    }

    /** @return BelongsTo<BoostProduct, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(BoostProduct::class, 'boost_product_id');
    }

    /** @return BelongsTo<Currency, $this> */
    public function currencyModel(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }
}
