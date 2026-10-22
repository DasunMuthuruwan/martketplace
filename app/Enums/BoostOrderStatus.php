<?php

namespace App\Enums;

/** A boost order's life (ADR-026, D3): pending until staff see the bank transfer. */
enum BoostOrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Refunded = 'refunded';

    public function isOpen(): bool
    {
        return $this === self::Pending;
    }
}
