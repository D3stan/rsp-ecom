<?php

namespace App\Console\Commands;

use App\Services\Ops\InvariantChecker;
use App\Services\Ops\TelegramNotifier;
use App\Settings\SystemInfo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckInvariants extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ops:check-invariants';

    /**
     * @var string
     */
    protected $description = 'Alert (log + Telegram) when a silent failure shows up in the numbers: a paid order that never arrived, a stalled webhook, a collapsed funnel step';

    public function handle(InvariantChecker $checker, TelegramNotifier $telegram): int
    {
        $problems = $checker->problems();

        $this->rememberResult($problems);

        if ($problems === []) {
            $this->info('All invariants hold.');

            return self::SUCCESS;
        }

        foreach ($problems as $key => $message) {
            $this->error($message);

            Log::error('Invariant violated: '.$message, ['invariant' => $key]);

            $telegram->sendOnce('invariant:'.$key, '🚨 '.$message, (int) config('ops.invariants.alert_window_minutes'));
        }

        return self::FAILURE;
    }

    /**
     * Last result, shown read-only in Admin -> Settings -> System (App\Settings\SystemInfo).
     *
     * @param  array<string, string>  $problems
     */
    private function rememberResult(array $problems): void
    {
        try {
            Cache::put(SystemInfo::INVARIANTS_CACHE_KEY, [
                'status' => $problems === [] ? 'ok' : 'problem',
                'checked_at' => now()->toIso8601String(),
            ], now()->addDay());
        } catch (Throwable) {
            // informational only
        }
    }
}
