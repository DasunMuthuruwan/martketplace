<?php

namespace App\View;

use App\Models\Listing;

/**
 * The six posting steps (layout doc §3.4, global UX §2–§4) and how far a draft has got. A step is
 * reachable once the steps before it are done; the first open step is where "Continue" leads.
 */
final class PostingSteps
{
    public const STEPS = ['category', 'location', 'details', 'price', 'photos', 'review'];

    public function __construct(private readonly ?Listing $listing) {}

    public function done(string $step): bool
    {
        $l = $this->listing;
        if ($l === null) {
            return false;
        }

        return match ($step) {
            'category', 'location' => true, // a draft exists only once both are saved
            'details' => filled($l->title) && filled($l->description),
            'price' => $l->price_type !== null,
            'photos' => $l->images()->exists(),
            default => false,
        };
    }

    public function reachable(string $step): bool
    {
        foreach (self::STEPS as $previous) {
            if ($previous === $step) {
                return true;
            }
            if (! $this->done($previous)) {
                return false;
            }
        }

        return false;
    }

    /** First step that still needs work (review when everything is done). */
    public function next(): string
    {
        foreach (self::STEPS as $step) {
            if (! $this->done($step)) {
                return $step;
            }
        }

        return 'review';
    }

    public function after(string $step): ?string
    {
        $i = array_search($step, self::STEPS, true);

        return $i === false ? null : (self::STEPS[$i + 1] ?? null);
    }

    public function before(string $step): ?string
    {
        $i = array_search($step, self::STEPS, true);

        return $i === false || $i === 0 ? null : self::STEPS[$i - 1];
    }
}
