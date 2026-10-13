<?php

namespace App\Shipping\Tracking\DTO;

/**
 * One shipment's news as pushed by a carrier webhook: which parcel, and what is new.
 */
final readonly class TrackingUpdate
{
    public function __construct(
        public string $trackingNumber,
        public ?string $carrierCode,
        public TrackingResult $result,
    ) {}
}
