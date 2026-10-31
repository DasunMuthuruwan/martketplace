<?php

namespace App\Models;

use App\Enums\BannerPlacement;
use App\Support\ListingsDisk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A banner managed by staff (B10). Shown while switched on and inside its dates, in its country
 * (null = every country) and placement.
 *
 * @property int $id
 * @property string $title
 * @property string $image_path
 * @property int $width
 * @property int $height
 * @property string|null $link_url
 * @property int|null $country_id
 * @property BannerPlacement $placement
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_active
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Banner extends Model
{
    /** @var list<string> */
    protected $fillable = ['title', 'link_url', 'country_id', 'placement', 'starts_at', 'ends_at', 'is_active'];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'country_id' => 'integer',
            'placement' => BannerPlacement::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Switched on and inside its dates.
     *
     * @param  Builder<Banner>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function imageUrl(): string
    {
        return ListingsDisk::disk()->url($this->image_path);
    }

    /** off | scheduled | ended | live */
    public function state(): string
    {
        return match (true) {
            ! $this->is_active => 'off',
            $this->starts_at?->isFuture() === true => 'scheduled',
            $this->ends_at?->isPast() === true => 'ended',
            default => 'live',
        };
    }
}
