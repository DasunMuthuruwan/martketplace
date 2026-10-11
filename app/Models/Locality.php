<?php

namespace App\Models;

use Database\Factories\LocalityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * Optional curated neighbourhood within a city.
 *
 * @property int $id
 * @property int $city_id
 * @property string $name
 * @property string $slug
 * @property bool $is_active
 */
class Locality extends Model
{
    /** @use HasFactory<LocalityFactory> */
    use HasFactory, HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @var list<string> */
    protected $fillable = ['city_id', 'name', 'slug', 'is_active'];

    protected function casts(): array
    {
        return ['city_id' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @param Builder<Locality> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
