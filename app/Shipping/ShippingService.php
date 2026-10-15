<?php

namespace App\Shipping;

use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Models\OrderEmail;
use App\Models\Shipment;
use App\Models\User;
use App\Services\EmailService;
use App\Shipping\Labels\DTO\ShipmentResult;
use App\Shipping\Labels\Exceptions\LabelException;
use App\Shipping\Labels\Exceptions\LabelProviderUnavailable;
use App\Shipping\Labels\LabelManager;
use App\Shipping\Labels\ShipmentBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The only way an order becomes "shipped" (controllers call nothing else). It records the shipment, stores the current
 * carrier and tracking number on the order (tracking module, emails), moves the order through the lifecycle and sends
 * the shipped email: one, with the tracking when there is a number. Nothing here is carrier specific.
 *
 * Label creation is optional (shipping.labels_enabled, OFF by default). When off, no label provider is ever resolved:
 * ship() takes the manual path (the admin typed the carrier and tracking number). When on and the admin asked for a
 * label, the active LabelProvider creates the shipment and the PDF; the tracking number comes from the carrier.
 */
class ShippingService
{
    public function __construct(
        private readonly EmailService $emails,
        private readonly LabelManager $labels,
        private readonly ShipmentBuilder $builder,
    ) {}

    /** Private disk that holds the label PDFs (never public). */
    private const LABEL_DISK = 'local';

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

    /**
     * Labels off: the admin typed the carrier and tracking number (or ships without one).
     */
    private function shipManually(Order $order, ShipInput $input, User $actor): ShipOutcome
    {
        $shipment = DB::transaction(function () use ($order, $input, $actor) {
            $this->lockAndGuard($order);

            $shipment = $order->shipments()->create([
                'carrier_code' => $input->carrierCode,
                'driver' => Shipment::DRIVER_MANUAL,
                'tracking_number' => $input->trackingNumber,
                'status' => Shipment::STATUS_CREATED,
                'created_by' => $actor->getKey(),
            ]);

            $order->fill([
                'tracking_carrier' => $input->carrierCode,
                'tracking_number' => $input->trackingNumber,
            ])->transitionTo('shipped', $actor, null, ['source' => 'ship_form', 'shipment_id' => $shipment->id]);

            return $shipment;
        });

        Log::info('Order shipped', ['order_id' => $order->id, 'shipment_id' => $shipment->id, 'driver' => $shipment->driver]);

        // One email: with the tracking when there is a number; without, only when the admin asked for it
        $emailFailed = ($input->trackingNumber !== null || $input->notifyCustomer)
            && ! $this->emails->sendOrderShipped($order, $input->trackingNumber);

        return new ShipOutcome($shipment, $emailFailed);
    }

