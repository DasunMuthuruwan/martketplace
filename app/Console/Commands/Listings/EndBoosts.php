<?php

namespace App\Console\Commands\Listings;

use App\Services\Boosts\BoostManager;
use Illuminate\Console\Command;

/**
 * Hourly: a boost ends with its ad (sold, deleted, expired, removed by a moderator, owner suspended),
 * so it does not come back if the ad does (ADR-026, proposal §4.6). Boosts past their end need
 * nothing: every query checks the end time.
 */
class EndBoosts extends Command
{
    protected $signature = 'marketplace:end-boosts';

    protected $description = 'End boosts of ads that are no longer public';

    public function handle(BoostManager $boosts): int
    {
        $ended = $boosts->endWhereAdNotLive();
        $this->info("Boosts ended: {$ended}");

        return self::SUCCESS;
    }
}
