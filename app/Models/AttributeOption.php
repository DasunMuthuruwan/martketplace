<?php

namespace App\Models;

use App\Services\Catalogue\CatalogueCache;
use App\Support\CountryScope;
use Database\Factories\AttributeOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Translatable\HasTranslations;

/**
 * A choice of a select/multiselect attribute. `parent_option_id` links a dependent option to the
 * option it belongs to (Model "Aqua" → Make "Toyota"). `scope_countries` limits it (e.g. LK deed types).
 *
 * @property int $id
 * @property int $attribute_id
 * @property int|null $parent_option_id
 * @property string $value
 * @property string $label
 * @property int $sort_order
 * @property bool $is_active
 * @property list<string>|null $scope_countries
 */
class AttributeOption extends Model
{
    /** @use HasFactory<AttributeOptionFactory> */
    use HasFactory, HasTranslations;

    /** Reserved value for "Other" (free text) on attributes that allow it. */
    public const OTHER = 'other';

    public $timestamps = false;

    /** @var list<string> */
    public array $translatable = ['label'];

    /** @var list<string> */
    protected $fillable = ['attribute_id', 'parent_option_id', 'value', 'label', 'sort_order', 'is_active', 'scope_countries'];

    /** @var array<string, mixed> */
    protected $attributes = ['sort_order' => 0, 'is_active' => true];

    protected function casts(): array
    {
        return [
            'attribute_id' => 'integer',
            'parent_option_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'scope_countries' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (AttributeOption $option): void {
            $option->value = Str::slug((string) ($option->getAttribute('value') ?: $option->getTranslation('label', 'en', true)));
            if ($option->value === '' || $option->value === self::OTHER) {
                throw new InvalidArgumentException("Option value [{$option->value}] is empty or reserved.");
            }

            $option->scope_countries = CountryScope::normalise($option->scope_countries);
            $option->guardParent();
        });
        static::saved(fn () => CatalogueCache::flush());
        static::deleting(function (AttributeOption $option): void {
            if (ListingAttributeValue::where('value_option_id', $option->id)->exists()) {
                throw new InvalidArgumentException("Option [{$option->value}] is used by listings. Deactivate it instead (CAT-06).");
            }
        });
        static::deleted(fn () => CatalogueCache::flush());
    }

    /** @return BelongsTo<Attribute, $this> */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    /** @return BelongsTo<AttributeOption, $this> */
    public function parentOption(): BelongsTo
    {
        return $this->belongsTo(AttributeOption::class, 'parent_option_id');
    }

    public function isInScopeFor(string $iso2): bool
    {
        return CountryScope::includes($this->scope_countries, $iso2);
    }

    /** A dependent attribute's options must belong to an option of the attribute it depends on. */
    private function guardParent(): void
    {
        $attribute = Attribute::find($this->attribute_id);
        if ($attribute === null) {
            throw new InvalidArgumentException('Unknown attribute.');
        }

        if ($attribute->depends_on_attribute_id === null) {
            if ($this->parent_option_id !== null) {
                throw new InvalidArgumentException("Attribute [{$attribute->key}] does not depend on another attribute.");
            }

            return;
        }

        $parent = $this->parent_option_id === null ? null : self::find($this->parent_option_id);
        if ($parent === null || $parent->attribute_id !== $attribute->depends_on_attribute_id) {
            throw new InvalidArgumentException("Options of [{$attribute->key}] need a parent option of the attribute it depends on.");
        }
    }
}
