<?php

namespace App\Enums;

/** Whether listings in a category need a price. */
enum PriceMode: string
{
    case Required = 'required';
    case Optional = 'optional';
    case None = 'none';
}
