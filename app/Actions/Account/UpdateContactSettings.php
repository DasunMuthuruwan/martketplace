<?php

namespace App\Actions\Account;

use App\Enums\ContactVisibility;
use App\Models\Country;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Propaganistas\LaravelPhone\PhoneNumber;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * Contact settings (MSG-01, MSG-02, DASH-02): who can see the seller's numbers, and an optional
 * separate WhatsApp number. Visibility other than hidden needs at least one number on the account.
 */
class UpdateContactSettings
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        $input['whatsapp_country'] = strtoupper(trim((string) ($input['whatsapp_country'] ?? '')));

        $validated = Validator::make($input, [
            'contact_visibility' => ['required', Rule::enum(ContactVisibility::class)],
            'whatsapp_country' => ['nullable', 'required_with:whatsapp_phone', 'string', 'size:2', Rule::exists(Country::class, 'iso2')],
            'whatsapp_phone' => ['nullable', 'string', 'max:30', (new Phone)->countryField('whatsapp_country')],
        ])->validateWithBag('updateContactSettings');

        $whatsapp = filled($validated['whatsapp_phone'] ?? null)
            ? (new PhoneNumber((string) $validated['whatsapp_phone'], strtoupper((string) $validated['whatsapp_country'])))->formatE164()
            : null;

        // The same number as the account phone is stored as null ("use my phone number").
        if ($whatsapp === $user->phone) {
            $whatsapp = null;
        }

        $visibility = ContactVisibility::from($validated['contact_visibility']);

        if ($visibility !== ContactVisibility::Hidden && $user->phone === null && $whatsapp === null) {
            throw ValidationException::withMessages([
                'contact_visibility' => __('account.contact.number_needed'),
            ])->errorBag('updateContactSettings');
        }

        $user->forceFill([
            'contact_visibility' => $visibility,
            'whatsapp_phone' => $whatsapp,
        ])->save();
    }
}
