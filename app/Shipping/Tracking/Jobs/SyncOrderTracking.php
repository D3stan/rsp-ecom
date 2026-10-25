<?php

namespace App\Shipping\Tracking\Jobs;

use App\Models\Order;
use App\Models\Shipment;
use App\Shipping\Tracking\Contracts\PassiveProvider;
use App\Shipping\Tracking\DTO\TrackingRequest;
use App\Shipping\Tracking\Enums\TrackingStatus;
use App\Shipping\Tracking\Exceptions\TrackingRejected;
use App\Shipping\Tracking\Exceptions\UnknownTrackingDriver;
use App\Shipping\Tracking\ShipmentTrackingService;
use App\Shipping\Tracking\TrackingManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncOrderTracking implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

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
            (new WithoutOverlapping("tracking-sync:{$this->orderId}"))->releaseAfter(30)->expireAfter(75),
        ];
    }

    public function handle(TrackingManager $manager, ShipmentTrackingService $service): void
    {
        $order = Order::query()->find($this->orderId);

        if (! $order || ! $service->isTrackable($order) || TrackingStatus::fromValue($order->tracking_status)->isTerminal()) {
            return;
        }

        $driverName = is_string($order->tracking_driver) && trim($order->tracking_driver) !== ''
            ? trim($order->tracking_driver)
            : $manager->getDefaultDriver();

        try {
            $provider = $manager->driver($driverName);
        } catch (UnknownTrackingDriver $e) {
            Log::warning('Order tracking driver cannot be resolved, skipping poll', [
                'order_id' => $order->id,
                'tracking_driver' => $driverName,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($provider instanceof PassiveProvider) {
            return;
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

        $shipment = $order->shipments()->whereIn('status', Shipment::ACTIVE_STATUSES)->reorder()->latest('id')->first();
        $providerCode = $shipment?->external_id ?: $order->tracking_number;

        try {
            $result = $provider->track(new TrackingRequest(
                TrackingRequest::normalizeNumber($order->tracking_number),
                $carrier,
                $order->order_number,
                $providerCode,
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
