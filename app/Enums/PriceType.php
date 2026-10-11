<?php

namespace App\Enums;

/** How a price is asked (database design §2.6). Categories may restrict them (e.g. Land: fixed, negotiable). */
enum PriceType: string
{
    case Fixed = 'fixed';
    case Negotiable = 'negotiable';
    case Free = 'free';
    case OnRequest = 'on_request';
}
