<?php

namespace App\Authorization;

use App\Enums\StaffRole;
use App\Models\User;

/**
 * Who may act on whom (docs/architecture/05-authorization.md §1).
 *
 * - Nobody acts on their own account through staff tools.
 * - Owners may act on every other account, including other Owners (needed to reset a
 *   locked-out Owner's 2FA). The last-Owner guard still protects the final Owner.
 * - Everyone else needs a strictly higher rank: Admin > Moderator > regular user.
 */
class StaffHierarchy
{
    public function canActOn(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }

        if ($actor->staffRole() === StaffRole::Owner) {
            return true;
        }

        return $actor->staffRank() > $target->staffRank();
    }

    public function isLastOwner(User $user): bool
    {
        return $user->hasRole(StaffRole::Owner->value)
            && User::role(StaffRole::Owner->value)->count() <= 1;
    }
}
