<?php

namespace App\Shipping;

final readonly class ShipInput
{
    public ?string $trackingNumber;

    public ?string $labelDriver;

    public ?string $trackingDriver;

    public ?string $quoteToken;

    public ?string $pickupDate;

    /**
     * @param  list<array<string, mixed>>  $parcels  weight/length/width/height per parcel (label mode)
     */
    public function __construct(
        public string $carrierCode,
        ?string $trackingNumber = null,
        public bool $notifyCustomer = false,
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
