<?php

namespace App\Services\Reviews;

use App\Models\ContactReveal;
use App\Models\SellerReview;
use App\Models\User;
use App\Notifications\SellerReviewed;
use App\Services\Moderation\ListingScreener;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Seller ratings (G5, ADR-027 E2). The marketplace never records a sale, so only a buyer who
 * revealed one of the seller's numbers between `reviews.min_days` and `reviews.max_days` ago may
 * rate them: verified, active, account at least `reviews.min_account_days` old, never the seller.
 * One review per buyer and seller (updating replaces it). Stars count at once; a comment waits for
 * moderation and is screened by the blocklist; the average shows from `reviews.min_for_average`.
 */
class SellerReviews
{
    public const COMMENT_MAX = 1000;

    public const REPLY_MAX = 1000;

    public function __construct(private readonly ListingScreener $screener) {}

    /** The ad the buyer contacted the seller about, if the buyer may rate the seller now. */
    public function eligibleListing(?User $buyer, User $seller): ?int
    {
        if ($buyer === null || $buyer->id === $seller->id || ! $buyer->hasVerifiedEmail() || ! $buyer->isActive()
            || $buyer->created_at === null || $buyer->created_at->isAfter(now()->subDays((int) config('marketplace.reviews.min_account_days')))) {
            return null;
        }

        $id = ContactReveal::query()
            ->join('listings', 'listings.id', '=', 'contact_reveals.listing_id')
            ->where('contact_reveals.user_id', $buyer->id)
            ->where('listings.user_id', $seller->id)
            ->whereBetween('contact_reveals.created_at', [
                now()->subDays((int) config('marketplace.reviews.max_days')),
                now()->subDays((int) config('marketplace.reviews.min_days')),
            ])
            ->orderByDesc('contact_reveals.created_at')
            ->value('contact_reveals.listing_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Rate a seller, or update one's rating of them.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function submit(User $buyer, User $seller, array $input): SellerReview
    {
        $listingId = $this->eligibleListing($buyer, $seller);
        if ($listingId === null) {
            throw ValidationException::withMessages(['rating' => __('reviews.not_eligible')]);
        }
        $data = Validator::make($input, [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:'.self::COMMENT_MAX, 'not_regex:/<[^>]*>/'],
            'deal' => ['nullable', Rule::in(['yes', 'no'])],
        ], ['comment.not_regex' => __('listings.html_not_allowed')], ['rating' => __('reviews.rating'), 'comment' => __('reviews.comment')])->validate();

        $comment = trim((string) ($data['comment'] ?? ''));
        $countryId = DB::table('listings')->where('id', $listingId)->value('country_id');
        if ($comment !== '' && $this->screener->screenText($comment, $countryId === null ? null : (int) $countryId)->isBlocked()) {
            throw ValidationException::withMessages(['comment' => __('reviews.comment_blocked')]);
        }

        $review = SellerReview::where('seller_id', $seller->id)->where('reviewer_id', $buyer->id)->first() ?? new SellerReview;
        $isNew = ! $review->exists;
        $commentChanged = $comment !== (string) $review->comment;
        $review->forceFill([
            'seller_id' => $seller->id,
            'reviewer_id' => $buyer->id,
            'listing_id' => $listingId,
            'country_id' => $countryId,
            'rating' => (int) $data['rating'],
            'deal' => $data['deal'] ?? null,
            'comment' => $comment === '' ? null : $comment,
            'comment_status' => $comment === '' ? SellerReview::COMMENT_NONE : ($commentChanged ? SellerReview::COMMENT_PENDING : $review->comment_status),
            'status' => $review->exists ? $review->status : SellerReview::PUBLISHED,
        ])->save();

        if ($isNew) {
            $seller->notify(new SellerReviewed($review));
        }

        return $review;
    }

    /** The seller answers a review once; screened like comments. */
    public function reply(User $seller, SellerReview $review, string $text): void
    {
        if ($review->seller_id !== $seller->id || $review->status !== SellerReview::PUBLISHED) {
            throw ValidationException::withMessages(['reply' => __('reviews.reply_not_allowed')]);
        }
        if ($review->reply !== null) {
            throw ValidationException::withMessages(['reply' => __('reviews.reply_once')]);
        }
        $text = trim($text);
        Validator::make(['reply' => $text], ['reply' => ['required', 'string', 'max:'.self::REPLY_MAX, 'not_regex:/<[^>]*>/']],
            ['reply.not_regex' => __('listings.html_not_allowed')], ['reply' => __('reviews.reply')])->validate();
        if ($this->screener->screenText($text, $review->country_id)->isBlocked()) {
            throw ValidationException::withMessages(['reply' => __('reviews.comment_blocked')]);
        }
        $review->forceFill(['reply' => $text, 'replied_at' => now()])->save();
    }

    /** @return array{count: int, average: float|null} the average only from `reviews.min_for_average` reviews */
    public function summary(User $seller): array
    {
        $row = SellerReview::query()->published()->where('seller_id', $seller->id)
            ->selectRaw('COUNT(*) as total, AVG(rating) as average')->toBase()->first();
        $count = (int) ($row->total ?? 0);

        return [
            'count' => $count,
            'average' => $count >= (int) config('marketplace.reviews.min_for_average') ? round((float) $row->average, 1) : null,
        ];
    }

    /** @return LengthAwarePaginator<int, SellerReview> */
    public function published(User $seller, int $perPage = 10): LengthAwarePaginator
    {
        return SellerReview::query()->published()->where('seller_id', $seller->id)
            ->with('reviewer')->latest()->latest('id')
            ->paginate($perPage, ['*'], 'reviews_page');
    }
}
