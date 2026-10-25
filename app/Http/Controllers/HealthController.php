<?php

namespace App\Http\Controllers;

use App\Settings\SystemInfo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Deep health check for an external uptime monitor: unlike Laravel's `/up` (the app boots) it proves the app can
 * reach what a sale needs. It reports only ok/fail per check, never details.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $lastQueueCheck = $this->lastQueueCheck();

        $checks = [
            'database' => $this->databaseResponds(),
            'cache' => $this->cacheRoundTrips(),
            'storage' => is_writable(storage_path('framework')),
            // ops:check-queue records its verdict every five minutes: it is the queue's verdict and the scheduler's pulse
            'queue' => $lastQueueCheck === null || $lastQueueCheck['status'] === 'ok',
            'scheduler' => $lastQueueCheck === null || $this->isRecent($lastQueueCheck['checked_at'] ?? null),
            'stripe' => filled(config('cashier.secret')),
        ];

        $failed = array_keys(array_filter($checks, fn (bool $passed) => ! $passed));

        if ($failed !== []) {
            Log::warning('Health check failing', ['failed' => $failed]);
        }

        return response()->json([
            'status' => $failed === [] ? 'ok' : 'fail',
            'checks' => array_map(fn (bool $passed) => $passed ? 'ok' : 'fail', $checks),
        ], $failed === [] ? 200 : 503);
    }

    private function databaseResponds(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function cacheRoundTrips(): bool
    {
        try {
            Cache::put('ops:health', 'ok', 60);

            return Cache::get('ops:health') === 'ok';
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{status: string, checked_at?: string}|null
     */
    private function lastQueueCheck(): ?array
    {
        try {
            return Cache::get(SystemInfo::QUEUE_HEALTH_CACHE_KEY);
        } catch (Throwable) {
            return null;
        }
    }

    private function isRecent(?string $checkedAt): bool
    {
        return $checkedAt !== null
            && Carbon::parse($checkedAt)->greaterThan(now()->subMinutes((int) config('ops.health.scheduler_max_age_minutes')));
    }
}
