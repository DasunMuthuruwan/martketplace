<?php

namespace App\Services\Moderation;

final class ScreeningResult
{
    /**
     * @param  list<string>  $blocked  blocked terms found (the ad is refused)
     * @param  list<string>  $flags  reasons to review (term:<term>, contact_in_text, duplicate)
     */
    public function __construct(
        public readonly array $blocked,
        public readonly array $flags,
    ) {}

    public function isBlocked(): bool
    {
        return $this->blocked !== [];
    }

    public function isFlagged(): bool
    {
        return $this->flags !== [];
    }
}
