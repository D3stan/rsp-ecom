<?php

namespace App\Shipping\Labels\DTO;

/**
 * What a driver returns after creating a shipment: the carrier's own id, the tracking number and the neutral carrier
 * code (carried through so callers never need the driver). `raw` keeps non-secret response facts for the audit row.
 */
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
    ) {}
}
