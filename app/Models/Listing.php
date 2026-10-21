<?php

namespace App\Models;

use App\Enums\ItemCondition;
use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Enums\PriceType;
use App\Enums\RentPeriod;
use App\Enums\UserStatus;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A classified ad (database design §2.7). State changes go through dedicated actions (T4.2/T4.4);
 * status, moderation and counter fields are never mass-assignable.
 *
 * @property int $id
 * @property int $user_id
 * @property int $category_id
 * @property ListingType|null $type null until the details step (drafts)
 * @property ListingStatus $status
 * @property string|null $title
 * @property string|null $slug
 * @property string|null $description
 * @property string|null $price decimal string in $currency (never converted)
 * @property string $currency ISO 4217
 * @property PriceType|null $price_type null until the price step (drafts)
 * @property RentPeriod|null $rent_period
 * @property ItemCondition|null $condition
 * @property int $country_id
 * @property int|null $region_id
 * @property int|null $subregion_id
 * @property int|null $city_id
 * @property int|null $locality_id
 * @property string|null $locality_text
 * @property bool $show_call
 * @property bool $show_whatsapp
 * @property array<string, mixed>|null $attributes_summary
 * @property int|null $cover_image_id
 * @property int $view_count
 * @property string|null $moderation_note
 * @property int|null $moderated_by
 * @property Carbon|null $moderated_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $published_at
 * @property Carbon|null $sorted_at the "newest" order: publication time, moved by a Bump (ADR-026)
 * @property Carbon|null $expires_at
 * @property Carbon|null $expiry_reminder_sent_at
 * @property Carbon|null $sold_at
 * @property Carbon|null $renewed_at
 * @property bool $flagged
 * @property bool $legal_hold
 */
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory, SoftDeletes;

    public const SLUG_MAX_LENGTH = 120;

    /** @var list<string> */
    protected $fillable = [
        'category_id', 'type', 'title', 'description', 'price', 'currency', 'price_type', 'rent_period', 'condition',
        'country_id', 'region_id', 'subregion_id', 'city_id', 'locality_id', 'locality_text', 'show_call', 'show_whatsapp',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'draft',
        'show_call' => true,
        'show_whatsapp' => true,
        'view_count' => 0,
        'flagged' => false,
        'legal_hold' => false,
    ];

    protected function casts(): array
    {
        return [
            'type' => ListingType::class,
            'status' => ListingStatus::class,
            'price' => 'decimal:3',
            'price_type' => PriceType::class,
            'rent_period' => RentPeriod::class,
            'condition' => ItemCondition::class,
            'country_id' => 'integer',
            'show_call' => 'boolean',
            'show_whatsapp' => 'boolean',
            'attributes_summary' => 'array',
            'view_count' => 'integer',
            'moderated_at' => 'datetime',
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
            'sorted_at' => 'datetime',
            'expires_at' => 'datetime',
            'expiry_reminder_sent_at' => 'datetime',
            'sold_at' => 'datetime',
            'renewed_at' => 'datetime',
            'flagged' => 'boolean',
            'legal_hold' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // The slug follows the title; old URLs still resolve because routing uses the id (routes doc §1).
        static::saving(function (Listing $listing): void {
            if ($listing->isDirty('title') || blank($listing->getAttribute('slug'))) {
                $listing->slug = Str::limit(Str::slug((string) $listing->title), self::SLUG_MAX_LENGTH, '') ?: 'listing';
            }
            // A published ad always has a place in the newest order (ListingPublisher sets both).
            if ($listing->published_at !== null && $listing->sorted_at === null) {
                $listing->sorted_at = $listing->published_at;
            }
        });
    }

    /**
     * What the public may see (global design §9): active, not expired, not deleted, owner allowed
     * to act (not suspended, banned or deleted), country active, category and its parent active.
     * Expiry is checked at query time, so no job has to run on time.
     *
     * @param  Builder<Listing>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->where('listings.status', ListingStatus::Active->value)
            ->where('listings.expires_at', '>', now())
            // Owner may act: no deleted, suspended or banned account. Written as NOT EXISTS (a
            // per-row primary-key lookup) because an EXISTS on users became a join that drove the
            // query from the users table and lost the newest-first index order (T5.4).
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('users')
                ->whereColumn('users.id', 'listings.user_id')
                ->where(fn ($q) => $q->whereNotNull('users.deleted_at')
                    ->orWhere('users.status', UserStatus::Banned->value)
                    ->orWhere(fn ($q) => $q->where('users.status', UserStatus::Suspended->value)
                        ->where(fn ($q) => $q->whereNull('users.suspended_until')->orWhere('users.suspended_until', '>', now())))))
            ->whereHas('country', fn (Builder $q) => $q->where('is_active', true));

        // Switched-off categories as a (cached, usually empty) exclusion list rather than a join:
        // a join on categories made MariaDB sort every ad of a country to show the newest 24
        // (measured in T5.4, docs/operations/performance.md).
        $hidden = Category::hiddenIds();
        if ($hidden !== []) {
            $query->whereNotIn('listings.category_id', $hidden);
        }
    }

    /**
     * Canonical URL `/ad/{slug}-{id}` (LST-11, CD-12), always from APP_URL. Old slugs redirect
     * here because the id decides.
     */
    public function publicUrl(): string
    {
        return route('listing.show', ['slugId' => ($this->slug ?: 'listing').'-'.$this->id]);
    }

    public function isPublic(): bool
    {
        return static::query()->public()->whereKey($this->getKey())->exists();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<Currency, $this> */
    public function currencyModel(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** @return BelongsTo<Region, $this> */
    public function subregion(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'subregion_id');
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return BelongsTo<Locality, $this> */
    public function locality(): BelongsTo
    {
        return $this->belongsTo(Locality::class);
    }

    /** @return HasMany<ListingAttributeValue, $this> */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(ListingAttributeValue::class);
    }

    /** @return HasMany<ListingBoost, $this> */
    public function boosts(): HasMany
    {
        return $this->hasMany(ListingBoost::class);
    }

    /** @return HasMany<ListingImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return BelongsTo<ListingImage, $this> */
    public function coverImage(): BelongsTo
    {
        return $this->belongsTo(ListingImage::class, 'cover_image_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function favouritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favourites');
    }

    /** @return BelongsTo<User, $this> */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }
}
