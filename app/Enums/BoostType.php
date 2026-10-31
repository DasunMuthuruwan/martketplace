<?php

namespace App\Enums;

/**
 * Paid visibility for a live ad (ADR-026, D1). Bump moves the ad back to the top of the newest
 * order once; Top ad shows it in the labelled block above the results for a period; Urgent puts a
 * labelled badge on the ad for a period. Home page featured slots come later.
 */
enum BoostType: string
{
    case Bump = 'bump';
    case Top = 'top';
    case Urgent = 'urgent';

    /** A Bump is a single moment; the others last a period. */
    public function hasDuration(): bool
    {
        return $this !== self::Bump;
    }

    /** "Bump", "Top ad · 7 days", "Urgent · 7 days" */
    public function productName(?int $days): string
    {
        return $this->hasDuration()
            ? (string) trans_choice('boosts.products.'.$this->value, (int) $days, ['days' => $days])
            : (string) __('boosts.products.'.$this->value);
    }
}
