<?php

namespace App\Services\Security;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Turnstile server-side verification (ADR-010). Works without Cloudflare DNS.
 * Disabled when no secret key is configured (local development, tests).
 */
class Turnstile
{
    public const RESPONSE_FIELD = 'cf-turnstile-response';

    public function enabled(): bool
    {
        return filled(config('marketplace.turnstile.secret_key')) && filled(config('marketplace.turnstile.site_key'));
    }

    public function siteKey(): ?string
    {
        return config('marketplace.turnstile.site_key');
    }

    public function verify(?string $token, ?string $ip = null): TurnstileResult
    {
        if (! $this->enabled()) {
            return TurnstileResult::Passed;
        }
        if (blank($token) || strlen($token) > 2048) {
            return TurnstileResult::Failed;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('marketplace.turnstile.timeout', 5))
                ->post((string) config('marketplace.turnstile.verify_url'), array_filter([
                    'secret' => config('marketplace.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (ConnectionException $e) {
            Log::warning('Turnstile verification unavailable', ['error' => $e->getMessage()]);

            return TurnstileResult::Unavailable;
        }

        if ($response->serverError()) {
            Log::warning('Turnstile verification unavailable', ['status' => $response->status()]);

            return TurnstileResult::Unavailable;
        }

        return $response->json('success') === true ? TurnstileResult::Passed : TurnstileResult::Failed;
    }
}
