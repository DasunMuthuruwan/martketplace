<?php

namespace App\Models;

use App\Enums\AttributeType;
use App\Services\Catalogue\CatalogueCache;
use Database\Factories\AttributeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use Spatie\Translatable\HasTranslations;

/**
 * A category-specific listing field (category tree doc §2–§3). Defined once, attached to
 * categories through `category_attribute`. Deactivate instead of deleting once used (CAT-06).
 *
 * @property int $id
 * @property string $key
 * @property string $label
 * @property AttributeType $type
 * @property string|null $unit display unit for plain numbers (km, cc, GB)
 * @property string|null $dimension "area": entered as value + a unit offered by the listing country
 * @property array<string, mixed>|null $rules {min, max, step, maxlength, max_years_ahead, units (area: allowed unit codes)}
 * @property int|null $depends_on_attribute_id
 * @property bool $allows_other
 * @property string|null $help_text
 * @property bool $is_active
 */
class Attribute extends Model
{
    /** @use HasFactory<AttributeFactory> */
    use HasFactory, HasTranslations;

    /** @var list<string> */
    public array $translatable = ['label', 'help_text'];

    /** @var list<string> */
    protected $fillable = ['key', 'label', 'type', 'unit', 'dimension', 'rules', 'depends_on_attribute_id', 'allows_other', 'help_text', 'is_active'];

    /** @var array<string, mixed> */
    protected $attributes = ['allows_other' => false, 'is_active' => true];

    protected function casts(): array
    {
        return [
            'type' => AttributeType::class,
            'rules' => 'array',
            'depends_on_attribute_id' => 'integer',
            'allows_other' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Attribute $attribute): void {
            if (! preg_match('/^[a-z][a-z0-9_]{0,59}$/', $attribute->key)) {
                throw new InvalidArgumentException("Attribute key [{$attribute->key}] must be snake_case (used in filter URLs).");
            }
            if ($attribute->dimension !== null && ($attribute->dimension !== MeasurementUnit::AREA || $attribute->type !== AttributeType::Number)) {
                throw new InvalidArgumentException('Only number attributes can have a dimension, and only "area" is supported.');
            }
            if ($attribute->depends_on_attribute_id !== null) {
                $parent = self::find($attribute->depends_on_attribute_id);
                if ($attribute->type !== AttributeType::Select || $parent === null || $parent->type !== AttributeType::Select || $parent->is($attribute)) {
                    throw new InvalidArgumentException('Only a select can depend on another select.');
                }
            }
        });
        static::saved(fn () => CatalogueCache::flush());
        static::deleting(function (Attribute $attribute): void {
            if (ListingAttributeValue::where('attribute_id', $attribute->id)->exists()) {
                throw new InvalidArgumentException("Attribute [{$attribute->key}] is used by listings. Deactivate it instead (CAT-06).");
            }
        });
        static::deleted(fn () => CatalogueCache::flush());
    }

    /** @return HasMany<AttributeOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(AttributeOption::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return BelongsTo<Attribute, $this> */
    public function dependsOn(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, 'depends_on_attribute_id');
    }

    /** @return BelongsToMany<Category, $this, CategoryAttribute> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_attribute')->using(CategoryAttribute::class)->withPivot(CategoryAttribute::COLUMNS);
    }
}
