<?php

namespace App\Shipping;

use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Models\OrderEmail;
use App\Models\Shipment;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The only way an order becomes "shipped" (controllers call nothing else). It records the shipment, stores the current
 * carrier and tracking number on the order (tracking module, emails), moves the order through the lifecycle and sends
 * the shipped email: one, with the tracking when there is a number. Nothing here is carrier specific.
 */
class ShippingService
{
    public function __construct(private readonly EmailService $emails) {}

    /**
     * @throws InvalidOrderTransition when the order may not ship (not processing, not paid, or already shipped)
     */
    public function ship(Order $order, ShipInput $input, User $actor): ShipOutcome
    {
        $shipment = DB::transaction(function () use ($order, $input, $actor) {
            // Decide on the stored, locked row: a second submit, or a refund meanwhile, must not ship twice or unpaid
            Order::query()->whereKey($order->getKey())->lockForUpdate()->first();
            $order->refresh();

            if (! $order->canShip()) {
                throw new InvalidOrderTransition("Order {$order->order_number} cannot ship now: it must be processing and paid.");
            }

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
}
