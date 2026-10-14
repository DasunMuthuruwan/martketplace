<?php

namespace App\Console\Commands\Retention;

use App\Actions\Staff\SetLegalHold;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Console\Command;

/** Set or lift a legal hold from the server (retention §3); the users screen covers accounts. */
class LegalHold extends Command
{
    protected $signature = 'marketplace:legal-hold {type : user or listing} {id} {reason} {--lift : remove the hold}';

    protected $description = 'Protect an account or listing from retention deletion, or lift the hold';

    public function handle(SetLegalHold $hold): int
    {
        $subject = match ($this->argument('type')) {
            'user' => User::withTrashed()->find($this->argument('id')),
            'listing' => Listing::withTrashed()->find($this->argument('id')),
            default => null,
        };
        if ($subject === null) {
            $this->error('Not found. Use "user" or "listing" and an existing id.');

            return self::FAILURE;
        }

        $hold->handle(null, $subject, ! $this->option('lift'), (string) $this->argument('reason'));
        $this->info($this->option('lift') ? 'Legal hold lifted.' : 'Legal hold set.');

        return self::SUCCESS;
    }
}
