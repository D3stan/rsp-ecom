<?php

namespace App\Shipping\Tracking\Jobs;

use App\Models\Order;
use App\Shipping\Tracking\Contracts\PassiveProvider;
use App\Shipping\Tracking\DTO\TrackingRequest;
use App\Shipping\Tracking\Enums\TrackingStatus;
use App\Shipping\Tracking\Exceptions\TrackingRejected;
use App\Shipping\Tracking\ShipmentTrackingService;
use App\Shipping\Tracking\TrackingManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Polls the active tracking driver for ONE order and applies the answer.
 *
 * Only the order id is serialized; the order is loaded when the job runs, so an order that was
 * delivered, cancelled or re-tracked in the meantime is simply skipped.
 *
 * Failures: a TrackingProviderUnavailable (or any unexpected error) is rethrown and retried with
 * backoff; when all attempts are used up failed() postpones the next poll. A TrackingRejected
 * means retrying cannot help: polling for that order is switched off (editing its tracking
 * number or carrier switches it back on).
 */
class SyncOrderTracking implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    // Must stay below the queue connection's retry_after (default 90s).
    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public function __construct(public int $orderId) {}

    /**
     * Seconds to wait between attempts (1m, 5m, 15m, 1h).
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            // One poll per order at a time (a manual `tracking:sync --order=` next to the scheduled one).
            (new WithoutOverlapping("tracking-sync:{$this->orderId}"))->releaseAfter(30)->expireAfter(75),
        ];
    }

    public function handle(TrackingManager $manager, ShipmentTrackingService $service): void
    {
        $order = Order::query()->find($this->orderId);

        if (! $order || ! $service->isTrackable($order) || TrackingStatus::fromValue($order->tracking_status)->isTerminal()) {
            return;
        }

        $provider = $manager->driver();

        if ($provider instanceof PassiveProvider) {
            return; // the driver was switched off since this job was queued
        }

        $carrier = is_string($order->tracking_carrier) && $order->tracking_carrier !== '' ? $order->tracking_carrier : null;

        if ($carrier !== null && ! $provider->supports($carrier)) {
            Log::info('Tracking driver does not support this carrier, polling disabled for the order', [
                'order_id' => $order->id,
                'provider' => $provider->name(),
                'carrier' => $carrier,
            ]);
            $service->disablePolling($order);

            return;
        }

        try {
            $result = $provider->track(new TrackingRequest(
                TrackingRequest::normalizeNumber($order->tracking_number),
                $carrier,
                $order->order_number,
            ));
        } catch (TrackingRejected $e) {
            Log::warning('Carrier rejected the tracking request, polling disabled for the order', [
                'order_id' => $order->id,
                'provider' => $provider->name(),
                'http_status' => $e->httpStatus,
                'error' => $e->getMessage(),
            ]);
            $service->disablePolling($order);

            return;
        }

        $service->apply($order, $provider->name(), $result);
    }

    /**
     * All attempts used up: postpone the next poll instead of retrying in a tight loop.
     */
    public function failed(?Throwable $e): void
    {
        Log::error('Tracking sync failed', [
            'order_id' => $this->orderId,
            'error_type' => $e ? get_class($e) : null,
            'error' => $e?->getMessage(),
        ]);

        try {
            $order = Order::query()->find($this->orderId);

            if ($order) {
                app(ShipmentTrackingService::class)->rescheduleAfterFailure($order);
            }
        } catch (Throwable $inner) {
            Log::error('Tracking sync could not be rescheduled', ['order_id' => $this->orderId, 'error' => $inner->getMessage()]);
        }
    }
}
