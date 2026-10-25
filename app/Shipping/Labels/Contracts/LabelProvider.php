<?php

namespace App\Shipping\Labels\Contracts;

use App\Shipping\Labels\DTO\LabelDocument;
use App\Shipping\Labels\DTO\ShipmentRequest;
use App\Shipping\Labels\DTO\ShipmentResult;
use App\Shipping\Labels\Exceptions\LabelProviderUnavailable;
use App\Shipping\Labels\Exceptions\LabelRejected;

interface LabelProvider
{
    public function name(): string;

    public function supports(string $carrierCode): bool;

    /**
     * Create the shipment at the carrier and return its external id + tracking number.
     *
     * @throws LabelRejected the carrier refused the request (validation): show the message, no state change
     * @throws LabelProviderUnavailable a transient/infrastructure problem: retry later, no state change
     */
    public function createShipment(ShipmentRequest $request): ShipmentResult;

    /**
     * Fetch the label PDF for a shipment (by result or external id).
     *
     * @throws LabelRejected
     * @throws LabelProviderUnavailable
     */
    public function label(ShipmentResult|string $externalId): LabelDocument;
}
