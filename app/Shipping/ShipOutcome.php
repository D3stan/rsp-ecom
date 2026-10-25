<?php

namespace App\Shipping;

use App\Models\Shipment;

final readonly class ShipOutcome
{
    public function __construct(
        public Shipment $shipment,
        public bool $emailFailed = false,
    ) {}
}
