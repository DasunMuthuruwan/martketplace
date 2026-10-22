<?php

namespace App\Models;

use App\Enums\BoostType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Something a seller can buy (ADR-026, D1): a Bump, or a Top ad for a number of days. Inserted by
 * migration; the Owner switches products on or off and sets their price per country.
 *
 * @property int $id
 * @property BoostType $type
 * @property int|null $duration_days
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BoostProduct extends Model
{
    /** @var list<string> */
    protected $fillable = ['is_active'];

    protected function casts(): array
    {
        return [
            'type' => BoostType::class,
            'duration_days' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<BoostPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(BoostPrice::class);
    }

    /** "Bump", "Top ad · 7 days" */
    public function name(): string
    {
        return $this->type === BoostType::Bump
            ? (string) __('boosts.products.bump')
            : (string) trans_choice('boosts.products.top', (int) $this->duration_days, ['days' => $this->duration_days]);
    }
}
