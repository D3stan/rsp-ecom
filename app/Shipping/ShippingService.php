<?php

namespace App\Shipping;

use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Models\OrderEmail;
use App\Models\Shipment;
use App\Models\User;
use App\Services\EmailService;
use App\Shipping\Labels\Contracts\CancelsShipments;
use App\Shipping\Labels\Contracts\ConfirmsPayment;
use App\Shipping\Labels\Contracts\LabelProvider;
use App\Shipping\Labels\DTO\ShipmentResult;
use App\Shipping\Labels\Exceptions\LabelException;
use App\Shipping\Labels\Exceptions\LabelProviderUnavailable;
use App\Shipping\Labels\Exceptions\LabelRejected;
use App\Shipping\Labels\LabelManager;
use App\Shipping\Labels\ShipmentBuilder;
use App\Shipping\Tracking\Contracts\PassiveProvider;
use App\Shipping\Tracking\DTO\TrackingRequest;
use App\Shipping\Tracking\ShipmentTrackingService;
use App\Shipping\Tracking\TrackingManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ShippingService
{
    public function __construct(
        private readonly EmailService $emails,
        private readonly LabelManager $labels,
        private readonly ShipmentBuilder $builder,
        private readonly TrackingManager $tracking,
    ) {}

    private const LABEL_DISK = 'local';

    /**
     * Admin-facing names of the registered provider modules, keyed by driver name.
     *
     * @return array<string, string>
     */
    public function providerNames(): array
    {
        return collect((array) config('shipping.manifests', []))
            ->mapWithKeys(fn (string $manifest) => [$manifest::name() => $manifest::displayName()])
            ->all();
    }

    public function labelsEnabled(): bool
    {
        return (bool) settings('shipping.labels_enabled', false);
    }

    /**
     * @throws InvalidOrderTransition the order may not ship (not processing, not paid, or already shipped)
     * @throws LabelException the label carrier refused (LabelRejected) or is unavailable (LabelProviderUnavailable)
     */
    public function ship(Order $order, ShipInput $input, User $actor): ShipOutcome
    {
        if ($this->labelsEnabled() && $input->createLabel) {
            return $this->shipWithLabel($order, $input, $actor);
        }

        return $this->shipManually($order, $input, $actor);
    }

    private function shipManually(Order $order, ShipInput $input, User $actor): ShipOutcome
    {
        $trackingNumber = $input->trackingNumber !== null && trim($input->trackingNumber) !== ''
            ? trim($input->trackingNumber)
            : null;
        $externalId = null;
        $initialResult = null;

        $driverName = $input->trackingDriver;
        if ($driverName === null || trim($driverName) === '') {
            $defaultDriver = $this->tracking->getDefaultDriver();
            if ($this->tracking->canPoll($defaultDriver)) {
                $driverName = $defaultDriver;
            } elseif (count($pollable = $this->tracking->pollableDriverNames()) === 1) {
                $driverName = $pollable[0];
            }
        }

        if ($trackingNumber !== null && $driverName !== null && trim($driverName) !== '') {
            try {
                $provider = $this->tracking->driver($driverName);
                if (! ($provider instanceof PassiveProvider)) {
                    $trackingReq = new TrackingRequest(
                        trackingNumber: $trackingNumber,
                        carrierCode: $input->carrierCode,
                        orderReference: $order->order_number,
                    );
                    $initialResult = $provider->track($trackingReq);
                    if ($initialResult->carrierTrackingNumber !== null && trim($initialResult->carrierTrackingNumber) !== '') {
                        $carrierTrk = trim($initialResult->carrierTrackingNumber);
                        if (strcasecmp($carrierTrk, $trackingNumber) !== 0) {
                            $externalId = $trackingNumber;
                            $trackingNumber = $carrierTrk;
                        }
                    }
                }
            } catch (Throwable $e) {
                Log::info('Initial carrier tracking number lookup skipped during manual shipment', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $shipment = DB::transaction(function () use ($order, $input, $actor, $trackingNumber, $externalId) {
            $this->lockAndGuard($order);

            $shipment = $order->shipments()->create([
                'carrier_code' => $input->carrierCode,
                'driver' => Shipment::DRIVER_MANUAL,
                'external_id' => $externalId,
                'tracking_number' => $trackingNumber,
                'status' => Shipment::STATUS_CREATED,
                'created_by' => $actor->getKey(),
            ]);

            $attributes = [
                'tracking_carrier' => $input->carrierCode,
                'tracking_number' => $trackingNumber,
                'tracking_driver' => $input->trackingDriver,
            ];

            $order->fill($attributes)
                ->transitionTo('shipped', $actor, null, ['source' => 'ship_form', 'shipment_id' => $shipment->id]);

            return $shipment;
        });

        if ($initialResult !== null && $driverName !== null) {
            try {
                app(ShipmentTrackingService::class)->apply($order, $driverName, $initialResult);
            } catch (Throwable $e) {
                Log::warning('Failed to apply initial tracking result during manual shipment', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Order shipped', [
            'order_id' => $order->id,
            'shipment_id' => $shipment->id,
            'driver' => $shipment->driver,
            'tracking_driver' => $order->tracking_driver,
            'external_id' => $externalId,
            'tracking_number' => $trackingNumber,
        ]);

        $emailFailed = ($trackingNumber !== null || $input->notifyCustomer)
            && ! $this->emails->sendOrderShipped($order, $trackingNumber);

        return new ShipOutcome($shipment, $emailFailed);
    }

    private function shipWithLabel(Order $order, ShipInput $input, User $actor): ShipOutcome
    {
        $lock = Cache::lock("ship-order:{$order->id}", 120);

        if (! $lock->get()) {
            throw new LabelProviderUnavailable('A label purchase for this order is already in progress. Wait a moment, then check the order.');
        }

        try {
            $order->refresh();

            if (! $order->canShip()) {
                throw new InvalidOrderTransition("Order {$order->order_number} cannot ship now: it must be processing and paid.");
            }

            return $this->purchaseLabelAndShip($order, $input, $actor);
        } finally {
            $lock->release();
        }
    }

    private function purchaseLabelAndShip(Order $order, ShipInput $input, User $actor): ShipOutcome
    {
        $driverName = $input->labelDriver ?? (string) settings('shipping.label_driver', '');
        $provider = $this->labels->driver($driverName);

        if (! $provider->supports($input->carrierCode)) {
            throw new LabelRejected('The selected carrier is not available for label creation.');
        }

        $request = $this->builder->build($order, $input->carrierCode, $input->parcels, $input->insuranceAmount, $input->codAmount, $input->quoteToken, $input->pickupDate);

        $result = $provider->createShipment($request);

        $path = null;
        if (! $result->awaitingPayment) {
            [$result, $path] = $this->fetchAndStoreLabel($provider, $result, $order);
        }

        $shipmentDriver = $result->raw['driver'] ?? $provider->name();
        $trackingDriver = $this->resolveTrackingDriverForLabel($input, $shipmentDriver);

        try {
            $shipment = DB::transaction(function () use ($order, $input, $actor, $result, $path, $shipmentDriver, $trackingDriver) {
                $this->lockAndGuard($order);

                $shipment = $order->shipments()->create([
                    'carrier_code' => $input->carrierCode,
                    'driver' => $shipmentDriver,
                    'external_id' => $result->externalId,
                    'tracking_number' => $result->trackingNumber,
                    'label_path' => $path,
                    'pickup_date' => $input->pickupDate,
                    'notify_on_pickup' => true,
                    'status' => $result->awaitingPayment ? Shipment::STATUS_AWAITING_PAYMENT : Shipment::STATUS_CREATED,
                    'request_payload' => $result->raw ?: null,
                    'created_by' => $actor->getKey(),
                ]);

                $attributes = [
                    'tracking_carrier' => $input->carrierCode,
                    'tracking_number' => $result->trackingNumber,
                ];

                if ($trackingDriver !== null) {
                    $attributes['tracking_driver'] = $trackingDriver;
                }

                $order->fill($attributes)
                    ->transitionTo('shipped', $actor, null, ['source' => 'ship_form', 'shipment_id' => $shipment->id]);

                return $shipment;
            });
        } catch (Throwable $e) {
            $this->recordOrphanShipment($order, $input, $result, $path, $actor, $shipmentDriver);
            $reference = (string) Str::uuid();
            Log::critical('Label was created but the order could not be shipped', [
                'order_id' => $order->id, 'external_id' => $result->externalId, 'reference' => $reference, 'error' => $e->getMessage(),
            ]);

            if ($e instanceof InvalidOrderTransition) {
                throw $e;
            }

            throw new LabelProviderUnavailable("The label was created but the order could not be updated (reference {$reference}). The shipment was recorded; check the order.");
        }

        Log::info('Order shipped with label', [
            'order_id' => $order->id,
            'shipment_id' => $shipment->id,
            'driver' => $shipment->driver,
            'tracking_driver' => $order->tracking_driver,
        ]);

        return new ShipOutcome($shipment, false);
    }

    /**
     * Fetch the label PDF of a bought shipment and keep it on the private disk.
     *
     * @return array{0: ShipmentResult, 1: string} the result (carrying the carrier's real waybill number when the label
     *                                             step revealed it) and the stored path
     */
    private function fetchAndStoreLabel(LabelProvider $provider, ShipmentResult $result, Order $order): array
    {
        $document = $provider->label($result);

        if ($document->trackingNumber !== null && trim($document->trackingNumber) !== '') {
            $result = new ShipmentResult(
                externalId: $result->externalId,
                trackingNumber: trim($document->trackingNumber),
                carrierCode: $result->carrierCode,
                raw: array_merge($result->raw, ['trackingNumber' => trim($document->trackingNumber)]),
            );
        }

        $path = sprintf('labels/%d/%s.pdf', $order->id, Str::uuid());
        Storage::disk(self::LABEL_DISK)->put($path, $document->contents);

        return [$result, $path];
    }

    private function resolveTrackingDriverForLabel(ShipInput $input, string $shipmentDriver): ?string
    {
        if ($input->trackingDriver !== null && trim($input->trackingDriver) !== '') {
            return $input->trackingDriver;
        }

        if (in_array($shipmentDriver, $this->tracking->driverNames(), true) && $this->tracking->canPoll($shipmentDriver)) {
            return $shipmentDriver;
        }

        return null;
    }

    public function labelContents(Shipment $shipment): ?string
    {
        if (! $shipment->label_path || ! Storage::disk(self::LABEL_DISK)->exists($shipment->label_path)) {
            return null;
        }

        return Storage::disk(self::LABEL_DISK)->get($shipment->label_path);
    }

    /**
     * An awaiting_payment shipment: ask the carrier whether it has been paid and, if so, fetch the label and finish it.
     * Returns false while it is still unpaid (nothing changes).
     *
     * @throws LabelException
     */
    public function completePurchase(Shipment $shipment): bool
    {
        if ($shipment->status !== Shipment::STATUS_AWAITING_PAYMENT || ! $shipment->external_id) {
            throw new LabelRejected('This shipment is not waiting for payment.');
        }

        $provider = $this->labels->driver($shipment->driver);

        if (! $provider instanceof ConfirmsPayment) {
            throw new LabelRejected('This carrier does not need a separate payment.');
        }

        $paid = $provider->confirmPurchase($shipment->external_id);

        if ($paid === null) {
            return false;
        }

        $order = $shipment->order;
        [$paid, $path] = $this->fetchAndStoreLabel($provider, $paid, $order);

        $shipment->update([
            'status' => Shipment::STATUS_CREATED,
            'tracking_number' => $paid->trackingNumber,
            'label_path' => $path,
            'request_payload' => array_merge((array) $shipment->request_payload, $paid->raw),
        ]);

        Order::query()->whereKey($order->id)->toBase()->update([
            'tracking_number' => $paid->trackingNumber,
            'tracking_next_sync_at' => null,
            'tracking_started_at' => null,
        ]);

        return true;
    }

    /**
     * Cancel a shipment at the carrier (driver must implement CancelsShipments). Does not change the order status.
     *
     * @throws LabelException
     */
    public function cancelShipment(Shipment $shipment): void
    {
        if ($shipment->isManual()) {
            $shipment->update(['status' => Shipment::STATUS_CANCELLED]);

            return;
        }

        if (! $shipment->external_id) {
            throw new LabelRejected('This shipment cannot be cancelled at the carrier.');
        }

        $provider = $this->labels->driver($shipment->driver);

        if (! $provider instanceof CancelsShipments) {
            throw new LabelRejected('This carrier does not support cancelling a shipment.');
        }

        $provider->cancelShipment($shipment->external_id);

        $shipment->update(['status' => Shipment::STATUS_CANCELLED]);
    }

    public function trackingChanged(Order $order): bool
    {
        if ($order->status !== 'shipped') {
            return true;
        }

        if ($order->shipments()->where('notify_on_pickup', true)->exists()) {
            return true;
        }

        $number = trim((string) $order->tracking_number) !== '' ? trim((string) $order->tracking_number) : null;
        $carrier = $order->getAttribute('tracking_carrier');
        $driverName = $order->getAttribute('tracking_driver') ?? $this->tracking->getDefaultDriver();

        if ($number !== null && $driverName !== null && trim($driverName) !== '') {
            try {
                $provider = $this->tracking->driver($driverName);
                if (! ($provider instanceof PassiveProvider)) {
                    $trackingReq = new TrackingRequest(
                        trackingNumber: $number,
                        carrierCode: is_string($carrier) ? $carrier : null,
                        orderReference: $order->order_number,
                    );
                    $result = $provider->track($trackingReq);
                    if ($result->carrierTrackingNumber !== null && trim($result->carrierTrackingNumber) !== '') {
                        $carrierTrk = trim($result->carrierTrackingNumber);
                        if (strcasecmp($carrierTrk, $number) !== 0) {
                            $shipment = $order->shipments()->whereIn('status', Shipment::ACTIVE_STATUSES)->reorder()->latest('id')->first();
                            if ($shipment) {
                                $shipment->update([
                                    'external_id' => $number,
                                    'tracking_number' => $carrierTrk,
                                ] + (is_string($carrier) && $carrier !== '' ? ['carrier_code' => $carrier] : []));
                            }
                            Order::query()->whereKey($order->id)->toBase()->update(['tracking_number' => $carrierTrk]);
                            $order->tracking_number = $carrierTrk;
                            $order->syncOriginalAttribute('tracking_number');
                            $number = $carrierTrk;
                        }
                    }
                }
            } catch (Throwable) {
            }
        }

        $shipment = $order->shipments()->whereIn('status', Shipment::ACTIVE_STATUSES)->reorder()->latest('id')->first();

        if ($shipment?->isManual() && $shipment->tracking_number !== $number) {
            $shipment->update(['tracking_number' => $number]
                + (is_string($carrier) && $carrier !== '' ? ['carrier_code' => $carrier] : []));
        }

        if ($number === null || ! $this->emails->isEnabled(OrderEmail::TYPE_SHIPPED)) {
            return true;
        }

        return $this->emails->sendOrderShipped($order, $number);
    }

    /**
     * Lock the stored row and refuse shipping twice or an order that may no longer ship (a refund in the meantime).
     *
     * @throws InvalidOrderTransition
     */
    private function lockAndGuard(Order $order): void
    {
        Order::query()->whereKey($order->getKey())->lockForUpdate()->first();
        $order->refresh();

        if (! $order->canShip()) {
            throw new InvalidOrderTransition("Order {$order->order_number} cannot ship now: it must be processing and paid.");
        }
    }

    private function recordOrphanShipment(Order $order, ShipInput $input, ShipmentResult $result, ?string $path, User $actor, string $driver): void
    {
        try {
            $order->shipments()->create([
                'carrier_code' => $input->carrierCode,
                'driver' => $driver,
                'external_id' => $result->externalId,
                'tracking_number' => $result->trackingNumber,
                'label_path' => $path,
                'pickup_date' => $input->pickupDate,
                'status' => $result->awaitingPayment ? Shipment::STATUS_AWAITING_PAYMENT : Shipment::STATUS_CREATED,
                'request_payload' => $result->raw ?: null,
                'created_by' => $actor->getKey(),
            ]);
        } catch (Throwable $e) {
            Log::critical('Could not record a created label after the order update failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }
}
