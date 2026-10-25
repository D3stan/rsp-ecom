<?php

namespace App\Shipping\Providers\Dvaexpress;

trait ResolvesCarrierTrackingUrl
{
    public function carrierTrackingUrl(string $trackingNumber, ?string $carrierCode = null): ?string
    {
        $number = trim($trackingNumber);

        if ($number === '' || $carrierCode === null || trim($carrierCode) === '') {
            return null;
        }

        $template = (config('shipping.carriers', [])[$carrierCode] ?? [])['tracking_url'] ?? null;

        if (! is_string($template) || ! str_contains($template, '{number}') || ! preg_match('#^https?://#i', $template)) {
            return null;
        }

        return str_replace('{number}', rawurlencode($number), $template);
    }
}
