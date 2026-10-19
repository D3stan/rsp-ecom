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
// sendOutputTo (not appendOutputTo): the file holds only the last run, so it can never grow without bound.
Schedule::command('generate:sitemap-index')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->sendOutputTo(storage_path('logs/sitemap.log'));

// Also keep the simple sitemap command for manual generation if needed
Schedule::command('generate:sitemap')
    ->weeklyOn(7, '03:00') // Run weekly on Sunday at 3 AM as backup
    ->withoutOverlapping()
    ->sendOutputTo(storage_path('logs/sitemap-simple.log'));

// Safety net for confirmation emails whose queue job was lost or never created (7-day window).
// Goes through EmailService, so it can never produce a duplicate mail. Its console output is not kept (it ran
// every ten minutes and the file grew without rotation); failures and backlog warnings go to the application log.
Schedule::command('orders:send-pending-emails --force')
    ->everyTenMinutes()
    ->withoutOverlapping();

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

// Poll carriers for shipment updates. Runs while the default tracking driver or any shipped order's
// tracking_driver contacts a carrier; when all orders are manual/null it never runs.
Schedule::command('tracking:sync')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->when(fn () => app(TrackingManager::class)->canPollAny());

// Checkout snapshots: drop abandoned ones after 7 days and consumed ones after 90 days (see CheckoutSnapshot::prunable)
Schedule::command('model:prune', ['--model' => \App\Models\CheckoutSnapshot::class])
    ->daily()
    ->withoutOverlapping();

// Newsletter: start scheduled campaigns when they are due (the sending itself runs on the queue, rate-limited)
Schedule::command('newsletter:dispatch-scheduled')
    ->everyMinute()
    ->withoutOverlapping();

// Consented review-request emails for delivered orders. No-op while reviews.request_enabled is off; the command
// checks marketing consent and OrderEmail dedupe, so a daily run never produces a second request for an order.
Schedule::command('reviews:send-requests')
    ->dailyAt('09:00')
    ->withoutOverlapping();
