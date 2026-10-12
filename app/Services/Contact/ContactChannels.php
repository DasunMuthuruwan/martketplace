<?php

namespace App\Services\Contact;

use App\Enums\ContactVisibility;
use App\Models\Listing;

/**
 * Which contact channels of a listing are visible to anyone at all (MSG-01–03): the account's
 * visibility must not be hidden, the listing toggle must be on and a number must exist. The full
 * per-viewer resolution (guest vs logged in, reveal endpoint) is ContactResolver in T4.5.
 */
class ContactChannels
{
    /** @return list<string> e.g. ['call', 'whatsapp'] */
    public function visible(Listing $listing): array
    {
        $seller = $listing->user;
        if ($seller->contact_visibility === ContactVisibility::Hidden) {
            return [];
        }

        $channels = [];
        if ($listing->show_call && $seller->phone !== null) {
            $channels[] = 'call';
        }
        if ($listing->show_whatsapp && ($seller->whatsapp_phone ?? $seller->phone) !== null) {
            $channels[] = 'whatsapp';
        }

        return $channels;
    }

    /** MSG-03: an ad cannot be submitted unless at least one channel is visible to someone. */
    public function hasVisibleChannel(Listing $listing): bool
    {
        return $this->visible($listing) !== [];
    }
}
