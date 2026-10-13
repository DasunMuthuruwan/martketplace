<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Keyed hash of a banned account's email or phone (retention §1). See App\Services\Security\BannedIdentities.
 *
 * @property int $id
 * @property string $kind
 * @property string $hash
 * @property int|null $user_id
 * @property Carbon $created_at
 */
class BannedIdentity extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['kind', 'hash', 'user_id'];

    /** @return Builder<BannedIdentity> */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays((int) config('marketplace.retention.banned_identity_hash')));
    }
}
