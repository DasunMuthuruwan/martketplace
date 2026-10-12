<?php

namespace App\Enums;

/** Listing lifecycle (architecture §4.2). Only Active listings that have not expired are public. */
enum ListingStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Active = 'active';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Sold = 'sold';
    case Expired = 'expired';
}
