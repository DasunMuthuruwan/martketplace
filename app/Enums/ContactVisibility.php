<?php

namespace App\Enums;

/** Who can see a seller's phone/WhatsApp (ADR-011). Hidden until the seller chooses. */
enum ContactVisibility: string
{
    case Hidden = 'hidden';
    case Registered = 'registered';
    case Public = 'public';
}
