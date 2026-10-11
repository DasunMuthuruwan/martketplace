<?php

namespace App\Actions\Fortify;

use App\Enums\ContactVisibility;
use App\Models\Country;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Propaganistas\LaravelPhone\PhoneNumber;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * Profile settings (AUTH-08): name, email, country and mobile number.
 * - Changing the email needs the current password and a new verification.
 * - Changing the mobile number clears phone_verified_at (reserved for SMS OTP).
 * - With no number left, contact visibility falls back to hidden (MSG-01).
 */
class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        $emailChanged = mb_strtolower(trim((string) ($input['email'] ?? ''))) !== $user->email;
        // ISO codes are stored upper-case; SQLite compares case-sensitively.
        $input['phone_country'] = strtoupper(trim((string) ($input['phone_country'] ?? '')));

        Validator::make($input, [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:191', Rule::unique(User::class)->ignore($user->id)],
            // Inactive countries are kept if already set, so a deactivation does not block profile edits.
            'country_id' => ['required', 'integer', Rule::exists(Country::class, 'id')->where(
                fn ($query) => $query->where('is_active', true)->orWhere('id', $user->country_id)
            )],
            'phone_country' => ['nullable', 'required_with:phone', 'string', 'size:2', Rule::exists(Country::class, 'iso2')],
            'phone' => ['nullable', 'string', 'max:30', (new Phone)->countryField('phone_country')],
            'current_password' => $emailChanged ? ['required', 'string', 'current_password:web'] : ['nullable'],
        ], [
            'current_password.required' => __('account.profile.password_for_email'),
            'current_password.current_password' => __('account.wrong_password'),
        ])->validateWithBag('updateProfileInformation');

        $phoneCountry = filled($input['phone'] ?? null) ? strtoupper((string) $input['phone_country']) : null;
        $phone = $phoneCountry !== null
            ? (new PhoneNumber((string) $input['phone'], $phoneCountry))->formatE164()
            : null;

        $user->forceFill([
            'name' => trim((string) $input['name']),
            'email' => (string) $input['email'],
            'country_id' => (int) $input['country_id'],
            'phone' => $phone,
            'phone_country' => $phoneCountry,
        ]);

        if ($user->isDirty('phone')) {
            $user->phone_verified_at = null;
        }
        if ($user->phone === null && $user->whatsapp_phone === null) {
            $user->contact_visibility = ContactVisibility::Hidden;
        }
        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }
    }
}
