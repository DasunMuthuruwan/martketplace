<?php

namespace App\Enums;

/**
 * How a currency's minor units are shown.
 * Auto hides them when the amount is whole (Rs 12,500,000 instead of Rs 12,500,000.00).
 */
enum DisplayDecimals: string
{
    case Auto = 'auto';
    case Always = 'always';
    case Never = 'never';
}
