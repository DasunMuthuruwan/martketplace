<?php

namespace App\Exceptions\Listings;

use App\Enums\ListingStatus;
use DomainException;

/**
 * The draft steps only edit drafts. Editing a published or rejected listing goes through the
 * lifecycle actions (T4.4), which decide whether it must be reviewed again (LST-06).
 */
class ListingNotEditable extends DomainException
{
    public function __construct(public readonly ListingStatus $status)
    {
        parent::__construct(__('listings.not_editable'));
    }
}
