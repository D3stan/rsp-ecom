<?php

namespace App\Shipping\Tracking;

use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentTrackingEvent;
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

/**
 * Applies carrier answers (from polling or webhooks) to orders. Provider independent.
 *
 * Rules, all enforced here so adapters stay dumb:
 *  - idempotent: events are deduplicated on (order, provider, event hash); applying the same answer
 *    twice, or poll + webhook for the same scan, changes nothing the second time;
 *  - only `shipped` orders are touched: cancelled/delivered (e.g. set by the admin) are never modified;
 *  - the tracking status never moves backwards (see TrackingStatus::canReplace);
 *  - Delivered moves a shipped order to `delivered` through a normal model save, so the
 *    OrderObserver sends the delivered email (once, via order_emails) exactly as for an admin action.
 *    This depends only on TrackingStatus::Delivered and shipping.tracking.auto_deliver (default on):
 *    with the flag off the events and tracking_status are still stored, but the order stays `shipped`
 *    and no email is sent (the admin marks it delivered);
 *  - Exception / Returned are recorded and logged, but never change the order or send mail.
 */
class ShipmentTrackingService
{
    /** Parked "next sync" of orders that must not be polled any more (cleared when the tracking data is edited). */
    public const POLLING_DISABLED_YEARS = 5;

    /** Events dated further than this in the future are carrier clock/timezone errors and are dropped. */
    private const FUTURE_TOLERANCE_HOURS = 24;

    public function apply(Order $order, string $provider, TrackingResult $result): void
    {
        DB::transaction(function () use ($order, $provider, $result) {
            // Fresh, locked row: a poll and a webhook for the same parcel are serialised here.
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

        // Callers keep using their instance: hand them the state that was just written.
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

    /**
     * When the next poll of this order is due: the normal interval, or the slow one once the shipment is old.
     */
    public function nextSyncAt(Order $order): CarbonInterface
    {
        $poll = (array) config('shipping.tracking.poll', []);
        $started = $order->tracking_started_at ?? now();
        $slow = $started->copy()->addDays(max(0, (int) ($poll['slow_after_days'] ?? 7)))->isPast();
        $minutes = (int) ($slow ? ($poll['slow_interval_minutes'] ?? 720) : ($poll['interval_minutes'] ?? 120));

        return now()->addMinutes(max(1, $minutes));
    }

    /**
     * Is the order still inside the polling window (max_age_days after its first poll)?
     */
    public function withinPollingWindow(Order $order): bool
    {
        $max = (int) config('shipping.tracking.poll.max_age_days', 45);

        return $order->tracking_started_at === null || $order->tracking_started_at->gt(now()->subDays($max));
    }

    /**
     * Postpone the next poll after a failure (without touching the tracking status).
     */
    public function rescheduleAfterFailure(Order $order): void
    {
        $this->writeSchedule($order->id, ['tracking_next_sync_at' => $this->nextSyncAt($order)]);
    }

    /**
     * Stop polling this order. Editing its tracking number or carrier starts a new cycle.
     */
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

        // insertOrIgnore: portable, never aborts the surrounding transaction on a duplicate.
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
            // Same path as the admin action (saves the tracking fields too), so the OrderObserver sends the delivered email.
            // A refused move must not fail the carrier webhook/poll: the tracking data is still saved below.
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

        Log::info('Tracking update applied', [
            'order_id' => $order->id,
            'provider' => $provider,
            'status' => $order->tracking_status,
            'new_events' => $newEvents,
        ]);
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

    /**
     * When a carrier aggregator reports the real courier waybill number
     * (distinct from its platform shipment code), promote it onto order.tracking_number and
     * shipment.tracking_number, while preserving the platform code in shipment.external_id
     * so future API polls continue to succeed.
     *
     * Updating via query builder avoids firing Order::updating, which would otherwise wipe out
     * tracking_status, tracking_last_synced_at, and the polling schedule.
     */
    private function promoteCarrierTrackingNumber(Order $locked, string $provider, TrackingResult $result, string $currentNumber): string
    {
        $newNumber = $result->carrierTrackingNumber !== null ? trim($result->carrierTrackingNumber) : '';

        if ($newNumber === '' || strcasecmp($newNumber, $currentNumber) === 0) {
            return $currentNumber;
        }

        // 1. Update the latest active shipment: save current platform code to external_id if empty,
        // and update shipment.tracking_number to match order.tracking_number so trackingUrl fallback matches.
        $shipment = $locked->shipments()->where('status', Shipment::STATUS_CREATED)->reorder()->latest('id')->first();
        if ($shipment) {
            $shipmentUpdates = ['tracking_number' => $newNumber];
            if (empty($shipment->external_id)) {
                $shipmentUpdates['external_id'] = $currentNumber;
            }
            Shipment::query()->whereKey($shipment->id)->toBase()->update($shipmentUpdates);
        }

        // 2. Promote order.tracking_number using query builder to avoid firing Order::updating
        Order::query()->whereKey($locked->id)->toBase()->update(['tracking_number' => $newNumber]);

        // Keep in-memory model in sync without marking tracking_number dirty on subsequent advance() save.
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
        // Query builder on purpose: no model events, no updated_at bump for scheduling bookkeeping.
        Order::query()->whereKey($orderId)->toBase()->update($values);
    }
}
