<?php

namespace App\Actions\Staff;

use App\Authorization\Permissions;
use App\Models\Listing;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Legal hold (retention §3): while set, the retention job leaves the account or listing (and, for an
 * account, all its listings) untouched, e.g. during a police request or a fraud investigation.
 * Needs `users.delete`; every change is audited with the reason. A null actor is the CLI.
 */
class SetLegalHold
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(?User $actor, User|Listing $subject, bool $hold, string $reason): void
    {
        if ($actor !== null && ! $actor->can(Permissions::USERS_DELETE)) {
            throw new AuthorizationException;
        }
        if ($subject->legal_hold === $hold) {
            return;
        }

        $subject->forceFill(['legal_hold' => $hold])->saveQuietly();
        $this->audit->record($actor, $hold ? 'legal_hold.set' : 'legal_hold.lifted', $subject, $reason);
    }
}