    /**
     * Labels on: create the shipment and the label PDF at the carrier, then record it and ship. The network call
     * happens first and outside the transaction, so a refusal or an outage never leaves a half-shipped order.
     */
    private function shipWithLabel(Order $order, ShipInput $input, User $actor): ShipOutcome
    {
        $provider = $this->labels->driver((string) settings('shipping.label_driver', ''));

        if (! $provider->supports($input->carrierCode)) {
            throw new \App\Shipping\Labels\Exceptions\LabelRejected('The selected carrier is not available for label creation.');
        }

        $request = $this->builder->build($order, $input->carrierCode, $input->parcels, $input->insuranceAmount, $input->codAmount);

        // 1. Carrier creates the shipment and the label (may throw LabelRejected / LabelProviderUnavailable)
        $result = $provider->createShipment($request);
        $document = $provider->label($result);

        // 2. Store the PDF on the private disk before any DB change, so the label is never lost
        $path = sprintf('labels/%d/%s.pdf', $order->id, Str::uuid());
        Storage::disk(self::LABEL_DISK)->put($path, $document->contents);

        // 3. Record the shipment and move the order to shipped in one transaction
        try {
            $shipment = DB::transaction(function () use ($order, $input, $actor, $result, $path) {
                $this->lockAndGuard($order);

                $shipment = $order->shipments()->create([
                    'carrier_code' => $input->carrierCode,
                    'driver' => $result->raw['driver'] ?? $this->labels->getDefaultDriver(),
                    'external_id' => $result->externalId,
                    'tracking_number' => $result->trackingNumber,
                    'label_path' => $path,
                    'status' => Shipment::STATUS_CREATED,
                    'request_payload' => $result->raw ?: null,
                    'created_by' => $actor->getKey(),
                ]);

                $order->fill([
                    'tracking_carrier' => $input->carrierCode,
                    'tracking_number' => $result->trackingNumber,
                ])->transitionTo('shipped', $actor, null, ['source' => 'ship_form', 'shipment_id' => $shipment->id]);

                return $shipment;
            });
        } catch (Throwable $e) {
            // The label exists at the carrier and on disk: record it so it is not lost, then surface the failure.
            $this->recordOrphanShipment($order, $input, $result, $path, $actor);
            $reference = (string) Str::uuid();
            Log::critical('Label was created but the order could not be shipped', [
                'order_id' => $order->id, 'external_id' => $result->externalId, 'reference' => $reference, 'error' => $e->getMessage(),
            ]);

            if ($e instanceof InvalidOrderTransition) {
                throw $e;
            }

            throw new LabelProviderUnavailable("The label was created but the order could not be updated (reference {$reference}). The shipment was recorded; check the order.");
        }

        Log::info('Order shipped with label', ['order_id' => $order->id, 'shipment_id' => $shipment->id, 'driver' => $shipment->driver]);

        $emailFailed = ! $this->emails->sendOrderShipped($order, $result->trackingNumber);

        return new ShipOutcome($shipment, $emailFailed);
    }

    /**
     * Download the label PDF of a shipment (streamed from the private disk by the controller).
     */
    public function labelContents(Shipment $shipment): ?string
    {
        if (! $shipment->label_path || ! Storage::disk(self::LABEL_DISK)->exists($shipment->label_path)) {
            return null;
        }

        return Storage::disk(self::LABEL_DISK)->get($shipment->label_path);
    }

    /**
     * Cancel a shipment at the carrier (driver must implement CancelsShipments). Does not change the order status.
     *
     * @throws LabelException
     */
    public function cancelShipment(Shipment $shipment): void
    {
        $provider = $this->labels->driver($shipment->driver);

        if (! $provider instanceof \App\Shipping\Labels\Contracts\CancelsShipments) {
            throw new \App\Shipping\Labels\Exceptions\LabelRejected('This carrier does not support cancelling a shipment.');
        }

        if ($shipment->external_id) {
            $provider->cancelShipment($shipment->external_id);
        }

        $shipment->update(['status' => Shipment::STATUS_CANCELLED]);
    }

    /**
     * The admin changed the tracking number or carrier of an order (already saved). For a shipped order this updates
     * the current manual shipment and sends the shipped email with the new number: the first one when none went out yet,
     * else one tracking update (EmailService dedupes on number + carrier, so saving the same values again sends nothing).
     * Without a number no email is sent. Returns false when the email could not be queued.
     */
    public function trackingChanged(Order $order): bool
    {
        if ($order->status !== 'shipped') {
            return true;
        }

        $number = trim((string) $order->tracking_number) !== '' ? trim((string) $order->tracking_number) : null;
        $carrier = $order->getAttribute('tracking_carrier');

        $shipment = $order->shipments()->where('status', Shipment::STATUS_CREATED)->reorder()->latest('id')->first();

        if ($shipment?->isManual()) {
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

    private function recordOrphanShipment(Order $order, ShipInput $input, ShipmentResult $result, string $path, User $actor): void
    {
        try {
            $order->shipments()->create([
                'carrier_code' => $input->carrierCode,
                'driver' => $result->raw['driver'] ?? (string) settings('shipping.label_driver', ''),
                'external_id' => $result->externalId,
                'tracking_number' => $result->trackingNumber,
                'label_path' => $path,
                'status' => Shipment::STATUS_CREATED,
                'request_payload' => $result->raw ?: null,
                'created_by' => $actor->getKey(),
            ]);
        } catch (Throwable $e) {
            Log::critical('Could not record a created label after the order update failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }
}
