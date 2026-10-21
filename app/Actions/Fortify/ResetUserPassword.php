<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Notifications\SecurityChange;
use App\Support\UserSessions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();

        // A reset usually means the password was forgotten or the account is at risk: sign it out
        // everywhere, like a password change (authentication doc §3, S-04).
        UserSessions::endAll($user);
        $user->notify(new SecurityChange(SecurityChange::PASSWORD_RESET));
    }
}
