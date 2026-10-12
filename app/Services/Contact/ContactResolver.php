<?php

namespace App\Services\Contact;

use App\Enums\ContactVisibility;
use App\Models\Listing;
use App\Models\User;

/**
 * Whether a viewer may reveal a channel of a public listing (architecture §4.3, MSG-01/02/06):
 * the stricter of the account visibility and the listing toggle. `registered` asks guests to log in.
 */
class ContactResolver
{
    public const ALLOWED = 'allowed';

    public const LOGIN_REQUIRED = 'login_required';

    public const UNAVAILABLE = 'unavailable';

    public function __construct(private readonly ContactChannels $channels) {}

    public function decide(Listing $listing, ?User $viewer, string $channel): string
    {
        if (! in_array($channel, $this->channels->visible($listing), true)) {
            return self::UNAVAILABLE;
        }

        if ($listing->user->contact_visibility === ContactVisibility::Registered && $viewer === null) {
            return self::LOGIN_REQUIRED;
        }

        return self::ALLOWED;
    }

    /**
     * Button states for the listing page, without numbers (MSG-04: numbers never in the HTML).
     *
     * @return array<string, string> channel => decision
     */
    public function buttons(Listing $listing, ?User $viewer): array
    {
        $buttons = [];
        foreach ($this->channels->visible($listing) as $channel) {
            $buttons[$channel] = $this->decide($listing, $viewer, $channel);
        }

        return $buttons;
    }
}
