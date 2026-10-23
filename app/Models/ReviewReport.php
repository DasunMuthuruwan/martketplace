<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Someone reported a seller review (G5b, ADR-027). One per review and reporter.
 *
 * @property int $id
 * @property int $review_id
 * @property int|null $reporter_id
 * @property string $reason
 * @property string|null $details
 * @property Carbon $created_at
 */
class ReviewReport extends Model
{
    public const UPDATED_AT = null;

    public const REASONS = ['fake', 'abusive', 'personal_data', 'other'];

    /** @var list<string> */
    protected $fillable = ['reason', 'details'];

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
