<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One contact reveal (MSG-04). Pruned by `model:prune` after `retention.contact_reveal` days.
 *
 * @property int $id
 * @property int $listing_id
 * @property int|null $user_id
 * @property string $channel
 * @property string $ip_hash
 * @property Carbon $created_at
 */
class ContactReveal extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    public const CALL = 'call';

    public const WHATSAPP = 'whatsapp';

    /** @var list<string> */
    protected $fillable = ['listing_id', 'user_id', 'channel', 'ip_hash'];

    /** @return Builder<ContactReveal> */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays((int) config('marketplace.retention.contact_reveal')));
    }
}
