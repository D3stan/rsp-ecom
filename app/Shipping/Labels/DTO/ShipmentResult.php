<?php

namespace App\Shipping\Labels\DTO;

final readonly class ShipmentResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $externalId,
        public string $trackingNumber,
        public string $carrierCode,
        public array $raw = [],
        public bool $awaitingPayment = false,
    ) {}
}
