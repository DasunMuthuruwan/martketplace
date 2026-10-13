<?php

namespace App\Actions\Staff;

use App\Models\User;
use App\Notifications\TwoFactorResetNotification;
use App\Services\Audit\AuditLogger;
use App\Support\UserSessions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Removes two-factor authentication from an account (2FA doc §5): an Owner for other staff in
 * Admin → Staff (policy `resetTwoFactor`), or the break-glass CLI (no actor). Clears the secret
 * and recovery codes, ends all sessions, audits and emails the person. Owners and Admins must
 * set it up again before entering the admin area.
 */
class ResetStaffTwoFactor
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return bool false when the account had no two-factor authentication
     *
     * @throws AuthorizationException
     */
    public function handle(?User $actor, User $target): bool
    {
        if ($actor !== null) {
            Gate::forUser($actor)->authorize('resetTwoFactor', $target);
        }
        if ($target->two_factor_secret === null) {
            return false;
        }

        $target->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        UserSessions::endAll($target);

        if ($actor === null) {
            Log::warning('Two-factor authentication reset from the command line', ['user_id' => $target->id]);
        }
        $this->audit->record($actor, 'staff.two_factor_reset', $target, $actor === null ? 'command line' : null);
        $target->notify(new TwoFactorResetNotification);

        return true;
    }
}
