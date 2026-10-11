<?php

namespace App\Models;

use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;

/**
 * Country master data (ISO 3166-1). All countries are seeded inactive; the Owner activates them.
 * Labels for location levels are per country; empty labels fall back to lang/en/geo.php.
 *
 * @property int $id
 * @property string $iso2
 * @property string $iso3
 * @property string $name
 * @property string $slug
 * @property string|null $default_currency
 * @property string $number_locale
 * @property string|null $phone_country_code
 * @property string|null $timezone
 * @property int $region_levels
 * @property bool $require_region
 * @property bool $require_city
 * @property bool $is_active
 * @property int $sort_order
 * @property int|null $geonames_id
 */
class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory, HasTranslations;

    /** Location levels whose label can be set per country. */
    public const LABEL_LEVELS = ['region', 'subregion', 'city', 'locality'];

    /** @var list<string> */
    public array $translatable = ['name', 'region_label', 'subregion_label', 'city_label', 'locality_label'];

    /** @var list<string> */
    protected $fillable = [
        'iso2', 'iso3', 'name', 'slug', 'default_currency', 'number_locale', 'phone_country_code', 'timezone',
        'region_levels', 'region_label', 'subregion_label', 'city_label', 'locality_label',
        'require_region', 'require_city', 'is_active', 'sort_order', 'geonames_id',
    ];

    protected function casts(): array
    {
        return [
            'region_levels' => 'integer',
            'require_region' => 'boolean',
            'require_city' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'geonames_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function defaultCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'default_currency', 'code');
    }

    /**
     * Currencies a listing in this country may use (MVP: the default only).
     *
     * @return BelongsToMany<Currency, $this>
     */
    public function currencies(): BelongsToMany
    {
        return $this->belongsToMany(Currency::class, 'country_currencies', 'country_id', 'currency_code', 'id', 'code')
            ->withPivot('is_default');
    }

    /**
     * @param  Builder<Country>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Country-appropriate label for a location level (e.g. "Province", "State"), with a generic fallback.
     */
    public function label(string $level): string
    {
        if (! in_array($level, self::LABEL_LEVELS, true)) {
            throw new \InvalidArgumentException("Unknown location level [{$level}].");
        }

        $label = $this->getTranslation("{$level}_label", app()->getLocale(), true);

        return filled($label) ? (string) $label : __("geo.labels.{$level}");
    }
}
