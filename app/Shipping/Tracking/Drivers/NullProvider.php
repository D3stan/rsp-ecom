<?php

namespace App\Shipping\Tracking\Drivers;

use App\Shipping\Tracking\Contracts\PassiveProvider;
use App\Shipping\Tracking\Contracts\TrackingProvider;
use App\Shipping\Tracking\DTO\TrackingRequest;
use App\Shipping\Tracking\DTO\TrackingResult;

class NullProvider implements PassiveProvider, TrackingProvider
{
    public function name(): string
    {
        return 'null';
    }

    public function supports(string $carrierCode): bool
    {
        return false;
    }

    public function track(TrackingRequest $request): TrackingResult
    {
        return TrackingResult::unknown();
    }

    public function trackingUrl(string $trackingNumber, ?string $carrierCode): ?string
    {
        return null;
    }
}
