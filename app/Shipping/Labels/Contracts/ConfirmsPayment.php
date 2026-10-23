<?php

namespace App\Shipping\Labels\Contracts;

use App\Shipping\Labels\DTO\ShipmentResult;
use App\Shipping\Labels\Exceptions\LabelProviderUnavailable;
use App\Shipping\Labels\Exceptions\LabelRejected;

/**
 * Optional capability of a LabelProvider whose createShipment() only makes a draft that the owner pays on the carrier's
 * own site (the API has no pay operation). The order page asks again later, on demand, whether it has been paid.
 */
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
