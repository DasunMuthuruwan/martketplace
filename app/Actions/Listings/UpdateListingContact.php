<?php

namespace App\Actions\Listings;

use App\Actions\Listings\Concerns\EditsDrafts;
use App\Models\Listing;
use App\Models\User;
use App\Services\Contact\ContactChannels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Wizard step 6 / ad settings (routes doc §3.1 saveContact): show call, show WhatsApp (MSG-02).
 * Not a material edit, so it applies at once (LST-06). A submitted ad must keep at least one
 * visible channel (MSG-03); drafts are checked on submit.
 */
class UpdateListingContact
{
    use EditsDrafts;

    public function __construct(private readonly ContactChannels $channels) {}

    /**
     * @param  array<string, mixed>  $input  show_call, show_whatsapp
     *
     * @throws ValidationException
     */
    public function handle(User $user, Listing $listing, array $input): Listing
    {
        $this->authorizeEdit($user, $listing);

        $validated = Validator::make($input, [
            'show_call' => ['required', 'boolean'],
            'show_whatsapp' => ['required', 'boolean'],
        ])->validate();

        return DB::transaction(function () use ($listing, $validated): Listing {
            $listing->forceFill([
                'show_call' => (bool) $validated['show_call'],
                'show_whatsapp' => (bool) $validated['show_whatsapp'],
            ]);

            if (self::isSubmitted($listing) && ! $this->channels->hasVisibleChannel($listing)) {
                throw ValidationException::withMessages(['contact' => __('listings.contact_required')]);
            }

            $listing->save();

            return $listing->fresh();
        });
    }
}
