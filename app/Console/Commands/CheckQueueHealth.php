<?php

namespace App\Console\Commands;

use App\Services\Ops\TelegramNotifier;
use App\Settings\SystemInfo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CheckQueueHealth extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ops:check-queue
                            {--max-age= : Minutes a runnable job may wait before it counts as stuck (default: mail.queue_alerts.max_job_age_minutes)}
                            {--failed-threshold= : Alert when failed_jobs holds more rows than this (default: mail.queue_alerts.failed_jobs_threshold)}';

    /**
     * @var string
     */
    protected $description = 'Log a critical alert when the queue is not being processed or failed jobs pile up';

    public function handle(TelegramNotifier $telegram): int
    {
        $maxAge = (int) ($this->option('max-age') ?? config('mail.queue_alerts.max_job_age_minutes', 10));
        $failedThreshold = (int) ($this->option('failed-threshold') ?? config('mail.queue_alerts.failed_jobs_threshold', 10));
        $connection = (string) config('queue.default');

        if (in_array($connection, ['sync', 'null'], true)) {
            $this->info("Queue connection is [{$connection}]: jobs run inline, nothing to check.");

            return self::SUCCESS;
        }

        $problems = [];

        try {
            if ($connection === 'database') {
                $problems = array_merge($problems, $this->checkJobsTable($maxAge));
            }

            $problems = array_merge($problems, $this->checkFailedJobs($failedThreshold));
        } catch (Throwable $e) {
            $problems[] = 'Queue health check could not query the database: '.$e->getMessage();
        }

        $this->rememberResult($problems === []);

        if ($problems === []) {
            $this->info('Queue looks healthy.');

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->error($problem);
        }

        Log::critical('Queue health check failed: '.implode(' | ', $problems));

        // The check runs every five minutes: remind hourly while the problem lasts, not every run
        $telegram->sendOnce('queue-health', '⚠️ Queue health check failed: '.implode(' | ', $problems), 60);

        return self::FAILURE;
    }

    /**
     * Last result, shown read-only in Admin -> Settings -> System (App\Settings\SystemInfo).
     */
    private function rememberResult(bool $healthy): void
    {
        try {
            Cache::put(SystemInfo::QUEUE_HEALTH_CACHE_KEY, [
                'status' => $healthy ? 'ok' : 'problem',
                'checked_at' => now()->toIso8601String(),
            ], now()->addDay());
        } catch (Throwable) {
            // informational only
        }
    }

    /**
     * @return array<int, string>
     */
    private function checkJobsTable(int $maxAge): array
    {
        $table = config('queue.connections.database.table', 'jobs');

        if (! Schema::hasTable($table)) {
            return ["QUEUE_CONNECTION=database but the [{$table}] table does not exist (run migrations): no queued mail can be processed."];
        }

        // available_at (not created_at): a job waiting out a retry backoff is not stuck.
        $oldest = DB::table($table)->min('available_at');

        if ($oldest === null) {
            return [];
        }

        $waitingMinutes = (int) floor((now()->timestamp - (int) $oldest) / 60);

        if ($waitingMinutes >= $maxAge) {
            return ["A queued job has been runnable for {$waitingMinutes} minutes (limit {$maxAge}): is the queue worker running?"];
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function checkFailedJobs(int $threshold): array
    {
        $table = config('queue.failed.table', 'failed_jobs');

        if (! Schema::hasTable($table)) {
            return [];
        }

        $failed = DB::table($table)->count();

        if ($failed > $threshold) {
            return ["{$failed} failed jobs (limit {$threshold}): inspect with `php artisan queue:failed`."];
        }

        return [];
    }
}
