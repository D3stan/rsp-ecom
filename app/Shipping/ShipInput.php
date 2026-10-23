<?php

namespace App\Shipping;

/**
 * What the admin entered on the ship form. Carrier codes are the neutral keys of the shipping.carriers setting.
 *
 * Two modes:
 *  - manual (labels off, or createLabel false): carrierCode + trackingNumber (or ship without one), optionally paired
 *    with a specific trackingDriver (e.g. 'manual' for URL-only or a pollable provider when the label was created externally).
 *  - label  (labels on, createLabel true): carrierCode + parcels + optional labelDriver; the provider returns the
 *    tracking number and the label PDF.
 */
final readonly class ShipInput
{
    public ?string $trackingNumber;

    public ?string $labelDriver;

    public ?string $trackingDriver;

    public ?string $quoteToken;

    /** Label mode: the day the carrier collects the parcel (Y-m-d). */
    public ?string $pickupDate;

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
        ?string $labelDriver = null,
        ?string $trackingDriver = null,
        ?string $quoteToken = null,
        ?string $pickupDate = null,
    ) {
        $trackingNumber = trim((string) $trackingNumber);
        $this->trackingNumber = $trackingNumber !== '' ? $trackingNumber : null;

        $labelDriver = trim((string) $labelDriver);
        $this->labelDriver = $labelDriver !== '' ? $labelDriver : null;

        $trackingDriver = trim((string) $trackingDriver);
        $this->trackingDriver = $trackingDriver !== '' ? $trackingDriver : null;

        $quoteToken = trim((string) $quoteToken);
        $this->quoteToken = $quoteToken !== '' ? $quoteToken : null;

        $pickupDate = trim((string) $pickupDate);
        $this->pickupDate = $pickupDate !== '' ? $pickupDate : null;
    }
}
