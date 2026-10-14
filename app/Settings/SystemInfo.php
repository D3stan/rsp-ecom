<?php

namespace App\Settings;

use App\Jobs\SendOrderEmail;
use App\Jobs\SendUserNotification;
use App\Jobs\SendWelcomeEmail;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Throwable;

/**
 * Read-only description of how the shop is wired (the "System" panel of the settings page). Values come from
 * config/env/code and can only be changed there. Never contains secrets: only names, numbers and yes/no flags
 * (Stripe is reported as configured or not, never by key).
 */
class SystemInfo
{
    public const QUEUE_HEALTH_CACHE_KEY = 'ops:queue-health';

    /**
     * @return array{sections: array<int, array<string, mixed>>}
     */
    public function panel(): array
    {
        return [
            'sections' => [
                $this->section('legal', [
                    $this->item('withdrawal_days', (int) settings('legal.withdrawal_days', config('shipping.withdrawal_days')), 'number', 'config'),
                ]),
                $this->section('integrations', [
                    $this->item('stripe_configured', filled(config('cashier.secret')) && filled(config('cashier.key')), 'bool', 'env'),
                    $this->item('mailer', (string) config('mail.default'), 'text', 'env'),
                    $this->item('tracking_driver', (string) config('shipping.tracking.driver'), 'text', 'env'),
                ]),
                $this->section('queue', [
                    $this->item('queue_connection', (string) config('queue.default'), 'text', 'env'),
                    $this->item('queue_health', $this->queueHealth(), 'status', 'runtime'),
                    $this->item('queue_alert_max_job_age', (int) config('mail.queue_alerts.max_job_age_minutes'), 'number', 'env', 'minutes'),
                    $this->item('queue_alert_failed_threshold', (int) config('mail.queue_alerts.failed_jobs_threshold'), 'number', 'env'),
                    $this->item('job_retries', $this->jobRetries(), 'table', 'code'),
                ]),
                $this->section('operations', [
                    $this->item('scheduled_tasks', $this->scheduledTasks(), 'table', 'code'),
                    $this->item('rate_limits', $this->rateLimits(), 'table', 'code'),
                ]),
            ],
        ];
    }

    /**
     * ok | problem | unknown, from the last `ops:check-queue` run.
     *
     * @return array{status: string, checked_at: string|null}
     */
    public function queueHealth(): array
    {
        try {
            $last = Cache::get(self::QUEUE_HEALTH_CACHE_KEY);
        } catch (Throwable) {
            $last = null;
        }

        if (! is_array($last) || ! isset($last['status'])) {
            return ['status' => 'unknown', 'checked_at' => null];
        }

        return ['status' => (string) $last['status'], 'checked_at' => $last['checked_at'] ?? null];
    }

    /**
     * @return array<int, array{name: string, tries: int, timeout: int, backoff: string}>
     */
    private function jobRetries(): array
    {
        $rows = [];

        foreach ([SendOrderEmail::class, SendUserNotification::class, SendWelcomeEmail::class] as $job) {
            $defaults = (new ReflectionClass($job))->getDefaultProperties();
            $rows[] = [
                'name' => class_basename($job),
                'tries' => (int) ($defaults['tries'] ?? 1),
                'timeout' => (int) ($defaults['timeout'] ?? 0),
                // the backoff() of these jobs: 1m, 5m, 15m, 1h
                'backoff' => '60, 300, 900, 3600',
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{name: string, cron: string}>
     */
    private function scheduledTasks(): array
    {
        try {
            // Scheduled tasks are declared in routes/console.php, which only the console kernel loads
            app(Kernel::class)->all();

            return collect(app(Schedule::class)->events())
                ->map(fn (ScheduledEvent $event) => [
                    'name' => $this->taskName($event),
                    'cron' => $event->expression,
                ])
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function taskName(ScheduledEvent $event): string
    {
        $command = (string) ($event->command ?? '');

        if (preg_match('/artisan[\'"]?\s+(.+)$/', $command, $matches)) {
            return trim($matches[1], '\'" ');
        }

        return $event->description ?: ($command !== '' ? $command : 'closure');
    }

    /**
     * Throttled routes, read from the route middleware (throttle:attempts,minutes).
     *
     * @return array<int, array{name: string, limit: string}>
     */
    private function rateLimits(): array
    {
        $limits = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && preg_match('/^throttle:(\d+),(\d+)/', $middleware, $m)) {
                    $limits[] = [
                        'name' => $route->getName() ?? $route->uri(),
                        'limit' => "{$m[1]} / {$m[2]} min",
                    ];
                }
            }
        }

        return $limits;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function section(string $id, array $items): array
    {
        return [
            'id' => $id,
            'label' => __("settings.system.section.{$id}"),
            'items' => $items,
        ];
    }

    /**
     * @param  'text'|'number'|'bool'|'status'|'table'  $format
     * @param  'env'|'config'|'code'|'runtime'  $source  where it is changed
     * @return array<string, mixed>
     */
    private function item(string $id, mixed $value, string $format, string $source, ?string $unit = null): array
    {
        return [
            'id' => $id,
            'label' => __("settings.system.{$id}.label"),
            'help' => __("settings.system.{$id}.help"),
            'value' => $value,
            'format' => $format,
            'source' => $source,
            'sourceHint' => __("settings.system.source.{$source}"),
            'unit' => $unit,
        ];
    }
}
