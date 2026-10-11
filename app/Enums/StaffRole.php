<?php

namespace App\Enums;

/**
 * Staff roles (owner decision Q9). Regular buyers/sellers have no role.
 * Rank drives the hierarchy guard: staff can only act on accounts of a lower rank.
 */
enum StaffRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Moderator = 'moderator';

    public function rank(): int
    {
        return match ($this) {
            self::Owner => 3,
            self::Admin => 2,
            self::Moderator => 1,
        };
    }

    /** Owner and Admin must use two-factor authentication (CD-11). */
    public function requiresTwoFactor(): bool
    {
        return $this !== self::Moderator;
    }
}
