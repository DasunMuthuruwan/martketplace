<?php

namespace App\Models;

use App\Enums\ContactVisibility;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

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
 * @property int|null $country_id
 * @property ContactVisibility $contact_visibility
 * @property UserStatus $status
 * @property string|null $status_reason
 * @property Carbon|null $suspended_until
 * @property Carbon|null $last_login_at
 * @property bool $legal_hold
 */
#[Fillable(['name', 'email', 'password', 'phone', 'phone_country', 'country_id'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

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
}
