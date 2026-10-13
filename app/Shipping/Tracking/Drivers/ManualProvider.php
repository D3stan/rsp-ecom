<?php

namespace App\Shipping\Tracking\Drivers;

use App\Shipping\Tracking\Contracts\PassiveProvider;
use App\Shipping\Tracking\Contracts\TrackingProvider;
use App\Shipping\Tracking\DTO\TrackingRequest;
use App\Shipping\Tracking\DTO\TrackingResult;

/**
 * Default driver: no API, the customer gets the carrier's public tracking page built from the
 * URL template in config('shipping.carriers'). Status changes stay a manual admin action.
 */
class ManualProvider implements PassiveProvider, TrackingProvider
{
    public function name(): string
    {
        return 'manual';
    }

    public function supports(string $carrierCode): bool
    {
        return $this->template($carrierCode) !== null;
    }

    public function track(TrackingRequest $request): TrackingResult
    {
        return TrackingResult::unknown();
    }

    public function trackingUrl(string $trackingNumber, ?string $carrierCode): ?string
    {
        // trim only: the link carries the number as the admin entered it (URL-encoded)
        $number = trim($trackingNumber);

        if ($number === '' || $carrierCode === null || $carrierCode === '') {
            return null;
        }

        $template = $this->template($carrierCode);

        return $template === null ? null : str_replace('{number}', rawurlencode($number), $template);
    }

    /**
     * The carrier's tracking URL template, only when it is usable: http(s) and containing {number}.
     */
    private function template(string $carrierCode): ?string
    {
        $template = (config('shipping.carriers', [])[$carrierCode] ?? [])['tracking_url'] ?? null;

        if (! is_string($template) || ! str_contains($template, '{number}') || ! preg_match('#^https?://#i', $template)) {
            return null;
        }

        return $template;
    }
}
