<?php

namespace App\Jobs\Middleware;

use App\Support\MailBudget;
use Closure;

/**
 * Queued mail waits for the next hour when the hourly budget is spent (MailBudget). The job is
 * released, not failed, so nothing is lost; the attempt does not count against its retries.
 */
class RespectMailBudget
{
    public function handle(object $job, Closure $next): mixed
    {
        if (MailBudget::remaining() > 0) {
            return $next($job);
        }

        if (method_exists($job, 'release')) {
            $job->release(MailBudget::secondsUntilReset());
        }

        return null;
    }
}
