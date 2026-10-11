<?php

namespace App\Models;

use App\Services\Catalogue\CatalogueCache;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A unit offered by a country in area inputs (global design §5).
 *
 * @property int $country_id
 * @property int $measurement_unit_id
 * @property bool $is_default
 * @property int $sort_order
 */
class CountryMeasurementUnit extends Pivot
{
    protected $table = 'country_measurement_units';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => CatalogueCache::flush());
        static::deleted(fn () => CatalogueCache::flush());
    }
}
