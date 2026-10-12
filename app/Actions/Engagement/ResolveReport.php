<?php

namespace App\Actions\Engagement;

use App\Authorization\Permissions;
use App\Enums\ReportStatus;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Staff close a report as actioned (something was done, e.g. the ad was suspended) or dismissed
 * (MOD-01/02/04). Needs `reports.resolve`; staff never resolve reports about themselves or their
 * own ads. Audited. The action taken on the target itself (suspend, ban) is a separate action.
 */
class ResolveReport
{
    public const NOTE_MAX = 500;

    public function __construct(private readonly AuditLogger $audit) {}

    /** @throws AuthorizationException|ValidationException */
    public function handle(User $staff, Report $report, ReportStatus $outcome, ?string $note): Report
    {
        if (! $staff->can(Permissions::REPORTS_RESOLVE) || $this->concerns($staff, $report)) {
            throw new AuthorizationException;
        }
        if ($outcome === ReportStatus::Open) {
            throw ValidationException::withMessages(['status' => __('reports.invalid_outcome')]);
        }
        Validator::make(['note' => $note], ['note' => ['nullable', 'string', 'max:'.self::NOTE_MAX]])->validate();

        return DB::transaction(function () use ($staff, $report, $outcome, $note): Report {
            $locked = Report::whereKey($report->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== ReportStatus::Open) {
                throw ValidationException::withMessages(['status' => __('moderation.already_decided')]);
            }

            $locked->forceFill([
                'status' => $outcome,
                'handled_by' => $staff->id,
                'handled_at' => now(),
                'resolution_note' => filled($note) ? trim((string) $note) : null,
            ])->save();
            $this->audit->record($staff, 'report.'.$outcome->value, $locked, $locked->resolution_note, ['status' => ['open', $outcome->value]]);

            return $locked;
        });
    }

    /** The staff member is the reported user or owns the reported listing. */
    private function concerns(User $staff, Report $report): bool
    {
        $target = $report->target();

        return ($target instanceof User && $target->is($staff))
            || ($target instanceof Listing && $target->user_id === $staff->id);
    }
}
