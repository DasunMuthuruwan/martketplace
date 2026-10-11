<?php

namespace App\Console\Commands\Staff;

use App\Models\User;
use App\Notifications\TwoFactorResetNotification;
use App\Support\UserSessions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Break-glass 2FA reset for a sole Owner who lost both phone and recovery codes
 * (docs/architecture/12-authentication-and-2fa.md §5). As strong as server access itself.
 * Other staff are reset by an Owner in Admin → Staff.
 */
class ResetTwoFactor extends Command
{
    protected $signature = 'marketplace:reset-2fa {email : Email of the account} {--force : Do not ask for confirmation}';

    protected $description = 'Remove two-factor authentication from an account (break-glass) and email the user';

    public function handle(): int
    {
        $user = User::where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();

        if ($user === null) {
            $this->error('No account with that email.');

            return self::FAILURE;
        }
        if ($user->two_factor_secret === null) {
            $this->info('This account has no two-factor authentication. Nothing to reset.');

            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm("Reset two-factor authentication for {$user->email}?")) {
            $this->line('Cancelled.');

            return self::FAILURE;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        UserSessions::endAll($user);

        Log::warning('Two-factor authentication reset from the command line', ['user_id' => $user->id]);
        $user->notify(new TwoFactorResetNotification);

        $this->info("Two-factor authentication removed for {$user->email}. The user has been emailed.");

        return self::SUCCESS;
    }
}
