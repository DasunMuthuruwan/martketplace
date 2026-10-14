<?php

namespace App\Notifications\Concerns;

use App\Jobs\Middleware\RespectMailBudget;

/** Queued notifications: the mail channel waits for the hourly budget; other channels never do. */
trait UsesMailBudget
{
    /** @return list<object> */
    public function middleware(object $notifiable, string $channel): array
    {
        return $channel === 'mail' ? [new RespectMailBudget] : [];
    }

    /** Released jobs come back after the hour; give them a day before they count as failed. */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addDay();
    }
}
