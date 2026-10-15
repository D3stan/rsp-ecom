<?php

namespace App\Shipping\Labels\Contracts;

use App\Shipping\Labels\DTO\LabelDocument;
use App\Shipping\Labels\DTO\ShipmentRequest;
use App\Shipping\Labels\DTO\ShipmentResult;
use App\Shipping\Labels\Exceptions\LabelProviderUnavailable;
use App\Shipping\Labels\Exceptions\LabelRejected;

/**
 * A carrier that can create a shipment and produce a shipping label. The modular label layer talks only to this
 * contract, so a new carrier is one driver class + one registration line + env credentials; nothing in controllers,
 * pages or emails is carrier specific.
 */
interface LabelProvider
{
    /** Stable driver name (matches the registration and config key). */
    public function name(): string;

    /** Does this driver handle the given neutral carrier code (config('shipping.carriers'))? */
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
