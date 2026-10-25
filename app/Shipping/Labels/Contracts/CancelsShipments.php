<?php

namespace App\Shipping\Labels\Contracts;

use App\Shipping\Labels\Exceptions\LabelProviderUnavailable;
use App\Shipping\Labels\Exceptions\LabelRejected;

interface CancelsShipments
{
    /**
     * @throws LabelRejected
     * @throws LabelProviderUnavailable
     */
    public function cancelShipment(string $externalId): void;
}
