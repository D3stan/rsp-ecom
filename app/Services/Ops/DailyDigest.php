<?php

namespace App\Services\Ops;

use App\Enums\OpsEventType;
use App\Models\CheckoutSnapshot;
use App\Models\OpsEvent;
use App\Models\Order;
use App\Models\OrderEmail;
use App\Settings\SystemInfo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The morning summary sent to Telegram: yesterday's numbers and whether the automatic checks are green. It is what
 * makes the silence of the other alerts trustworthy: a missing digest means the scheduler is down.
 */
class DailyDigest
{
    public function build(?Carbon $day = null): string
    {
        $day ??= now()->subDay();
        [$from, $to] = [$day->copy()->startOfDay(), $day->copy()->endOfDay()];

        $revenue = Order::query()
            ->paymentSucceeded()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('currency, COUNT(*) as orders, SUM(total_amount) as total')
            ->groupBy('currency')
            ->get();

        $orders = $revenue->isEmpty()
            ? '0 paid'
            : $revenue->map(fn ($row) => sprintf('%d paid, %s %s', $row->orders, number_format((float) $row->total, 2, '.', ''), $row->currency))->implode(' + ');

        return implode("\n", [
            "📊 {$day->toDateString()}",
            "Orders: {$orders}",
            sprintf(
                'Funnel: %d add to cart → %d checkout → %d payment started',
                $this->visitors(OpsEventType::AddToCart, $from, $to),
                $this->visitors(OpsEventType::CheckoutStarted, $from, $to),
                $this->visitors(OpsEventType::PaymentStarted, $from, $to),
            ),
            'Abandoned checkouts: '.CheckoutSnapshot::query()->whereNull('consumed_at')->whereBetween('created_at', [$from, $to])->count(),
            'Orders waiting for you: '.Order::query()->pending()->count(),
            sprintf('Failed emails (24h): %d · Failed jobs: %d', $this->failedEmails(), $this->failedJobs()),
            sprintf(
                'Checks: invariants %s · Stripe reconciliation %s',
                $this->lastResult(SystemInfo::INVARIANTS_CACHE_KEY),
                $this->lastResult(SystemInfo::RECONCILIATION_CACHE_KEY),
            ),
        ]);
    }

    private function visitors(OpsEventType $type, Carbon $from, Carbon $to): int
    {
        return OpsEvent::query()->where('type', $type->value)->whereBetween('created_at', [$from, $to])->count();
    }

    private function failedEmails(): int
    {
        return OrderEmail::query()->whereNotNull('failed_at')->whereNull('sent_at')->where('failed_at', '>', now()->subDay())->count();
    }

    private function failedJobs(): int
    {
        return Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
    }

    private function lastResult(string $cacheKey): string
    {
        try {
            return Cache::get($cacheKey)['status'] ?? 'not run yet';
        } catch (Throwable) {
            return 'unknown';
        }
    }
}
