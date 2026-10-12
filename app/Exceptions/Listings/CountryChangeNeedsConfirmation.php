<?php

namespace App\Exceptions\Listings;

use RuntimeException;

/**
 * GLB-11: the seller must confirm before a country change clears values. Carries the list to show
 * in the confirmation dialog; repeat the call with `confirm_country_change` to proceed.
 */
class CountryChangeNeedsConfirmation extends RuntimeException
{
    /** @param  array<string, string>  $clears  key => label */
    public function __construct(public readonly array $clears)
    {
        parent::__construct(__('listings.country_change.confirm'));
    }
}
