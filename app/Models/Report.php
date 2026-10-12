<?php

namespace App\Models;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user's report of a listing or another user (MOD-02). The target type is a short, stable name
 * (`listing`, `user`), never a class name, so renaming classes never breaks stored reports.
 *
 * @property int $id
 * @property int|null $reporter_id
 * @property string $reportable_type
 * @property int $reportable_id
 * @property ReportReason $reason
 * @property string|null $details
 * @property ReportStatus $status
 * @property int|null $handled_by
 * @property Carbon|null $handled_at
 * @property string|null $resolution_note
 */
class Report extends Model
{
    /** @var array<string, class-string<Model>> */
    public const TYPES = ['listing' => Listing::class, 'user' => User::class];

    /** @var list<string> */
    protected $fillable = ['reason', 'details'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return [
            'reportable_id' => 'integer',
            'reason' => ReportReason::class,
            'status' => ReportStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    /** The reported listing or user (including deleted ones, so staff can still see them). */
    public function target(): ?Model
    {
        $class = self::TYPES[$this->reportable_type] ?? null;

        return $class === null ? null : $class::withTrashed()->find($this->reportable_id);
    }

    /** @param  Builder<Report>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', ReportStatus::Open->value);
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** @return BelongsTo<User, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
