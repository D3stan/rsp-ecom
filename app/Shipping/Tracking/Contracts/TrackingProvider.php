<?php

namespace App\Shipping\Tracking\Contracts;

use App\Shipping\Tracking\DTO\TrackingRequest;
use App\Shipping\Tracking\DTO\TrackingResult;
use App\Shipping\Tracking\Exceptions\TrackingProviderUnavailable;
use App\Shipping\Tracking\Exceptions\TrackingRejected;

interface TrackingProvider
{
    public function name(): string;

    public function supports(string $carrierCode): bool;

    /**
     * Fetch the current state of one shipment.
     *
     * @throws TrackingProviderUnavailable temporary problem: the job retries later
     * @throws TrackingRejected the carrier refused this shipment: polling for it stops
     */
    public function track(TrackingRequest $request): TrackingResult;

    public function trackingUrl(string $trackingNumber, ?string $carrierCode): ?string;
}
