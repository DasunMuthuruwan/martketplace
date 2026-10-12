<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes audit rows (MOD-04). The IP is stored only as an HMAC keyed with the app key, which is
 * enough to correlate actions from one address without keeping the address itself.
 */
class AuditLogger
{
    /** @param  array<string, mixed>|null  $changes */
    public function record(?User $actor, string $action, ?Model $subject = null, ?string $reason = null, ?array $changes = null): AuditLog
    {
        $ip = request()->ip();

        return AuditLog::create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'reason' => $reason === null ? null : mb_substr($reason, 0, 500),
            'changes' => $changes,
            'ip_hash' => $ip === null ? null : self::hashIp($ip),
        ]);
    }

    public static function hashIp(string $ip): string
    {
        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }
}
