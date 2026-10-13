<?php

namespace App\Services\Security;

use App\Models\BannedIdentity;
use App\Models\User;

/**
 * Blocks re-registration after a ban without keeping personal data (retention §1): the email
 * (lower-cased) and the phone (E.164) are stored as HMAC-SHA256 keyed with the app key. A new
 * account, an email change or a phone change is refused when its hash is listed.
 * Rotating APP_KEY invalidates the list (documented in the key-rotation runbook).
 */
class BannedIdentities
{
    public const EMAIL = 'email';

    public const PHONE = 'phone';

    /** Record the banned account's identifiers. */
    public function add(User $user): void
    {
        foreach ($this->identifiers($user->email, $user->phone) as $kind => $hash) {
            BannedIdentity::firstOrCreate(['kind' => $kind, 'hash' => $hash], ['user_id' => $user->id]);
        }
    }

    /** Lifting a ban lifts the block on that account's identifiers. */
    public function remove(User $user): void
    {
        BannedIdentity::where('user_id', $user->id)->delete();
    }

    public function isBanned(?string $email, ?string $phone = null): bool
    {
        $identifiers = $this->identifiers($email, $phone);
        if ($identifiers === []) {
            return false;
        }

        return BannedIdentity::where(function ($query) use ($identifiers): void {
            foreach ($identifiers as $kind => $hash) {
                $query->orWhere(fn ($q) => $q->where('kind', $kind)->where('hash', $hash));
            }
        })->exists();
    }

    /** @return array<string, string> kind => hash */
    private function identifiers(?string $email, ?string $phone): array
    {
        return array_filter([
            self::EMAIL => $email === null || trim($email) === '' ? null : $this->hash(mb_strtolower(trim($email))),
            self::PHONE => $phone === null || $phone === '' ? null : $this->hash($phone),
        ]);
    }

    private function hash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
