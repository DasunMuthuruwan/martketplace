<?php

namespace App\Models;

use App\Enums\ContactVisibility;
use App\Enums\StaffRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Marketplace account (buyer and seller). Status, visibility and staff fields are never
 * mass-assignable: they change only through dedicated actions.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $phone E.164
 * @property string|null $phone_country ISO 3166-1 alpha-2 of the phone number
 * @property Carbon|null $phone_verified_at reserved for SMS OTP (AUTH-05)
 * @property string|null $whatsapp_phone E.164; null means the same as $phone
 * @property int|null $country_id
 * @property ContactVisibility $contact_visibility
 * @property UserStatus $status
 * @property string|null $status_reason
 * @property Carbon|null $suspended_until
 * @property Carbon|null $last_login_at
 * @property bool $legal_hold
 * @property string|null $two_factor_secret encrypted
 * @property string|null $two_factor_recovery_codes encrypted
 * @property Carbon|null $two_factor_confirmed_at
 */
#[Fillable(['name', 'email', 'password', 'phone', 'phone_country', 'country_id'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * Same defaults as the database columns, so a freshly created instance (e.g. right after
     * registration, before it is reloaded) already has its status and visibility.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'contact_visibility' => 'hidden',
        'locale' => 'en',
        'legal_hold' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'country_id' => 'integer',
            'contact_visibility' => ContactVisibility::class,
            'status' => UserStatus::class,
            'suspended_until' => 'datetime',
            'last_login_at' => 'datetime',
            'legal_hold' => 'boolean',
            'anonymised_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** Emails are stored lower-cased so lookups and uniqueness are case-insensitive. */
    protected function email(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null ? null : mb_strtolower(trim($value)));
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Whether the account may sign in and act. A suspension with an end date expires on its own.
     */
    public function isActive(): bool
    {
        return match ($this->status) {
            UserStatus::Active => true,
            UserStatus::Suspended => $this->suspended_until !== null && $this->suspended_until->isPast(),
            UserStatus::Banned => false,
        };
    }

    /**
     * Accounts that may act: the query form of isActive() (a suspension with a past end date has expired).
     *
     * @param  Builder<User>  $query
     */
    public function scopeActiveAccounts(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->where('status', UserStatus::Active->value)
            ->orWhere(fn (Builder $q) => $q->where('status', UserStatus::Suspended->value)
                ->whereNotNull('suspended_until')
                ->where('suspended_until', '<=', now())));
    }

    /** Public seller page `/seller/{id}-{slug}` (routes doc §1); the slug follows the name. */
    public function sellerUrl(): string
    {
        return route('seller.show', ['idSlug' => $this->id.'-'.$this->sellerSlug()]);
    }

    public function sellerSlug(): string
    {
        return Str::slug($this->name) ?: 'seller';
    }

    /** @return HasMany<Listing, $this> */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /**
     * Saved listings (SRCH-08), newest first.
     *
     * @return BelongsToMany<Listing, $this>
     */
    public function favourites(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'favourites')->withPivot('created_at')->orderByPivot('created_at', 'desc');
    }

    /** Highest staff role, or null for buyers/sellers. */
    public function staffRole(): ?StaffRole
    {
        foreach (StaffRole::cases() as $role) {
            if ($this->hasRole($role->value)) {
                return $role;
            }
        }

        return null;
    }

    public function isStaff(): bool
    {
        return $this->staffRole() !== null;
    }

    /** 0 for regular users; see StaffRole::rank(). */
    public function staffRank(): int
    {
        return $this->staffRole()?->rank() ?? 0;
    }

    public function hasConfirmedTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /** Owner/Admin without confirmed 2FA may not enter the admin area (CD-11). */
    public function mustSetUpTwoFactor(): bool
    {
        return ($this->staffRole()?->requiresTwoFactor() ?? false) && ! $this->hasConfirmedTwoFactor();
    }
}
