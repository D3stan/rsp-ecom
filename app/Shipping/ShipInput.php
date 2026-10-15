<?php

namespace App\Shipping;

/**
 * What the admin entered on the ship form. Carrier codes are the neutral keys of the shipping.carriers setting.
 */
final readonly class ShipInput
{
    public ?string $trackingNumber;

    public function __construct(
        public string $carrierCode,
        ?string $trackingNumber = null,
        /** Without a tracking number: email the customer now (otherwise the first email goes out with the number). */
        public bool $notifyCustomer = false,
    ) {
        $trackingNumber = trim((string) $trackingNumber);
        $this->trackingNumber = $trackingNumber !== '' ? $trackingNumber : null;
    }
}
