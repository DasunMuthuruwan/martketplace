<?php

namespace App\Services\Reviews;

use App\Enums\ReportStatus;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * "Trusted seller" (G5b, ADR-027 E6): account old enough, enough ads marked sold, a good average from
 * enough reviews, and no upheld report about the seller or their ads in the last 6 months. The
 * thresholds are Owner settings (`reviews.trusted_*`). Cached per seller for an hour.
 */
class TrustBadges
{
    private const UPHELD_REPORT_DAYS = 180;

    public function __construct(private readonly SellerReviews $reviews) {}

    public function isTrusted(User $seller): bool
    {
        return Cache::remember('trusted-seller:'.$seller->id, now()->addHour(), function () use ($seller): bool {
            if ($seller->created_at === null || $seller->created_at->isAfter(now()->subDays((int) config('marketplace.reviews.trusted_min_days')))) {
                return false;
            }
            $sold = Listing::withTrashed()->where('user_id', $seller->id)->whereNotNull('sold_at')->count();
            if ($sold < (int) config('marketplace.reviews.trusted_min_sold')) {
                return false;
            }
            $summary = $this->reviews->summary($seller);
            if ($summary['count'] < (int) config('marketplace.reviews.trusted_min_reviews') || $summary['average'] === null
                || $summary['average'] < (float) config('marketplace.reviews.trusted_min_average')) {
                return false;
            }

            return ! Report::query()
                ->where('status', ReportStatus::Actioned->value)
                ->where('handled_at', '>=', now()->subDays(self::UPHELD_REPORT_DAYS))
                ->where(fn ($q) => $q->where(fn ($q) => $q->where('reportable_type', 'user')->where('reportable_id', $seller->id))
                    ->orWhere(fn ($q) => $q->where('reportable_type', 'listing')
                        ->whereIn('reportable_id', Listing::withTrashed()->where('user_id', $seller->id)->select('id'))))
                ->exists();
        });
    }
}
