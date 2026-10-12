<?php

namespace App\Actions\Engagement;

use App\Enums\ReportReason;
use App\Exceptions\DuplicateReport;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use App\Rules\PassesTurnstile;
use App\Services\Security\Turnstile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Report a listing or a user (MOD-02, routes doc §4.4). Targets: a public listing that is not the
 * reporter's own, or another user account. One open report per reporter and target (409 on a
 * repeat, under a lock so two clicks cannot create two). Plain-text details, Turnstile when
 * configured (fails open: staff review every report anyway).
 */
class SubmitReport
{
    public const DETAILS_MAX = 1000;

    /**
     * @param  array<string, mixed>  $input  reportable_type, reportable_id, reason, details, turnstile token
     *
     * @throws ValidationException
     * @throws DuplicateReport
     */
    public function handle(User $reporter, array $input): Report
    {
        $validated = Validator::make($input, [
            'reportable_type' => ['required', Rule::in(array_keys(Report::TYPES))],
            'reportable_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'details' => ['nullable', 'string', 'max:'.self::DETAILS_MAX, 'not_regex:/<[^>]*>/', Rule::requiredIf(($input['reason'] ?? null) === ReportReason::Other->value)],
            Turnstile::RESPONSE_FIELD => [Rule::requiredIf(fn () => app(Turnstile::class)->enabled()), new PassesTurnstile(failOpen: true)],
        ], [
            'details.not_regex' => __('listings.html_not_allowed'),
            'details.required' => __('reports.details_for_other'),
            Turnstile::RESPONSE_FIELD.'.required' => __('security.turnstile_failed'),
        ])->validate();

        $type = $validated['reportable_type'];
        $id = (int) $validated['reportable_id'];
        $this->checkTarget($reporter, $type, $id);

        return DB::transaction(function () use ($reporter, $validated, $type, $id): Report {
            User::whereKey($reporter->id)->lockForUpdate()->first();

            $exists = Report::open()->where('reporter_id', $reporter->id)
                ->where('reportable_type', $type)->where('reportable_id', $id)->exists();
            if ($exists) {
                throw new DuplicateReport;
            }

            $report = new Report([
                'reason' => $validated['reason'],
                'details' => filled($validated['details'] ?? null) ? trim((string) $validated['details']) : null,
            ]);
            $report->forceFill(['reporter_id' => $reporter->id, 'reportable_type' => $type, 'reportable_id' => $id])->save();

            return $report;
        });
    }

    /** @throws ValidationException */
    private function checkTarget(User $reporter, string $type, int $id): void
    {
        $valid = match ($type) {
            'listing' => ($listing = Listing::find($id)) !== null && $listing->isPublic() && $listing->user_id !== $reporter->id,
            'user' => $id !== $reporter->id && User::whereKey($id)->exists(),
            default => false,
        };

        if (! $valid) {
            throw ValidationException::withMessages(['reportable_id' => __('reports.invalid_target')]);
        }
    }
}
