<?php

namespace App\Actions\Staff;

use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\AccountStatusChanged;
use App\Services\Audit\AuditLogger;
use App\Services\Security\BannedIdentities;
use App\Support\UserSessions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Staff actions on accounts (AUTH-09, ADM-04, MOD-04): suspend (optionally until a date), ban,
 * reinstate, verify email and delete. Each one: UserPolicy (permission + hierarchy + last-Owner
 * guard), reason where it matters, audit entry, signed out everywhere when access is removed,
 * email to the person. A ban also blocks the email and phone from re-registering (retention §1).
 * Ads of suspended, banned or deleted accounts disappear through the public scope.
 */
class ManageAccount
{
    public const REASON_MAX = 500;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BannedIdentities $banned,
    ) {}

    /** @throws AuthorizationException|ValidationException */
    public function suspend(User $actor, User $target, ?string $reason, ?Carbon $until = null): User
    {
        Gate::forUser($actor)->authorize('suspend', $target);
        $reason = $this->reason($reason);
        if ($until !== null && $until->isPast()) {
            throw ValidationException::withMessages(['until' => __('staff.accounts.until_future')]);
        }

        return $this->change($actor, $target, 'account.suspended', $reason, [
            'status' => UserStatus::Suspended, 'status_reason' => $reason, 'suspended_until' => $until,
        ], signOut: true);
    }

    /** @throws AuthorizationException|ValidationException */
    public function ban(User $actor, User $target, ?string $reason): User
    {
        Gate::forUser($actor)->authorize('ban', $target);
        $reason = $this->reason($reason);

        $user = $this->change($actor, $target, 'account.banned', $reason, [
            'status' => UserStatus::Banned, 'status_reason' => $reason, 'suspended_until' => null,
        ], signOut: true);
        $this->banned->add($user);

        return $user;
    }

    /** @throws AuthorizationException|ValidationException */
    public function reinstate(User $actor, User $target, ?string $reason = null): User
    {
        Gate::forUser($actor)->authorize('reinstate', $target);
        if ($target->status === UserStatus::Active) {
            throw ValidationException::withMessages(['status' => __('staff.accounts.already_active')]);
        }

        $user = $this->change($actor, $target, 'account.reinstated', $reason === null ? null : trim($reason), [
            'status' => UserStatus::Active, 'status_reason' => null, 'suspended_until' => null,
        ], signOut: false);
        $this->banned->remove($user);

        return $user;
    }

    /** @throws AuthorizationException */
    public function verifyEmail(User $actor, User $target): User
    {
        Gate::forUser($actor)->authorize('verifyEmail', $target);
        if ($target->hasVerifiedEmail()) {
            return $target;
        }

        $target->markEmailAsVerified();
        $this->audit->record($actor, 'account.email_verified', $target);

        return $target->fresh();
    }

    /**
     * Soft delete by staff (users.delete). Same first step as self-service deletion: roles and
     * favourites removed, signed out everywhere; anonymised later by the retention job.
     *
     * @throws AuthorizationException|ValidationException
     */
    public function delete(User $actor, User $target, ?string $reason): void
    {
        Gate::forUser($actor)->authorize('delete', $target);
        $reason = $this->reason($reason);

        DB::transaction(function () use ($actor, $target, $reason): void {
            $target->syncRoles([]);
            $target->favourites()->detach();
            $target->delete();
            $this->audit->record($actor, 'account.deleted', $target, $reason);
        });
        UserSessions::endAll($target);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function change(User $actor, User $target, string $action, ?string $reason, array $changes, bool $signOut): User
    {
        $before = $target->status->value;

        DB::transaction(function () use ($actor, $target, $action, $reason, $changes, $before): void {
            $target->forceFill($changes)->save();
            $this->audit->record($actor, $action, $target, $reason, [
                'status' => [$before, $target->status->value],
                'until' => $target->suspended_until?->toAtomString(),
            ]);
        });

        if ($signOut) {
            UserSessions::endAll($target);
        }
        $target->notify(new AccountStatusChanged($target->status, $reason, $target->suspended_until));

        return $target->fresh();
    }

    /** @throws ValidationException */
    private function reason(?string $reason): string
    {
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:'.self::REASON_MAX]])->validate();

        return trim((string) $reason);
    }
}
