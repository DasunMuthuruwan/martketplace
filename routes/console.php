<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Housekeeping (security doc §4). Runs from `schedule:run` at whatever interval the host allows.
Schedule::command('marketplace:prune-images')->daily()->withoutOverlapping();
Schedule::command('marketplace:expire-listings')->hourly()->withoutOverlapping();
// Retention (docs/architecture/09-data-retention.md): Prunable models, e.g. contact reveals after 90 days.
Schedule::command('model:prune')->daily()->withoutOverlapping();
Schedule::command('marketplace:prune')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('sitemap:generate')->daily()->withoutOverlapping();

// Queue (architecture §6): every scheduler run drains the queue for under a minute. With a slow
// cron, the optional web runner (RunBackgroundTasks) keeps mail moving in between.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=720')->daily();
Schedule::command('auth:clear-resets')->daily();
