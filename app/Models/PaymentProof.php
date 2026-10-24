<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Proof of a bank transfer attached to a promotion order (B7). The file is on the private disk and
 * is only ever streamed through PaymentProofs::response(). Set through PaymentProofs only.
 *
 * @property int $id
 * @property int $boost_order_id
 * @property int|null $user_id
 * @property string $kind
 * @property string $path
 * @property string $original_name
 * @property int $bytes
 * @property string $sha256
 * @property Carbon $created_at
 */
class PaymentProof extends Model
{
    public const IMAGE = 'image';

    public const PDF = 'pdf';

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'boost_order_id' => 'integer',
            'bytes' => 'integer',
        ];
    }

    /** @return BelongsTo<BoostOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(BoostOrder::class, 'boost_order_id');
    }

    public function isPdf(): bool
    {
        return $this->kind === self::PDF;
    }
}
