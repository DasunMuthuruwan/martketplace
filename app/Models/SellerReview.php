<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A buyer's rating of a seller (G5, ADR-027 E2). Written only through SellerReviews (no mass
 * assignment). Stars count while `status` is published; the comment shows once approved.
 *
 * @property int $id
 * @property int $seller_id
 * @property int|null $reviewer_id
 * @property int|null $listing_id
 * @property int|null $country_id
 * @property int $rating
 * @property string|null $comment
 * @property string $comment_status
 * @property string|null $deal
 * @property string|null $reply
 * @property Carbon|null $replied_at
 * @property string $status
 * @property Carbon|null $reported_at
 * @property string|null $removed_reason
 * @property int|null $moderated_by
 * @property Carbon|null $moderated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SellerReview extends Model
{
    public const PUBLISHED = 'published';

    public const REMOVED = 'removed';

    public const COMMENT_NONE = 'none';

    public const COMMENT_PENDING = 'pending';

    public const COMMENT_APPROVED = 'approved';

    public const COMMENT_REJECTED = 'rejected';

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'replied_at' => 'datetime',
            'reported_at' => 'datetime',
            'moderated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class)->withTrashed();
    }

    /** @return HasMany<ReviewReport, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(ReviewReport::class, 'review_id');
    }

    /** @param  Builder<SellerReview>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('seller_reviews.status', self::PUBLISHED);
    }

    public function commentShown(): bool
    {
        return $this->comment !== null && $this->comment_status === self::COMMENT_APPROVED;
    }

    /** "Nimal P.": first name and initial only, on public pages. */
    public function reviewerName(): string
    {
        $name = trim((string) $this->reviewer?->name);
        if ($name === '' || $this->reviewer?->anonymised_at !== null) { // deleted accounts keep their reviews, not their name
            return (string) __('account.deleted_user');
        }
        $parts = preg_split('/\s+/u', $name) ?: [$name];

        return count($parts) > 1 ? $parts[0].' '.mb_substr((string) end($parts), 0, 1).'.' : $parts[0];
    }
}
