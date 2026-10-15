<?php

namespace App\Shipping\Labels;

use App\Models\Order;
use App\Shipping\Labels\DTO\Address;
use App\Shipping\Labels\DTO\Parcel;
use App\Shipping\Labels\DTO\ShipmentRequest;

/**
 * Builds a provider-neutral ShipmentRequest from an order: the sender from the shipping settings, the recipient from
 * the order's shipping address, and the parcels from the admin's parcel input (prefilled from the order items' box
 * sizes and a default weight). Carrier specific nothing lives here.
 */
class ShipmentBuilder
{
    /**
     * @param  list<array{weight?: float|int|string|null, length?: mixed, width?: mixed, height?: mixed}>  $parcels
     */
    public function build(Order $order, string $carrierCode, array $parcels = [], ?float $insurance = null, ?float $cod = null): ShipmentRequest
    {
        return new ShipmentRequest(
            carrierCode: $carrierCode,
            sender: $this->sender(),
            recipient: $this->recipient($order),
            parcels: $this->parcels($parcels),
            reference: (string) $order->order_number,
            insuranceAmount: $insurance,
            codAmount: $cod,
        );
    }

    /**
     * Prefilled parcels for the ship form: one per distinct box size on the order, each with the default weight.
     *
     * @return list<array{weight: float, length: float|null, width: float|null, height: float|null, label: string}>
     */
    public function prefillParcels(Order $order): array
    {
        $weight = $this->defaultWeight();
        $order->loadMissing('orderItems.size');

        $parcels = [];

        foreach ($order->orderItems as $item) {
            $size = $item->size;

            $parcels[] = [
                'weight' => $weight,
                'length' => $size?->length !== null ? (float) $size->length : null,
                'width' => $size?->width !== null ? (float) $size->width : null,
                'height' => $size?->height !== null ? (float) $size->height : null,
                'label' => $size?->name ? (string) $size->name : 'Parcel',
            ];
        }

        return $parcels !== [] ? $parcels : [['weight' => $weight, 'length' => null, 'width' => null, 'height' => null, 'label' => 'Parcel']];
    }

    private function sender(): Address
    {
        return new Address(
            name: (string) settings('shipping.sender_name', settings('store.name', '')),
            street: (string) settings('shipping.sender_street', settings('store.address', '')),
            city: (string) settings('shipping.sender_city', ''),
            postalCode: (string) settings('shipping.sender_postal_code', ''),
            province: (string) settings('shipping.sender_province', ''),
            country: (string) settings('shipping.sender_country', settings('shipping.default_country', 'IT')),
            phone: (string) settings('shipping.sender_phone', settings('contact.phone', '')),
            email: (string) settings('shipping.sender_email', settings('contact.support_email', '')),
        );
    }

    private function recipient(Order $order): Address
    {
        $address = $order->shippingAddress;

        return new Address(
            name: trim((string) ($address?->first_name.' '.$address?->last_name)) ?: (string) $order->getCustomerName(),
            street: trim((string) ($address?->address_line_1.' '.$address?->address_line_2)),
            city: (string) ($address?->city ?? ''),
            postalCode: (string) ($address?->postal_code ?? ''),
            province: (string) ($address?->state ?? ''),
            country: (string) ($address?->country ?? ''),
            phone: (string) ($address?->phone ?? $order->guest_phone ?? ''),
            email: (string) ($order->getCustomerEmail() ?? ''),
            company: (string) ($address?->company ?? ''),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $parcels
     * @return list<Parcel>
     */
    private function parcels(array $parcels): array
    {
        $weight = $this->defaultWeight();
        $built = [];

        foreach ($parcels as $parcel) {
            $built[] = new Parcel(
                weightKg: ($w = (float) ($parcel['weight'] ?? 0)) > 0 ? $w : $weight,
                lengthCm: isset($parcel['length']) && $parcel['length'] !== '' ? (float) $parcel['length'] : null,
                widthCm: isset($parcel['width']) && $parcel['width'] !== '' ? (float) $parcel['width'] : null,
                heightCm: isset($parcel['height']) && $parcel['height'] !== '' ? (float) $parcel['height'] : null,
            );
        }

        return $built !== [] ? $built : [new Parcel(weightKg: $weight)];
    }

    private function defaultWeight(): float
    {
        return max(0.01, (float) settings('shipping.default_parcel_weight_kg', 1.0));
    }
}
