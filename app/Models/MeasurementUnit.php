<?php

namespace App\Models;

use App\Services\Catalogue\CatalogueCache;
use Database\Factories\MeasurementUnitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * A unit of a dimension (area today) with its exact factor to the base unit (m²).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $symbol
 * @property string $dimension
 * @property string $factor_to_base decimal string, never a float
 * @property int $sort_order
 * @property bool $is_active
 */
class MeasurementUnit extends Model
{
    /** @use HasFactory<MeasurementUnitFactory> */
    use HasFactory, HasTranslations;

    public const AREA = 'area';

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @var list<string> */
    protected $fillable = ['code', 'name', 'symbol', 'dimension', 'factor_to_base', 'sort_order', 'is_active'];

    /** @var array<string, mixed> */
    protected $attributes = ['dimension' => self::AREA, 'sort_order' => 0, 'is_active' => true];

    protected function casts(): array
    {
        return [
            'factor_to_base' => 'decimal:10',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Area attribute schemas embed each country's units.
        static::saved(fn () => CatalogueCache::flush());
        static::deleted(fn () => CatalogueCache::flush());
    }

    /** @param  Builder<MeasurementUnit>  $query */
    public function scopeArea(Builder $query): void
    {
        $query->where('dimension', self::AREA);
    }

    /** @param  Builder<MeasurementUnit>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
