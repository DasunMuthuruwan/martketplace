<?php

namespace App\Services\Contact;

use App\Enums\ContactVisibility;
use App\Models\ContactReveal;
use App\Models\Listing;

/**
 * Which contact channels of a listing are visible to anyone at all (MSG-01–03, MSG-07): the
 * channel is enabled site-wide, the account's visibility is not hidden, the listing toggle is on
 * and a number exists. Per-viewer rules (guest vs logged in) are in ContactResolver.
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
        if (config('marketplace.contact.call_enabled') && $listing->show_call && $seller->phone !== null) {
            $channels[] = ContactReveal::CALL;
        }
        if (config('marketplace.contact.whatsapp_enabled') && $listing->show_whatsapp && $this->whatsappNumber($listing) !== null) {
            $channels[] = ContactReveal::WHATSAPP;
        }

        return $channels;
    }

    /** MSG-03: an ad cannot be submitted unless at least one channel is visible to someone. */
    public function hasVisibleChannel(Listing $listing): bool
    {
        return $this->visible($listing) !== [];
    }

    /** E.164; the separate WhatsApp number when set, otherwise the phone. */
    public function whatsappNumber(Listing $listing): ?string
    {
        return $listing->user->whatsapp_phone ?? $listing->user->phone;
    }
}
