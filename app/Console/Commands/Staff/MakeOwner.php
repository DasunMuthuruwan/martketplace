<?php

namespace App\Console\Commands\Staff;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Bootstraps the first Owner (or adds one) from the server. Requires server access, which is
 * the only way to grant Owner before an Owner exists. Later Owners can also be appointed in Admin → Staff.
 */
class MakeOwner extends Command
{
    protected $signature = 'marketplace:make-owner {email : Email of an existing, verified account} {--force : Do not ask for confirmation}';

    protected $description = 'Give an existing verified account the Owner role';

    public function handle(): int
    {
        $user = User::where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();

        if ($user === null) {
            $this->error('No account with that email. Register it first.');

            return self::FAILURE;
        }
        if (! $user->hasVerifiedEmail() || ! $user->isActive()) {
            $this->error('The account must have a verified email and be active.');

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm("Make {$user->email} an Owner (full control of the marketplace)?")) {
            $this->line('Cancelled.');

            return self::FAILURE;
        }

        $user->syncRoles([StaffRole::Owner->value]);
        Log::warning('Owner role granted from the command line', ['user_id' => $user->id]);

        $this->info("{$user->email} is now an Owner.");
        $this->warn('Two-factor authentication is required before the admin area can be used: /account/two-factor');

        return self::SUCCESS;
    }
}
