<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;

/**
 * Trusts the proxies listed in TRUSTED_PROXIES (e.g. a CDN in front of the site).
 * Read from config at request time so it works with a cached config.
 */
class TrustConfiguredProxies extends TrustProxies
{
    /**
     * @return array<int, string>|string|null
     */
    protected function proxies(): array|string|null
    {
        $proxies = config('marketplace.trusted_proxies', []);

        return $proxies === ['*'] ? '*' : ($proxies ?: null);
    }
}
