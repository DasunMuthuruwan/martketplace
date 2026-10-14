<?php

namespace App\Models;

use Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * City or town, attached to the deepest region level of its country.
 *
 * @property int $id
 * @property int $country_id
 * @property int|null $region_id
 * @property string $name
 * @property string $ascii_name
 * @property string $slug
 * @property string|null $latitude
 * @property string|null $longitude
 * @property int|null $population
 * @property int|null $geonames_id
 * @property bool $is_active
 * @property bool $is_curated
 */
class City extends Model
{
    /** @use HasFactory<CityFactory> */
    use HasFactory, HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @var list<string> */
    protected $fillable = [
        'country_id', 'region_id', 'name', 'ascii_name', 'slug', 'latitude', 'longitude',
        'population', 'geonames_id', 'is_active', 'is_curated',
    ];

    protected function casts(): array
    {
        return [
            'country_id' => 'integer',
            'region_id' => 'integer',
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'population' => 'integer',
            'geonames_id' => 'integer',
            'is_active' => 'boolean',
            'is_curated' => 'boolean',
        ];
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** @return HasMany<Listing, $this> */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /** @return HasMany<Locality, $this> */
    public function localities(): HasMany
    {
        return $this->hasMany(Locality::class);
    }

    /** @param Builder<City> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
