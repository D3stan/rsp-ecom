<?php

namespace App\Shipping;

/**
 * What the admin entered on the ship form. Carrier codes are the neutral keys of the shipping.carriers setting.
 *
 * Two modes:
 *  - manual (labels off, the default): carrierCode + trackingNumber (or ship without one).
 *  - label  (labels on, createLabel): carrierCode + parcels; the carrier returns the tracking number and the label PDF.
 */
final readonly class ShipInput
{
    public ?string $trackingNumber;

    /**
     * @param  list<array<string, mixed>>  $parcels  weight/length/width/height per parcel (label mode)
     */
    public function __construct(
        public string $carrierCode,
        ?string $trackingNumber = null,
        /** Without a tracking number: email the customer now (otherwise the first email goes out with the number). */
        public bool $notifyCustomer = false,
        /** Label mode: create the shipment and label through the carrier (only honoured when labels are enabled). */
        public bool $createLabel = false,
        public array $parcels = [],
        public ?float $insuranceAmount = null,
        public ?float $codAmount = null,
    ) {
        $trackingNumber = trim((string) $trackingNumber);
        $this->trackingNumber = $trackingNumber !== '' ? $trackingNumber : null;
    }
}
