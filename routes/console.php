<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule sitemap generation daily at 2 AM
// Use the index version for better performance with large product catalogs
Schedule::command('generate:sitemap-index')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/sitemap.log'));

// Also keep the simple sitemap command for manual generation if needed
Schedule::command('generate:sitemap')
    ->weeklyOn(7, '03:00') // Run weekly on Sunday at 3 AM as backup
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/sitemap-simple.log'));
