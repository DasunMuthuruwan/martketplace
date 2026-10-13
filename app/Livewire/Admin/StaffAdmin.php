<?php

namespace App\Livewire\Admin;

use App\Actions\Staff\ChangeStaffRole;
use App\Actions\Staff\ResetStaffTwoFactor;
use App\Authorization\Permissions;
use App\Enums\StaffRole;
use App\Livewire\Admin\Concerns\RequiresPermission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Staff (authorization doc §2, 2FA doc §5): Owner only (`staff.manage`). Appoint an account by
 * email, change or remove a role, reset another staff member's two-factor authentication. Every
 * action needs a recently confirmed password and goes through ChangeStaffRole / ResetStaffTwoFactor
 * (hierarchy, last-Owner guard, audit).
 */
#[Layout('layouts.admin')]
#[Title('Staff')]
class StaffAdmin extends Component
{
    use RequiresPermission;

    public string $email = '';

    public string $role = '';

    /** @var array<int, string> user id => chosen role value ("none" removes the role) */
    public array $roleFor = [];

    public function mount(): void
    {
        $this->requirePermission(Permissions::STAFF_MANAGE);
    }

    /** Give the account with this email a staff role. */
    public function appoint(ChangeStaffRole $change): void
    {
        $this->requirePermission(Permissions::STAFF_MANAGE);
        if (! $this->hasRecentPassword()) {
            return;
        }

        $user = User::where('email', mb_strtolower(trim($this->email)))->first();
        $role = StaffRole::tryFrom($this->role);
        if ($user === null || $role === null) {
            $this->addError('email', __('staff.manage.not_found'));

            return;
        }

        $this->run(fn () => $change->handle(Auth::user(), $user, $role), __('staff.manage.saved', ['name' => $user->name]));
        $this->reset('email', 'role');
    }

    public function changeRole(int $userId, ChangeStaffRole $change): void
    {
        $this->requirePermission(Permissions::STAFF_MANAGE);
        if (! $this->hasRecentPassword()) {
            return;
        }

        $user = $this->staffMember($userId);
        $value = (string) ($this->roleFor[$userId] ?? '');
        $role = StaffRole::tryFrom($value);
        if ($role === null && $value !== 'none') {
            $this->addError('role', __('staff.manage.not_found'));

            return;
        }

        $this->run(fn () => $change->handle(Auth::user(), $user, $role), __('staff.manage.saved', ['name' => $user->name]));
    }

    public function resetTwoFactor(int $userId, ResetStaffTwoFactor $reset): void
    {
        $this->requirePermission(Permissions::STAFF_MANAGE);
        if (! $this->hasRecentPassword()) {
            return;
        }

        $user = $this->staffMember($userId);
        $this->run(fn () => $reset->handle(Auth::user(), $user), __('staff.manage.two_factor_reset', ['name' => $user->name]));
    }

    public function render(): View
    {
        $staff = User::role(array_map(fn (StaffRole $r) => $r->value, StaffRole::cases()))->orderBy('name')->get();
        foreach ($staff as $member) {
            $this->roleFor[$member->id] ??= (string) $member->staffRole()?->value;
        }

        return view('livewire.admin.staff', ['staff' => $staff, 'roles' => StaffRole::cases()]);
    }

    private function staffMember(int $id): User
    {
        $user = User::find($id);
        abort_if($user === null || ! $user->isStaff(), 404);

        return $user;
    }

    private function run(callable $action, string $message): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        } catch (AuthorizationException) {
            $this->addError('role', __('staff.accounts.not_allowed'));

            return;
        }

        session()->flash('status', $message);
    }
}
