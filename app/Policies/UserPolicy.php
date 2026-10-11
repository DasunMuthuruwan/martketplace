<?php

namespace App\Policies;

use App\Authorization\Permissions;
use App\Authorization\StaffHierarchy;
use App\Models\User;

/**
 * Staff actions on accounts. Each check = permission + hierarchy (+ last-Owner guard where an
 * Owner could lose access). Used by admin screens (M6) and actions; never by role name.
 */
class UserPolicy
{
    public function __construct(private readonly StaffHierarchy $hierarchy) {}

    public function viewAny(User $actor): bool
    {
        return $actor->can(Permissions::USERS_VIEW);
    }

    /** Email and phone are visible to Admin/Owner only (Moderator sees limited fields). */
    public function viewContactDetails(User $actor, User $target): bool
    {
        return $actor->is($target) || ($actor->can(Permissions::USERS_VIEW) && $actor->can(Permissions::USERS_SUSPEND));
    }

    public function suspend(User $actor, User $target): bool
    {
        return $actor->can(Permissions::USERS_SUSPEND)
            && $this->hierarchy->canActOn($actor, $target)
            && ! $this->hierarchy->isLastOwner($target);
    }

    public function ban(User $actor, User $target): bool
    {
        return $actor->can(Permissions::USERS_BAN)
            && $this->hierarchy->canActOn($actor, $target)
            && ! $this->hierarchy->isLastOwner($target);
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->can(Permissions::USERS_DELETE)
            && $this->hierarchy->canActOn($actor, $target)
            && ! $this->hierarchy->isLastOwner($target);
    }

    public function changeRole(User $actor, User $target): bool
    {
        return $actor->can(Permissions::STAFF_MANAGE) && $this->hierarchy->canActOn($actor, $target);
    }

    public function resetTwoFactor(User $actor, User $target): bool
    {
        return $actor->can(Permissions::STAFF_MANAGE) && $this->hierarchy->canActOn($actor, $target);
    }
}
