<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Shipment;
use App\Shipping\Tracking\Enums\TrackingStatus;
use App\Shipping\Tracking\Jobs\SyncOrderTracking;
use App\Shipping\Tracking\TrackingManager;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncShipmentTracking extends Command
{
    private const DISPATCH_LEASE_MINUTES = 90;

    /**
     * @var string
     */
    protected $signature = 'tracking:sync
                            {--order= : Sync this order id now, ignoring its schedule and polling window}
                            {--limit=100 : Maximum number of orders queued per run}';

    /**
     * @var string
     */
    protected $description = 'Queue a carrier tracking poll for every shipped order whose next sync is due';

    public function handle(TrackingManager $manager): int
    {
        if (! $manager->canPollAny()) {
            $this->info('The active tracking driver does not poll carriers (manual, null or unresolvable): nothing to do.');

            return self::SUCCESS;
        }

        $orders = $this->dueOrders(
            max(1, (int) $this->option('limit')),
            $this->option('order') !== null ? (int) $this->option('order') : null,
            $manager,
        );

        $queued = 0;
        $failed = 0;

        foreach ($orders as $order) {
            try {
                Order::query()->whereKey($order->id)->toBase()->update([
                    'tracking_next_sync_at' => now()->addMinutes(self::DISPATCH_LEASE_MINUTES),
                    'tracking_started_at' => $order->tracking_started_at ?? now(),
                ]);

                SyncOrderTracking::dispatch($order->id);
                $queued++;
            } catch (Throwable $e) {
                $failed++;
                Order::query()->whereKey($order->id)->toBase()->update([
                    'tracking_next_sync_at' => $order->tracking_next_sync_at,
                    'tracking_started_at' => $order->tracking_started_at,
                ]);
                Log::error('Tracking sync could not be queued', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info("Queued {$queued} tracking sync job(s).");

        if ($failed > 0) {
            $this->error("{$failed} order(s) could not be queued, see the log.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Order>
     */
    private function dueOrders(int $limit, ?int $onlyOrder, TrackingManager $manager)
    {
        $pollable = $manager->pollableDriverNames();
        $defaultPolls = $manager->canPoll();

        $query = Order::query()
            ->where('status', 'shipped')
            ->whereNotNull('tracking_number')
            ->where('tracking_number', '!=', '')
            ->whereDoesntHave('shipments', fn ($shipments) => $shipments->where('status', Shipment::STATUS_AWAITING_PAYMENT))
            ->where(function ($q) use ($pollable, $defaultPolls) {
                $q->whereIn('tracking_driver', $pollable);

                if ($defaultPolls) {
                    $q->orWhereNull('tracking_driver');
                }
            })
            ->where(fn ($q) => $q->whereNull('tracking_status')
                ->orWhereNotIn('tracking_status', [TrackingStatus::Delivered->value, TrackingStatus::Returned->value]))
            ->orderBy('id')
            ->limit($limit);

        if ($onlyOrder !== null) {
            return $query->whereKey($onlyOrder)->get();
        }

        $maxAgeDays = (int) config('shipping.tracking.poll.max_age_days', 45);

        return $query
            ->where(fn ($q) => $q->whereNull('tracking_started_at')->orWhere('tracking_started_at', '>', now()->subDays($maxAgeDays)))
            ->where(fn ($q) => $q->whereNull('tracking_next_sync_at')->orWhere('tracking_next_sync_at', '<=', now()))
            ->get();
    }
}
