<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Telegram exception alerts
    |--------------------------------------------------------------------------
    |
    | Sentry's free plan cannot post to chat apps, so uncaught exceptions are also pushed to Telegram by the
    | application itself (App\Services\Ops\TelegramNotifier), once per distinct error per throttle window.
    | Credentials live in config/services.php. Nothing is sent while they are missing.
    |
    */

    'telegram' => [
        'exception_alerts' => (bool) env('OPS_TELEGRAM_EXCEPTION_ALERTS', true),
        'exception_throttle_minutes' => (int) env('OPS_TELEGRAM_EXCEPTION_THROTTLE_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health endpoint
    |--------------------------------------------------------------------------
    |
    | GET /api/health. The scheduler counts as dead when the queue check it runs every five minutes has not
    | recorded a result for this long. No result at all (fresh deploy, cache cleared) is not held against it.
    |
    */

    'health' => [
        'scheduler_max_age_minutes' => (int) env('OPS_HEALTH_SCHEDULER_MAX_AGE_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Invariant checks (php artisan ops:check-invariants, every 15 minutes)
    |--------------------------------------------------------------------------
    |
    | Silent failures: nothing throws, the numbers just stop adding up. See App\Services\Ops\InvariantChecker.
    | A problem is announced on Telegram once per alert window while it lasts.
    |
    */

    'invariants' => [
        'alert_window_minutes' => (int) env('OPS_INVARIANTS_ALERT_WINDOW_MINUTES', 360),
        // Orders awaiting payment this long should have been closed by Stripe's expiry webhook (raise it if you accept slow bank transfers)
        'payment_pending_hours' => (int) env('OPS_PAYMENT_PENDING_HOURS', 72),
        'paid_waiting_days' => (int) env('OPS_PAID_WAITING_DAYS', 3),
        'failed_email_hours' => (int) env('OPS_FAILED_EMAIL_HOURS', 24),
        'webhook_grace_minutes' => (int) env('OPS_WEBHOOK_GRACE_MINUTES', 15),
        // Visitors sent to Stripe in 24h with zero paid orders before it counts as broken. 0 switches the check off.
        'payments_without_orders' => (int) env('OPS_PAYMENTS_WITHOUT_ORDERS', 10),
        // Alert when no paid order arrives for this many hours. 0 (default) switches it off: a quiet shop is not a broken one.
        'no_orders_hours' => (int) env('OPS_NO_ORDERS_HOURS', 0),
        'funnel' => [
            'min_baseline' => (int) env('OPS_FUNNEL_MIN_BASELINE', 20),
            'min_recent' => (int) env('OPS_FUNNEL_MIN_RECENT', 10),
            // The recent step rate must fall below this share of the weekly rate to count as a drop
            'drop_ratio' => (float) env('OPS_FUNNEL_DROP_RATIO', 0.4),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Abuse signals (App\Services\Ops\AbuseMonitor)
    |--------------------------------------------------------------------------
    |
    | A signal that reaches `threshold` events within `window_minutes` is pushed to Telegram, once per window.
    | The monitor only watches; blocking is the job of the rate limiters, Cloudflare and Stripe Radar.
    |
    */

    'abuse' => [
        'failed_login_per_ip' => ['threshold' => 10, 'window_minutes' => 10],
        'failed_login_admin_account' => ['threshold' => 3, 'window_minutes' => 10],
        'rate_limited_per_ip' => ['threshold' => 30, 'window_minutes' => 10],
        // Needs payment_intent.payment_failed enabled on the Stripe webhook endpoint
        'payment_failed' => ['threshold' => 5, 'window_minutes' => 10],
        'webhook_rejected' => ['threshold' => 1, 'window_minutes' => 60],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduler heartbeat
    |--------------------------------------------------------------------------
    |
    | A ping URL (healthchecks.io, Better Stack, Sentry Crons, ...) called every five minutes by the scheduler.
    | The external service alerts when the pings stop, which is the only way to notice a dead scheduler.
    |
    */

    'heartbeat_url' => env('OPS_HEARTBEAT_URL'),

];
