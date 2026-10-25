<?php

namespace App\Shipping\Tracking\DTO;

final readonly class TrackingUpdate
{
    public function __construct(
        public string $trackingNumber,
        public ?string $carrierCode,
        public TrackingResult $result,
    ) {}
}
