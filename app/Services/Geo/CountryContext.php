<?php

namespace App\Services\Geo;

use App\Models\Country;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The visitor's current country (global design §6, G-2), resolved once per request:
 *   1. the `{cc}` route segment (set by the ResolveCountry middleware);
 *   2. the remembered choice (cookie);
 *   3. Cloudflare's CF-IPCountry header, only when enabled and the request came through a
 *      trusted proxy (otherwise anyone could send the header);
 *   4. the site default (MARKETPLACE_DEFAULT_COUNTRY).
 * Only active countries are ever returned. Null means nothing is known: the home page shows the
 * country chooser. No paid geo-IP service is used.
 */
class CountryContext
{
    private ?Country $current = null;

    /** The request the cached answer belongs to (the service may outlive a request). */
    private ?Request $resolvedFor = null;

    /** Set from the URL by ResolveCountry; always wins for that request. */
    public function set(Request $request, Country $country): void
    {
        $this->current = $country;
        $this->resolvedFor = $request;
    }

    public function current(Request $request): ?Country
    {
        if ($this->resolvedFor !== $request) {
            $this->current = $this->fromCookie($request) ?? $this->fromCloudflare($request) ?? $this->fromDefault();
            $this->resolvedFor = $request;
        }

        return $this->current;
    }

    /** Active country by ISO 3166-1 alpha-2 code, any case. */
    public function find(?string $iso2): ?Country
    {
        if ($iso2 === null || ! preg_match('/^[A-Za-z]{2}$/', $iso2)) {
            return null;
        }

        return Country::active()->where('iso2', strtoupper($iso2))->first();
    }

    /** Cookie remembering the visitor's choice (host-only, like the session cookie). */
    public function rememberCookie(Country $country): Cookie
    {
        return cookie(
            (string) config('marketplace.geo.country_cookie'),
            strtolower($country->iso2),
            (int) config('marketplace.geo.country_cookie_days') * 24 * 60,
            '/',
            null,
            config('session.secure'),
            true,
            false,
            'lax',
        );
    }

    private function fromCookie(Request $request): ?Country
    {
        return $this->find($request->cookie((string) config('marketplace.geo.country_cookie')));
    }

    private function fromCloudflare(Request $request): ?Country
    {
        if (! config('marketplace.geo.use_cf_ip_country') || ! $request->isFromTrustedProxy()) {
            return null;
        }

        // Cloudflare sends XX (unknown) and T1 (Tor); find() ignores both as they are not active countries.
        return $this->find($request->header('CF-IPCountry'));
    }

    private function fromDefault(): ?Country
    {
        return $this->find(config('marketplace.default_country'));
    }
}
