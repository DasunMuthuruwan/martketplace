<?php

namespace App\Enums;

/** Lifecycle of a "my town is not listed" request (docs/architecture/13-global-marketplace.md §4.5). */
enum LocationRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved'; // a new city/locality was created
    case Mapped = 'mapped';     // matched to an existing city/locality
    case Rejected = 'rejected';
}
