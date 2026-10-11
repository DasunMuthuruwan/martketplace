<?php

namespace App\Actions\Fortify;

use App\Models\Country;
use App\Models\User;
use App\Rules\HumanTiming;
use App\Rules\PassesTurnstile;
use App\Services\Security\Turnstile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Propaganistas\LaravelPhone\PhoneNumber;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * Registration (AUTH-01): name, email, password, country, optional mobile number, terms.
 * Bot protection: honeypot + time trap + Turnstile (when configured, fail closed).
 * The mobile number is optional at sign-up and required later before contact options can be shown.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        // ISO codes are stored upper-case; SQLite compares case-sensitively.
        $input['phone_country'] = strtoupper(trim((string) ($input['phone_country'] ?? '')));

        Validator::make($input, [
            'name' => ['required', 'string', 'max:100'],
            // Includes soft-deleted accounts: their address stays reserved until anonymised (restore window).
            'email' => ['required', 'string', 'email:rfc', 'max:191', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
            'country_id' => ['required', 'integer', Rule::exists(Country::class, 'id')->where('is_active', true)],
            'phone_country' => ['nullable', 'required_with:phone', 'string', 'size:2', Rule::exists(Country::class, 'iso2')],
            'phone' => ['nullable', 'string', 'max:30', (new Phone)->countryField('phone_country')],
            'terms' => ['accepted'],
            'website' => ['prohibited'],
            HumanTiming::FIELD => ['required', new HumanTiming],
            // Required when Turnstile is configured: custom rules don't run on absent fields.
            Turnstile::RESPONSE_FIELD => [Rule::requiredIf(fn () => app(Turnstile::class)->enabled()), new PassesTurnstile(failOpen: false)],
        ], [
            'website.prohibited' => __('security.form_expired'),
            HumanTiming::FIELD.'.required' => __('security.form_expired'),
            Turnstile::RESPONSE_FIELD.'.required' => __('security.turnstile_failed'),
        ])->validate();

        $phoneCountry = $input['phone_country'] !== '' ? $input['phone_country'] : null;
        $phone = filled($input['phone'] ?? null)
            ? (new PhoneNumber((string) $input['phone'], $phoneCountry))->formatE164()
            : null;

        return User::create([
            'name' => trim((string) $input['name']),
            'email' => (string) $input['email'],
            'password' => Hash::make((string) $input['password']),
            'country_id' => (int) $input['country_id'],
            'phone' => $phone,
            'phone_country' => $phone !== null ? $phoneCountry : null,
        ]);
    }
}
