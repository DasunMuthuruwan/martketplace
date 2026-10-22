<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Requests from crawlers, link previewers, monitors and scripts. They are not counted in ad views
 * (B1) or search terms (G1). An empty user agent counts as a bot.
 */
final class Bots
{
    private const PATTERN = '/bot|crawl|spider|slurp|facebookexternalhit|embedly|preview|monitor|curl|wget|python|java\/|go-http|headless|lighthouse/i';

    public static function is(Request $request): bool
    {
        $agent = (string) $request->userAgent();

        return $agent === '' || preg_match(self::PATTERN, $agent) === 1;
    }
}
