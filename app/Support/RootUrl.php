<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

/**
 * Makes APP_URL the only source of absolute URLs (portability rule P3).
 *
 * Queued mail, scheduled jobs and CLI commands have no request host, so links,
 * canonicals and sitemaps always come out with the configured domain.
 */
class RootUrl
{
    public static function configure(): void
    {
        $url = rtrim((string) config('app.url'), '/');

        if ($url !== '') {
            URL::forceRootUrl($url);
            // forceRootUrl alone keeps the current request's scheme; pin APP_URL's scheme too.
            URL::forceScheme((string) parse_url($url, PHP_URL_SCHEME));
        }

        if (app()->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
