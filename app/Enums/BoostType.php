<?php

namespace App\Enums;

/**
 * Paid visibility for a live ad (ADR-026, D1). Bump moves the ad back to the top of the newest
 * order once; Top ad shows it in the labelled block above the results for a period. Urgent and
 * home page featured slots come later.
 */
enum BoostType: string
{
    case Bump = 'bump';
    case Top = 'top';

    /** A Bump is a single moment; the others last a period. */
    public function hasDuration(): bool
    {
        return $this !== self::Bump;
    }
}
