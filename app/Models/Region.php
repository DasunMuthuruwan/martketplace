<?php

namespace App\Models;

use Database\Factories\RegionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * Administrative division. Level 1 = state/province/nation; level 2 = district/county/area.
 * The term shown to users comes from the country (Country::label()).
 *
 * @property int $id
 * @property int $country_id
 * @property int|null $parent_id
 * @property int $level
 * @property string $name
 * @property string $slug
 * @property string|null $code
 * @property int|null $geonames_id
 * @property bool $is_active
 * @property int $sort_order
 */
class Region extends Model
{
    /** @use HasFactory<RegionFactory> */
    use HasFactory, HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @var list<string> */
    protected $fillable = ['country_id', 'parent_id', 'level', 'name', 'slug', 'code', 'geonames_id', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'country_id' => 'integer',
            'parent_id' => 'integer',
            'level' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'geonames_id' => 'integer',
        ];
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<Region, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'parent_id');
    }

    /** @return HasMany<Region, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Region::class, 'parent_id');
    }

    /** @return HasMany<City, $this> */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    /** @param Builder<Region> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
