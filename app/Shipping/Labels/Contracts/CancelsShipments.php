<?php

namespace App\Shipping\Labels\Contracts;

use App\Shipping\Labels\Exceptions\LabelProviderUnavailable;
use App\Shipping\Labels\Exceptions\LabelRejected;

/**
 * Optional capability: a label driver that can cancel a shipment at the carrier. Cancelling does not change the
 * order status (the admin then uses "undo ship").
 */
interface CancelsShipments
{
    /**
     * @throws LabelRejected
     * @throws LabelProviderUnavailable
     */
    public function cancelShipment(string $externalId): void;
}
