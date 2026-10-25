<?php

namespace App\Shipping\Tracking;

use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentTrackingEvent;
use App\Services\EmailService;
use App\Shipping\Tracking\DTO\TrackingEvent;
use App\Shipping\Tracking\DTO\TrackingRequest;
use App\Shipping\Tracking\DTO\TrackingResult;
use App\Shipping\Tracking\Enums\TrackingStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShipmentTrackingService
{
    public const POLLING_DISABLED_YEARS = 5;

    private const FUTURE_TOLERANCE_HOURS = 24;

    public function apply(Order $order, string $provider, TrackingResult $result): void
    {
        DB::transaction(function () use ($order, $provider, $result) {
            $locked = Order::query()->lockForUpdate()->find($order->id);

            if (! $locked || ! $this->isTrackable($locked)) {
                Log::info('Tracking update ignored: order is not in shipped status', [
                    'order_id' => $order->id,
                    'provider' => $provider,
                ]);

                return;
            }

            $number = trim((string) $locked->tracking_number);

            if ($number === '') {
                return;
            }

            $number = $this->promoteCarrierTrackingNumber($locked, $provider, $result, $number);

            $stored = $this->storeEvents($locked, $provider, $number, $result);

            $this->advance($locked, $provider, $result->status, $stored);
        });

        $fresh = Order::query()->find($order->id);

        if ($fresh) {
            $order->setRawAttributes($fresh->getAttributes(), true);
        }
    }

    /**
     * Shipped orders carrying this tracking number (the same parcel can hold several orders).
     * Matching ignores case and spaces. When the update names a carrier and the order has a carrier
     * chosen, they must agree: carriers recycle numbers, and this avoids crossing two shipments.
     * When $provider is passed (webhooks), orders must match $provider (using explicit tracking_driver
     * or falling back to the default tracking driver).
     *
     * @return Collection<int, Order>
     */
    public function ordersForTrackingNumber(string $trackingNumber, ?string $carrierCode = null, ?string $provider = null): Collection
    {
        $normalized = mb_strtolower(TrackingRequest::normalizeNumber($trackingNumber));

        if ($normalized === '') {
            return new Collection;
        }

        $providerName = $provider !== null && trim($provider) !== '' ? trim($provider) : null;

        return Order::query()
            ->where('status', 'shipped')
            ->whereRaw("LOWER(REPLACE(tracking_number, ' ', '')) = ?", [$normalized])
            ->orderByDesc('id')
            ->limit(25)
            ->get()
            ->filter(function (Order $order) use ($carrierCode, $providerName) {
                if ($carrierCode !== null
                    && is_string($order->tracking_carrier)
                    && $order->tracking_carrier !== ''
                    && strcasecmp($order->tracking_carrier, $carrierCode) !== 0) {
                    return false;
                }

                if ($providerName !== null) {
                    $orderDriver = is_string($order->tracking_driver) && trim($order->tracking_driver) !== ''
                        ? trim($order->tracking_driver)
                        : null;

                    $effectiveDriver = $orderDriver ?? app(TrackingManager::class)->getDefaultDriver();

                    if (strcasecmp($effectiveDriver, $providerName) !== 0) {
                        return false;
                    }
                }

                return true;
            })
            ->values();
    }

    public function nextSyncAt(Order $order): CarbonInterface
    {
        $poll = (array) config('shipping.tracking.poll', []);
        $started = $order->tracking_started_at ?? now();
        $slow = $started->copy()->addDays(max(0, (int) ($poll['slow_after_days'] ?? 7)))->isPast();
        $minutes = (int) ($slow ? ($poll['slow_interval_minutes'] ?? 720) : ($poll['interval_minutes'] ?? 120));

        return now()->addMinutes(max(1, $minutes));
    }

    public function withinPollingWindow(Order $order): bool
    {
        $max = (int) config('shipping.tracking.poll.max_age_days', 45);

        return $order->tracking_started_at === null || $order->tracking_started_at->gt(now()->subDays($max));
    }

    public function rescheduleAfterFailure(Order $order): void
    {
        $this->writeSchedule($order->id, ['tracking_next_sync_at' => $this->nextSyncAt($order)]);
    }

    public function disablePolling(Order $order): void
    {
        $this->writeSchedule($order->id, ['tracking_next_sync_at' => now()->addYears(self::POLLING_DISABLED_YEARS)]);
    }

    public function isTrackable(Order $order): bool
    {
        return $order->status === 'shipped' && trim((string) $order->tracking_number) !== '';
    }

    /**
     * @return int number of events newly recorded (0 when everything was already known)
     */
    private function storeEvents(Order $order, string $provider, string $number, TrackingResult $result): int
    {
        $cutoff = CarbonImmutable::now()->addHours(self::FUTURE_TOLERANCE_HOURS);
        $timezone = (string) config('app.timezone', 'UTC');
        $now = now();
        $rows = [];

        foreach ($result->events as $event) {
            if ($event->occurredAt->greaterThan($cutoff)) {
                Log::warning('Tracking event dated in the future ignored', ['order_id' => $order->id, 'provider' => $provider]);

                continue;
            }

            $hash = $event->hash($provider, $number);

            $rows[$hash] = [
                'order_id' => $order->id,
                'provider' => $provider,
                'tracking_number' => Str::limit($number, 100, ''),
                'status' => $event->status->value,
                'description' => Str::limit($event->description, 1000, '...'),
                'location' => $event->location === null ? null : Str::limit($event->location, 255, ''),
                'occurred_at' => $event->occurredAt->setTimezone($timezone)->format('Y-m-d H:i:s'),
                'external_id' => $event->externalId === null ? null : Str::limit($event->externalId, 255, ''),
                'event_hash' => $hash,
                'raw' => $this->encodeRaw($event),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        return ShipmentTrackingEvent::query()->insertOrIgnore(array_values($rows));
    }

    private function advance(Order $order, string $provider, TrackingStatus $new, int $newEvents): void
    {
        $current = $order->tracking_status === null ? null : TrackingStatus::fromValue($order->tracking_status);
        $moves = $new->canReplace($current);

        if ($moves) {
            $order->tracking_status = $new->value;
        }

        $order->tracking_last_synced_at = now();
        $order->tracking_started_at ??= now();
        $order->tracking_next_sync_at = $this->resultingStatus($order)->isTerminal() ? null : $this->nextSyncAt($order);

        if ($moves && $new !== $current && ($new === TrackingStatus::Exception || $new === TrackingStatus::Returned)) {
            Log::warning('Shipment reported a problem by the carrier', [
                'order_id' => $order->id,
                'provider' => $provider,
                'status' => $new->value,
            ]);
        }

        $delivered = false;

        if ($moves && $new === TrackingStatus::Delivered && $this->autoDeliver() && $order->status === 'shipped') {
            try {
                $order->transitionTo('delivered', null, null, ['source' => 'tracking', 'provider' => $provider]);
                $delivered = true;
            } catch (InvalidOrderTransition $e) {
                Log::warning('Carrier delivery not applied: refused by the order lifecycle', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }

        if (! $delivered) {
            $order->save();
        }

        if (in_array($this->resultingStatus($order), [TrackingStatus::InTransit, TrackingStatus::OutForDelivery, TrackingStatus::Delivered], true)) {
            $this->sendDeferredShippedEmail($order);
        }

        Log::info('Tracking update applied', [
            'order_id' => $order->id,
            'provider' => $provider,
            'status' => $order->tracking_status,
            'new_events' => $newEvents,
        ]);
    }

    private function sendDeferredShippedEmail(Order $order): void
    {
        $shipment = $order->shipments()->where('notify_on_pickup', true)->reorder()->latest('id')->first();

        if ($shipment === null) {
            return;
        }

        if ($this->resultingStatus($order) === TrackingStatus::Delivered || app(EmailService::class)->sendOrderShipped($order, $order->tracking_number)) {
            $shipment->update(['notify_on_pickup' => false]);

            return;
        }

        Log::warning('Deferred shipped email could not be queued; it will be retried on the next sync', ['order_id' => $order->id]);
    }

    private function autoDeliver(): bool
    {
        return (bool) config('shipping.tracking.auto_deliver', true);
    }

    private function resultingStatus(Order $order): TrackingStatus
    {
        return TrackingStatus::fromValue($order->tracking_status);
    }

    private function encodeRaw(TrackingEvent $event): ?string
    {
        if ($event->raw === null) {
            return null;
        }

        $json = json_encode($event->raw, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        if ($json === false) {
            return null;
        }

        if (strlen($json) > (int) config('shipping.tracking.max_raw_bytes', 8192)) {
            return json_encode(['_truncated' => true, '_bytes' => strlen($json)]);
        }

        return $json;
    }

    private function promoteCarrierTrackingNumber(Order $locked, string $provider, TrackingResult $result, string $currentNumber): string
    {
        $newNumber = $result->carrierTrackingNumber !== null ? trim($result->carrierTrackingNumber) : '';

        if ($newNumber === '' || strcasecmp($newNumber, $currentNumber) === 0) {
            return $currentNumber;
        }

        $shipment = $locked->shipments()->whereIn('status', Shipment::ACTIVE_STATUSES)->reorder()->latest('id')->first();
        if ($shipment) {
            $shipmentUpdates = ['tracking_number' => $newNumber];
            if (empty($shipment->external_id)) {
                $shipmentUpdates['external_id'] = $currentNumber;
            }
            Shipment::query()->whereKey($shipment->id)->toBase()->update($shipmentUpdates);
        }

        Order::query()->whereKey($locked->id)->toBase()->update(['tracking_number' => $newNumber]);

        $locked->tracking_number = $newNumber;
        $locked->syncOriginalAttribute('tracking_number');

        Log::info('Promoted carrier tracking number', [
            'order_id' => $locked->id,
            'provider' => $provider,
            'old_number' => $currentNumber,
            'new_number' => $newNumber,
        ]);

        return $newNumber;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function writeSchedule(int $orderId, array $values): void
    {
        Order::query()->whereKey($orderId)->toBase()->update($values);
    }
}
