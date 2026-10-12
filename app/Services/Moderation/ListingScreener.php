<?php

namespace App\Services\Moderation;

use App\Enums\ListingStatus;
use App\Models\BlockedTerm;
use App\Models\Listing;
use Illuminate\Support\Str;

/**
 * Checks an ad before it is submitted (security doc §5, MOD-03, LST-10):
 * - blocked terms refuse the ad; flagged terms send it to review even when moderation is off;
 * - a phone number or email typed into the text is flagged (sellers should use contact settings);
 * - the same seller posting the same title in the same category within the window is flagged.
 * Re-run by the moderation screen (M6) to show the reasons; only the `flagged` bit is stored.
 */
class ListingScreener
{
    public const DUPLICATE_WINDOW_DAYS = 7;

    public function screen(Listing $listing): ScreeningResult
    {
        $text = mb_strtolower($listing->title.' '.$listing->description);
        $blocked = [];
        $flags = [];

        foreach (BlockedTerm::forCountry($listing->country_id)->get() as $term) {
            if (! $this->matches($text, $term)) {
                continue;
            }
            if ($term->action === BlockedTerm::ACTION_BLOCK) {
                $blocked[] = $term->term;
            } else {
                $flags[] = 'term:'.$term->term;
            }
        }

        if ($this->containsContactDetails((string) $listing->description.' '.$listing->title)) {
            $flags[] = 'contact_in_text';
        }
        if ($this->isDuplicate($listing)) {
            $flags[] = 'duplicate';
        }

        return new ScreeningResult(array_values(array_unique($blocked)), $flags);
    }

    private function matches(string $text, BlockedTerm $term): bool
    {
        if ($term->match === BlockedTerm::MATCH_PHRASE) {
            return str_contains($text, $term->term);
        }

        // Whole word in any script: no letter or digit directly before or after.
        return (bool) preg_match('/(?<![\p{L}\p{N}])'.preg_quote($term->term, '/').'(?![\p{L}\p{N}])/u', $text);
    }

    private function containsContactDetails(string $text): bool
    {
        $email = '/[\p{L}\p{N}._%+-]+@[\p{L}\p{N}.-]+\.[\p{L}]{2,}/u';
        // Seven or more digits, allowing spaces, dots, dashes and brackets between them.
        $phone = '/(?:\+?\d[\s.\-()]*){7,}/';

        return (bool) preg_match($email, $text) || (bool) preg_match($phone, $text);
    }

    private function isDuplicate(Listing $listing): bool
    {
        $title = Str::lower(Str::squish((string) $listing->title));

        return Listing::where('user_id', $listing->user_id)
            ->where('category_id', $listing->category_id)
            ->whereKeyNot($listing->id)
            ->where('status', '!=', ListingStatus::Draft->value)
            ->where('created_at', '>=', now()->subDays(self::DUPLICATE_WINDOW_DAYS))
            ->pluck('title')
            ->contains(fn (?string $other) => Str::lower(Str::squish((string) $other)) === $title);
    }
}
