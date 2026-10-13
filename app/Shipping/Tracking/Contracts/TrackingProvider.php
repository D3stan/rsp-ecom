<?php

namespace App\Shipping\Tracking\Contracts;

use App\Shipping\Tracking\DTO\TrackingRequest;
use App\Shipping\Tracking\DTO\TrackingResult;
use App\Shipping\Tracking\Exceptions\TrackingProviderUnavailable;
use App\Shipping\Tracking\Exceptions\TrackingRejected;

/**
 * A source of shipment tracking information. One class per carrier/aggregator.
 *
 * Adapters translate the carrier's API into the module's vocabulary and nothing else: statuses
 * are mapped to TrackingStatus, times are returned as CarbonImmutable (any timezone), and all
 * failures are reported through the Tracking exceptions. Persistence, retries, scheduling and
 * order status changes are handled by the module, never by the adapter.
 *
 * Capabilities beyond this contract are separate interfaces (SupportsWebhooks,
 * SupportsShipmentCreation) so an adapter implements only what its carrier offers.
 */
interface TrackingProvider
{
    /**
     * Driver name, as used in SHIPPING_TRACKING_DRIVER and stored on tracking events ("provider").
     */
    public function name(): string;

    /**
     * Can this provider track shipments of the carrier with this code (a key of config('shipping.carriers'))?
     * Aggregators usually answer true for every code. Must be cheap and free of side effects.
     */
    public function supports(string $carrierCode): bool;

    /**
     * Fetch the current state of one shipment.
     *
     * @throws TrackingProviderUnavailable temporary problem: the job retries later
     * @throws TrackingRejected the carrier refused this shipment: polling for it stops
     */
    public function track(TrackingRequest $request): TrackingResult;

    /**
     * Public tracking page for the customer, or null when there is none for this number/carrier.
     * Must never throw and never call the network (it is used while rendering emails).
     */
    public function trackingUrl(string $trackingNumber, ?string $carrierCode): ?string;
}
