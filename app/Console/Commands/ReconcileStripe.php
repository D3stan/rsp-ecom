<?php

namespace App\Console\Commands;

use App\Services\Ops\StripeReconciler;
use App\Services\Ops\TelegramNotifier;
use App\Settings\SystemInfo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReconcileStripe extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ops:reconcile-stripe {--hours=48 : How far back to compare Stripe checkout sessions with orders}';

    /**
     * @var string
     */
    protected $description = 'Compare the last days of paid Stripe checkout sessions with the orders, and alert on any difference';

    public function handle(StripeReconciler $reconciler, TelegramNotifier $telegram): int
    {
        $problems = $reconciler->problems((int) $this->option('hours'));

        $this->rememberResult($problems === []);

        if ($problems === []) {
            $this->info('Stripe and the orders agree.');

            return self::SUCCESS;
        }

        foreach ($problems as $key => $message) {
            $this->error($message);

            Log::error('Stripe reconciliation: '.$message, ['reconciliation' => $key]);

            // The run is daily and each run repeats a standing problem: one reminder per day is enough
            $telegram->sendOnce('reconcile:'.$key, '💳 '.$message, 60 * 24);
        }

        return self::FAILURE;
    }

    /**
     * Last result, shown read-only in Admin -> Settings -> System (App\Settings\SystemInfo).
     */
    private function rememberResult(bool $healthy): void
    {
        try {
            Cache::put(SystemInfo::RECONCILIATION_CACHE_KEY, [
                'status' => $healthy ? 'ok' : 'problem',
                'checked_at' => now()->toIso8601String(),
            ], now()->addDays(2));
        } catch (Throwable) {
            // informational only
        }
    }
}
