<?php

namespace App\Enums;

/** Rent period of a rent listing (database design §2.7). */
enum RentPeriod: string
{
    case Day = 'day';
    case Month = 'month';
    case Year = 'year';
}
