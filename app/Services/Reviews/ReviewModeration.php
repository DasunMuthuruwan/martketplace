<?php

namespace App\Services\Reviews;

use App\Models\ReviewReport;
use App\Models\SellerReview;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Review moderation (G5b, ADR-027): staff approve or reject comments, keep a reported review or remove
 * it with a reason (it no longer counts or shows). Anyone signed in may report a review once. Staff
 * never moderate reviews about themselves or written by themselves. Every staff action is audited.
 */
class ReviewModeration
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function approveComment(User $staff, SellerReview $review): void
    {
        $this->decide($staff, $review, 'review.comment_approved', ['comment_status' => SellerReview::COMMENT_APPROVED]);
    }

    public function rejectComment(User $staff, SellerReview $review): void
    {
        $this->decide($staff, $review, 'review.comment_rejected', ['comment_status' => SellerReview::COMMENT_REJECTED]);
    }

    /** The reports were unfounded: the review stays as it is. */
    public function keep(User $staff, SellerReview $review): void
    {
        $this->decide($staff, $review, 'review.kept', ['reported_at' => null]);
    }

    public function remove(User $staff, SellerReview $review, string $reason): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => __('admin.reviews.reason_required')]);
        }
        $this->decide($staff, $review, 'review.removed', ['status' => SellerReview::REMOVED, 'removed_reason' => trim($reason), 'reported_at' => null], $reason);
    }

    /**
     * @param  array<string, mixed>  $input  reason, details
     *
     * @throws ValidationException
     */
    public function report(User $reporter, SellerReview $review, array $input): void
    {
        if ($review->reviewer_id === $reporter->id || $review->status !== SellerReview::PUBLISHED) {
            throw ValidationException::withMessages(['reason' => __('reviews.report_not_allowed')]);
        }
        $data = Validator::make($input, [
            'reason' => ['required', Rule::in(ReviewReport::REASONS)],
            'details' => ['nullable', 'string', 'max:500', 'not_regex:/<[^>]*>/'],
        ], ['details.not_regex' => __('listings.html_not_allowed')])->validate();

        try {
            DB::transaction(function () use ($reporter, $review, $data): void {
                $report = new ReviewReport(['reason' => $data['reason'], 'details' => $data['details'] ?? null]);
                $report->forceFill(['review_id' => $review->id, 'reporter_id' => $reporter->id])->save();
                $review->forceFill(['reported_at' => $review->reported_at ?? now()])->save();
            });
        } catch (QueryException) {
            throw ValidationException::withMessages(['reason' => __('reviews.already_reported')]);
        }
    }

    /** @param  array<string, mixed>  $changes */
    private function decide(User $staff, SellerReview $review, string $action, array $changes, ?string $reason = null): void
    {
        if (in_array($staff->id, [$review->seller_id, $review->reviewer_id], true)) {
            throw ValidationException::withMessages(['review' => __('admin.reviews.own')]);
        }
        $review->forceFill($changes + ['moderated_by' => $staff->id, 'moderated_at' => now()])->save();
        $this->audit->record($staff, $action, $review->seller, $reason, ['review' => $review->id, 'rating' => $review->rating]);
    }
}
