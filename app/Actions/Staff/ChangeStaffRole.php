<?php

namespace App\Actions\Staff;

use App\Authorization\StaffHierarchy;
use App\Enums\StaffRole;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Assigns, changes or removes a user's staff role (Owner only: staff.manage).
 * A staff member holds at most one role. The last Owner can never lose the Owner role.
 * Audited (staff.role_changed, before and after).
 */
class ChangeStaffRole
{
    public function __construct(
        private readonly StaffHierarchy $hierarchy,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, User $target, ?StaffRole $role): void
    {
        Gate::forUser($actor)->authorize('changeRole', $target);

        if (! $target->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['role' => __('staff.unverified_target')]);
        }

        $before = $target->staffRole()?->value;

        DB::transaction(function () use ($actor, $target, $role, $before): void {
            // Lock the Owner set so two concurrent demotions can't remove the last Owner.
            User::role(StaffRole::Owner->value)->lockForUpdate()->get();

            if ($role !== StaffRole::Owner && $this->hierarchy->isLastOwner($target)) {
                throw ValidationException::withMessages(['role' => __('staff.last_owner')]);
            }

            $target->syncRoles($role === null ? [] : [$role->value]);
            $this->audit->record($actor, 'staff.role_changed', $target, null, ['role' => [$before, $role?->value]]);
        });
    }
}
