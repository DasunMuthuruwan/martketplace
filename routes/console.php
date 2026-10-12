<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Housekeeping (security doc §4). Runs from `schedule:run` at whatever interval the host allows.
Schedule::command('marketplace:prune-images')->daily()->withoutOverlapping();
