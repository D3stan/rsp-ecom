<?php

use App\Shipping\Tracking\TrackingManager;
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

// Safety net for confirmation emails whose queue job was lost or never created (7-day window).
// Goes through EmailService, so it can never produce a duplicate mail.
Schedule::command('orders:send-pending-emails --force')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/pending-order-emails.log'));

// Drop expired pending registrations daily
Schedule::command('auth:cleanup-pending --force')
    ->dailyAt('03:00')
    ->withoutOverlapping();

// Keep failed_jobs bounded: retain 7 days (168h) for inspection/retry
Schedule::command('queue:prune-failed --hours=168')
    ->daily()
    ->withoutOverlapping();

// Alerts (critical log) when the queue worker is down or failed jobs pile up. Needs the scheduler itself
// to be running: monitor /up externally as the complement (see docs/reports/email-operations.md).
Schedule::command('ops:check-queue')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Poll carriers for shipment updates. Runs only while the configured tracking driver actually contacts a
// carrier (SHIPPING_TRACKING_DRIVER); with manual/null it never runs. See docs/shipping-tracking-module.md.
Schedule::command('tracking:sync')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->when(fn () => app(TrackingManager::class)->canPoll());
