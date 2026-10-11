<?php

namespace App\Rules;

use App\Services\Security\Turnstile;
use App\Services\Security\TurnstileResult;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates the Turnstile widget token. Passes when Turnstile isn't configured.
 * $failOpen = true lets the request through when Cloudflare is unreachable (used where the
 * content is moderated anyway, e.g. listing submission); sign-up fails closed.
 */
class PassesTurnstile implements ValidationRule
{
    public function __construct(private readonly bool $failOpen = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $result = app(Turnstile::class)->verify(is_string($value) ? $value : null, request()->ip());

        if ($result === TurnstileResult::Passed || ($result === TurnstileResult::Unavailable && $this->failOpen)) {
            return;
        }

        $fail($result === TurnstileResult::Unavailable ? __('security.turnstile_unavailable') : __('security.turnstile_failed'));
    }
}
