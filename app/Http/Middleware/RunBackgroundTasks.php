<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Web runner (ADR-006, architecture §6): on hosts without an every-minute cron, drain the queue
 * after a response has been sent (Symfony flushes it with fastcgi_finish_request or
 * litespeed_finish_request before terminate runs). At most once per interval across all
 * visitors (atomic cache lock, not released, so it doubles as the throttle), for at most
 * `max_seconds`. Off by default (WEB_RUNNER_ENABLED); on a VPS a real worker replaces it.
 */
class RunBackgroundTasks
{
    public const LOCK = 'web-runner';

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! config('marketplace.web_runner.enabled') || config('queue.default') === 'sync') {
            return;
        }

        $interval = max(10, (int) config('marketplace.web_runner.interval'));
        if (! Cache::lock(self::LOCK, $interval)->get()) {
            return; // another visitor's request ran it within the interval
        }

        ignore_user_abort(true);
        try {
            Artisan::call('queue:work', [
                '--stop-when-empty' => true,
                '--max-time' => max(1, (int) config('marketplace.web_runner.max_seconds')),
                '--sleep' => 0,
                '--tries' => 3,
                '--quiet' => true,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
