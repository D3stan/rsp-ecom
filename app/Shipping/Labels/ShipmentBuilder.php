<?php

namespace App\Shipping\Labels;

use App\Models\Order;
use App\Shipping\Labels\DTO\Address;
use App\Shipping\Labels\DTO\Parcel;
use App\Shipping\Labels\DTO\ShipmentRequest;
use App\Shipping\Support\SenderAddress;

/**
 * Builds a provider-neutral ShipmentRequest from an order: the sender from the shipping settings (SenderAddress), the recipient from
 * the order's shipping address, and the parcels from the admin's parcel input (prefilled from the order items' box
 * sizes and a default weight). Carrier specific nothing lives here.
 */
class ShipmentBuilder
{
    /**
     * @param  list<array{weight?: float|int|string|null, length?: mixed, width?: mixed, height?: mixed}>  $parcels
     */
    public function build(
        Order $order,
        string $carrierCode,
        array $parcels = [],
        ?float $insurance = null,
        ?float $cod = null,
        ?string $quoteToken = null,
        ?string $pickupDate = null,
    ): ShipmentRequest {
        return new ShipmentRequest(
            carrierCode: $carrierCode,
            sender: $this->sender(),
            recipient: $this->recipient($order),
            parcels: $this->parcels($parcels),
            reference: (string) $order->order_number,
            insuranceAmount: $insurance,
            codAmount: $cod,
            quoteToken: $quoteToken,
            pickupDate: $pickupDate,
        );
    }

    /**
     * Prefilled parcels for the ship form: one box per unit ordered (a quantity of 2 ships in 2 boxes), each with its
     * size's packed weight or the default weight, capped at the form's 20 parcels.
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
            $quantity = max(1, (int) $item->quantity);
            $name = trim($item->product_name.($size?->name ? " ({$size->name})" : '')) ?: 'Parcel';

            for ($unit = 1; $unit <= $quantity; $unit++) {
                $parcels[] = [
                    'weight' => $size?->weight_kg !== null ? (float) $size->weight_kg : $weight,
                    'length' => $size?->length !== null ? (float) $size->length : null,
                    'width' => $size?->width !== null ? (float) $size->width : null,
                    'height' => $size?->height !== null ? (float) $size->height : null,
                    'label' => $quantity > 1 ? "{$name} {$unit}/{$quantity}" : $name,
                ];
            }
        }

        return $parcels !== [] ? array_slice($parcels, 0, 20) : [['weight' => $weight, 'length' => null, 'width' => null, 'height' => null, 'label' => 'Parcel']];
    }

    private function sender(): Address
    {
        $sender = SenderAddress::fromSettings();

        return new Address(
            name: $sender['name'],
            street: $sender['street'],
            city: $sender['city'],
            postalCode: $sender['postal_code'],
            province: $sender['province'],
            country: $sender['country'],
            phone: $sender['phone'],
            email: $sender['email'],
        );
    }

    private function recipient(Order $order): Address
    {
        $address = $order->shippingAddress;

        $country = (string) ($address?->country ?? '');
        $postalCode = trim((string) ($address?->postal_code ?? ''));
        if (strtoupper($country) === 'IT' && is_numeric($postalCode) && $postalCode !== '') {
            $postalCode = str_pad($postalCode, 5, '0', STR_PAD_LEFT);
        }

        return new Address(
            name: trim((string) ($address?->first_name.' '.$address?->last_name)) ?: (string) $order->getCustomerName(),
            street: trim((string) ($address?->address_line_1.' '.$address?->address_line_2)),
            city: (string) ($address?->city ?? ''),
            postalCode: $postalCode,
            province: (string) ($address?->state ?? ''),
            country: $country,
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
