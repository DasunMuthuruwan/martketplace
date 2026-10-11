<?php

namespace App\Models;

use App\Enums\DisplayDecimals;
use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;

/**
 * ISO 4217 currency. Formatting lives in the Money service (T1.5); this model only holds data.
 *
 * @property string $code
 * @property string|null $numeric_code
 * @property string $name
 * @property int $minor_units
 * @property string|null $symbol_override
 * @property DisplayDecimals $display_decimals
 * @property bool $is_active
 */
class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory, HasTranslations;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @var list<string> */
    protected $fillable = ['code', 'numeric_code', 'name', 'minor_units', 'symbol_override', 'display_decimals', 'is_active'];

    protected function casts(): array
    {
        return [
            'minor_units' => 'integer',
            'display_decimals' => DisplayDecimals::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Country, $this>
     */
    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'country_currencies', 'currency_code', 'country_id', 'code', 'id')
            ->withPivot('is_default');
    }

    /**
     * @param  Builder<Currency>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
