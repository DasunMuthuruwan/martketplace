<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts;

/**
 * Trusts only the host of APP_URL plus optional temporary aliases (APP_ALIASES).
 *
 * Unlike Laravel's default, subdomains are not trusted and the check also runs in
 * the testing environment. Requests with any other Host header are rejected, so
 * password-reset and verification links can't be generated for a forged host.
 */
class TrustConfiguredHosts extends TrustHosts
{
    /**
     * @return array<int, string>
     */
    public function hosts(): array
    {
        $hosts = [parse_url((string) config('app.url'), PHP_URL_HOST)];

        foreach ((array) config('marketplace.app_aliases', []) as $alias) {
            $hosts[] = parse_url(str_contains($alias, '://') ? $alias : 'https://'.$alias, PHP_URL_HOST);
        }

        return array_values(array_map(
            fn (string $host): string => '^'.preg_quote($host).'$',
            array_unique(array_filter($hosts)),
        ));
    }

    protected function shouldSpecifyTrustedHosts(): bool
    {
        return ! $this->app->environment('local');
    }
}
