<?php

namespace App\Shipping\Labels\Contracts;

use App\Shipping\Labels\DTO\ShipmentResult;
use App\Shipping\Labels\Exceptions\LabelProviderUnavailable;
use App\Shipping\Labels\Exceptions\LabelRejected;

interface ConfirmsPayment
{
    /**
     * The shipment once it has been paid, or null while it is still a draft.
     *
     * @throws LabelRejected
     * @throws LabelProviderUnavailable
     */
    public function confirmPurchase(string $externalId): ?ShipmentResult;
}
