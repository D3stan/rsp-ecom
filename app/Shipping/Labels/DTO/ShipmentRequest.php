<?php

namespace App\Shipping\Labels\DTO;

final readonly class ShipmentRequest
{
    /**
     * @param  list<Parcel>  $parcels
     */
    public function __construct(
        public string $carrierCode,
        public Address $sender,
        public Address $recipient,
        public array $parcels,
        public string $reference = '',
        public ?float $insuranceAmount = null,
        public ?float $codAmount = null,
        public string $notes = '',
        public ?string $quoteToken = null,
        public ?string $pickupDate = null,
    ) {}
}
