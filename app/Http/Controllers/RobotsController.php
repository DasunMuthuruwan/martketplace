<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * `/robots.txt` (SEO-03, architecture §8). Generated so it follows SEO_INDEXING: staging and an
 * old domain during a migration block everything (P10); production allows crawling except
 * private areas and points to the sitemap on APP_URL.
 */
class RobotsController extends Controller
{
    /** Never useful in search results. */
    private const PRIVATE_PATHS = ['/account', '/admin', '/user', '/login', '/register', '/forgot-password', '/reset-password', '/email', '/two-factor-challenge', '/listings/', '/reports', '/country'];

    public function __invoke(): Response
    {
        $lines = ['User-agent: *'];

        if (config('marketplace.seo.indexing')) {
            foreach (self::PRIVATE_PATHS as $path) {
                $lines[] = 'Disallow: '.$path;
            }
            $lines[] = '';
            $lines[] = 'Sitemap: '.url('sitemap.xml');
        } else {
            $lines[] = 'Disallow: /';
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
