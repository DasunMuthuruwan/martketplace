<?php

namespace App\Models;

use App\Enums\CategoryModeration;
use App\Enums\ListingType;
use App\Enums\PriceMode;
use App\Enums\PriceType;
use App\Exceptions\InvalidCategoryTree;
use App\Services\Catalogue\CatalogueCache;
use App\Support\CountryScope;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

/**
 * Two-level category tree (category tree doc §1). Listings are posted to leaves (T4.1 adds the
 * listings check). One global tree; `scope_countries` limits a category to some countries (§6).
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $icon
 * @property int $sort_order
 * @property bool $is_active
 * @property list<string>|null $scope_countries
 * @property list<string> $allowed_types
 * @property PriceMode $price_mode
 * @property bool $allow_free
 * @property list<string>|null $allowed_price_types
 * @property int|null $unit_price_attribute_id attribute used for the calculated price per unit (Land → land_size)
 * @property bool $show_condition
 * @property int $max_images
 * @property CategoryModeration $moderation
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasTranslations;

    public const REDIRECT_TYPE = 'category';

    /** @var list<string> */
    public array $translatable = ['name', 'description', 'meta_title', 'meta_description'];

    /** @var list<string> */
    protected $fillable = [
        'parent_id', 'name', 'slug', 'description', 'icon', 'sort_order', 'is_active', 'scope_countries',
        'allowed_types', 'price_mode', 'allow_free', 'allowed_price_types', 'unit_price_attribute_id', 'show_condition', 'max_images',
        'moderation', 'meta_title', 'meta_description',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
        'price_mode' => 'required',
        'allow_free' => false,
        'show_condition' => true,
        'max_images' => 8,
        'moderation' => 'inherit',
    ];

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'scope_countries' => 'array',
            'allowed_types' => 'array',
            'price_mode' => PriceMode::class,
            'allow_free' => 'boolean',
            'allowed_price_types' => 'array',
            'unit_price_attribute_id' => 'integer',
            'show_condition' => 'boolean',
            'max_images' => 'integer',
            'moderation' => CategoryModeration::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Category $category): void {
            $category->normaliseAndGuard();
        });

        static::updated(function (Category $category): void {
            if ($category->wasChanged('slug')) {
                SlugRedirect::updateOrCreate(
                    ['type' => self::REDIRECT_TYPE, 'old_slug' => $category->getOriginal('slug')],
                    ['target_id' => $category->id],
                );
                // Earlier redirects to this category now point at the new slug automatically (by id).
            }
        });

        static::saved(function (Category $category): void {
            // A slug in use again is no longer a redirect.
            SlugRedirect::where('type', self::REDIRECT_TYPE)->where('old_slug', $category->slug)->delete();
            CatalogueCache::flush();
        });

        static::deleting(function (Category $category): void {
            if ($category->children()->exists()) {
                throw InvalidCategoryTree::hasChildren();
            }
            if ($category->listings()->withTrashed()->exists()) {
                throw InvalidCategoryTree::hasListings();
            }
        });

        static::deleted(function (Category $category): void {
            SlugRedirect::where('type', self::REDIRECT_TYPE)->where('target_id', $category->id)->delete();
            CatalogueCache::flush();
        });
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<Listing, $this> */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /**
     * Attributes attached directly to this category (a leaf also inherits its parent's: see AttributeSchema).
     *
     * @return BelongsToMany<Attribute, $this, CategoryAttribute>
     */
    public function attributeDefinitions(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'category_attribute')->using(CategoryAttribute::class)->withPivot(CategoryAttribute::COLUMNS);
    }

    /** @param  Builder<Category>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param  Builder<Category>  $query */
    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * Categories offered in a country by their own scope (a leaf also needs its parent in scope:
     * see isAvailableIn() and CategoryTree).
     *
     * @param  Builder<Category>  $query
     */
    public function scopeInScopeFor(Builder $query, string $iso2): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('scope_countries')->orWhereJsonContains('scope_countries', strtoupper($iso2)));
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function isLeaf(): bool
    {
        return $this->relationLoaded('children') ? $this->children->isEmpty() : ! $this->children()->exists();
    }

    public function isInScopeFor(string $iso2): bool
    {
        return CountryScope::includes($this->scope_countries, $iso2);
    }

    /** Active and in scope, together with its parent. */
    public function isAvailableIn(string $iso2): bool
    {
        if (! $this->is_active || ! $this->isInScopeFor($iso2)) {
            return false;
        }

        return $this->parent === null || $this->parent->isAvailableIn($iso2);
    }

    /** @return list<ListingType> */
    public function listingTypes(): array
    {
        return array_map(fn (string $type) => ListingType::from($type), $this->allowed_types);
    }

    public function allowsListingType(ListingType $type): bool
    {
        return in_array($type->value, $this->allowed_types, true);
    }

    /** Free needs allow_free; a configured list restricts the rest (e.g. Land: fixed, negotiable). */
    public function allowsPriceType(PriceType $type): bool
    {
        if ($this->price_mode === PriceMode::None) {
            return false;
        }
        if ($type === PriceType::Free && ! $this->allow_free) {
            return false;
        }

        return $this->allowed_price_types === null || in_array($type->value, $this->allowed_price_types, true);
    }

    /** @return list<PriceType> */
    public function priceTypes(): array
    {
        return array_values(array_filter(PriceType::cases(), fn (PriceType $type) => $this->allowsPriceType($type)));
    }

    /**
     * Categories whose ads are hidden: switched off, or under a switched-off parent. Cached in the
     * catalogue cache generation, which every category change renews.
     *
     * @return list<int>
     */
    public static function hiddenIds(): array
    {
        return CatalogueCache::remember('hidden-category-ids', function (): array {
            $off = self::where('is_active', false)->pluck('id');

            return self::whereIn('id', $off)->orWhereIn('parent_id', $off)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        });
    }

    /** Current category for a slug, following renamed-slug redirects. */
    public static function findBySlugOrRedirect(string $slug): ?self
    {
        $category = self::where('slug', $slug)->first();
        if ($category !== null) {
            return $category;
        }

        $redirect = SlugRedirect::where('type', self::REDIRECT_TYPE)->where('old_slug', $slug)->first();

        return $redirect === null ? null : self::find($redirect->target_id);
    }

    private function normaliseAndGuard(): void
    {
        if (blank($this->getAttribute('slug'))) { // not set yet on a new category
            $this->slug = $this->uniqueSlug(Str::slug((string) $this->getTranslation('name', 'en', true)));
        }

        $this->scope_countries = CountryScope::normalise($this->scope_countries);
        $this->allowed_types = $this->normaliseEnumList('allowed_types', $this->allowed_types ?? [], ListingType::class);
        if ($this->allowed_types === []) {
            throw InvalidCategoryTree::noListingTypes();
        }
        $this->allowed_price_types = $this->allowed_price_types === null
            ? null
            : $this->normaliseEnumList('allowed_price_types', $this->allowed_price_types, PriceType::class);

        if ($this->parent_id === null) {
            return;
        }
        if ($this->exists && $this->parent_id === $this->id) {
            throw InvalidCategoryTree::selfParent();
        }

        $parent = self::find($this->parent_id);
        if ($parent === null || $parent->parent_id !== null) {
            throw InvalidCategoryTree::tooDeep();
        }
        // Listings live on leaves: a category that has listings cannot get subcategories.
        if ($parent->listings()->withTrashed()->exists()) {
            throw InvalidCategoryTree::parentHasListings();
        }
        if ($this->exists && $this->children()->exists()) {
            throw InvalidCategoryTree::parentHasChildren();
        }
    }

    /**
     * @param  array<int, mixed>  $values
     * @param  class-string<ListingType>|class-string<PriceType>  $enum
     * @return list<string>
     */
    private function normaliseEnumList(string $field, array $values, string $enum): array
    {
        $clean = [];
        foreach ($values as $value) {
            $value = $value instanceof \BackedEnum ? $value->value : (string) $value;
            if ($enum::tryFrom($value) === null) {
                throw InvalidCategoryTree::invalidValue($field, $value);
            }
            $clean[$value] = $value;
        }

        // Keep the enum's order so stored values are stable.
        return array_values(array_filter(array_map(fn ($case) => $case->value, $enum::cases()), fn ($v) => isset($clean[$v])));
    }

    private function uniqueSlug(string $base): string
    {
        $base = $base !== '' ? Str::limit($base, 110, '') : 'category';
        $slug = $base;
        $suffix = 2;

        while (self::where('slug', $slug)->when($this->exists, fn ($q) => $q->whereKeyNot($this->id))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
