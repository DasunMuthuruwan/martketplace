<?php

namespace App\Actions\Account;

use App\Authorization\StaffHierarchy;
use App\Enums\StaffRole;
use App\Models\User;
use App\Notifications\AccountDeletedNotification;
use App\Support\UserSessions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Self-service account deletion (AUTH-08), step 1 of the retention plan (docs/architecture/09 §2):
 * soft delete, staff roles removed, signed out everywhere, confirmation email. Anonymisation after
 * the restore window is done by the retention job (M7). Listings are hidden by the public scope
 * (owner deleted, T4.1) and favourites are removed when they exist (T4.6).
 */
class DeleteAccount
{
    public function __construct(private readonly StaffHierarchy $hierarchy) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function delete(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => ['required', 'string', 'current_password:web'],
        ], [
            'password.current_password' => __('account.wrong_password'),
        ])->validateWithBag('deleteAccount');

        DB::transaction(function () use ($user): void {
            // Lock the Owner set so two Owners cannot delete themselves at the same time.
            User::role(StaffRole::Owner->value)->lockForUpdate()->get();

            if ($this->hierarchy->isLastOwner($user)) {
                throw ValidationException::withMessages(['password' => __('staff.last_owner')])
                    ->errorBag('deleteAccount');
            }

            // A restored account (support request) must not silently regain staff powers.
            $user->syncRoles([]);
            $user->delete();
        });

        UserSessions::endAll($user);

        Log::info('Account deleted by its owner', ['user_id' => $user->id]);
        $user->notify(new AccountDeletedNotification);
    }
}
