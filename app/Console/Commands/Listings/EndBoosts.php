<?php

namespace App\Console\Commands\Listings;

use App\Services\Boosts\BoostManager;
use App\Services\Boosts\BoostOrders;
use Illuminate\Console\Command;

/**
 * Hourly boost housekeeping (ADR-026):
 * - a boost ends with its ad (sold, deleted, expired, removed by a moderator, owner suspended), so
 *   it does not come back if the ad does (proposal §4.6); boosts past their end need nothing;
 * - unpaid orders past their payment window lapse (B5);
 * - "your Top ad ends soon" goes out once for paid Top ads ending within a day (B5).
 */
class EndBoosts extends Command
{
    protected $signature = 'marketplace:end-boosts';

    protected $description = 'End boosts of ads that are no longer public, expire unpaid orders, send Top ad reminders';

    public function handle(BoostManager $boosts, BoostOrders $orders): int
    {
        $this->info('Boosts ended: '.$boosts->endWhereAdNotLive());
        $this->info('Unpaid orders expired: '.$orders->expireOverdue());
        $this->info('Ending reminders sent: '.$orders->sendEndingReminders());

        return self::SUCCESS;
    }
}
