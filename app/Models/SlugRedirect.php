<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Old slug → current record, so renamed categories answer with a 301 (database design §3).
 *
 * @property int $id
 * @property string $type
 * @property string $old_slug
 * @property int $target_id
 */
class SlugRedirect extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['type', 'old_slug', 'target_id'];

    protected function casts(): array
    {
        return ['target_id' => 'integer'];
    }
}
