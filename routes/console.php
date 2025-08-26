<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule automated data synchronization and cleanup
Schedule::command('sync:all-users')
    ->hourly()
    ->between('6:00', '23:00') // Только в активное время
    ->name('sync-all-users')
    ->withoutOverlapping(120); // Максимум 2 часа на выполнение

Schedule::command('sync:all-users --force')
    ->dailyAt('02:00') // Полная синхронизация ночью
    ->name('daily-full-sync')
    ->withoutOverlapping(180); // Максимум 3 часа на выполнение

Schedule::command('cleanup:old-data')
    ->weeklyOn(1, '03:00') // Каждый понедельник в 3:00
    ->name('weekly-cleanup')
    ->withoutOverlapping(60);
