<?php

namespace App\Shipping;

use App\Models\Shipment;

/**
 * Result of ShippingService::ship(): the shipment recorded, and whether the shipped email could not be queued (the
 * order is shipped either way; the admin gets a warning).
 */
final readonly class ShipOutcome
{
    public function __construct(
        public Shipment $shipment,
        public bool $emailFailed = false,
    ) {}
}
