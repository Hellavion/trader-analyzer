<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule Bybit data synchronization
Schedule::command('bybit:sync')->hourly()->name('bybit-sync')->withoutOverlapping();
Schedule::command('bybit:sync --market-data')->everyFourHours()->name('bybit-market-data-sync');
