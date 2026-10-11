<?php

namespace App\Enums;

/** What a listing offers (category tree doc §1). Categories choose which types they allow. */
enum ListingType: string
{
    case Sale = 'sale';
    case Rent = 'rent';
    case Wanted = 'wanted';
}
