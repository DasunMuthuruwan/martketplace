<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Hourly email budget (architecture §10, CD-09): shared hosts and mail providers cap messages per
 * hour. Every message sent counts (MessageSending listener, so immediate mail such as password
 * resets is counted too); queued notifications wait for the next hour when the budget is spent
 * (RespectMailBudget). Immediate mail is never blocked: it is critical and low volume.
 */
final class MailBudget
{
    public static function limit(): int
    {
        return max(0, (int) config('marketplace.mail.hourly_limit'));
    }

    public static function used(): int
    {
        return (int) Cache::get(self::key(), 0);
    }

    public static function remaining(): int
    {
        return max(0, self::limit() - self::used());
    }

    public static function record(): void
    {
        Cache::add(self::key(), 0, now()->addHours(2));
        Cache::increment(self::key());
    }

    /** Seconds until the budget renews (start of the next hour), plus a small spread. */
    public static function secondsUntilReset(): int
    {
        return (int) now()->diffInSeconds(Carbon::now()->startOfHour()->addHour(), true) + random_int(5, 120);
    }

    private static function key(): string
    {
        return 'mail-budget:'.now()->format('YmdH');
    }
}
