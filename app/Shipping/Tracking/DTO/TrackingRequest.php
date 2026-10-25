<?php

namespace App\Shipping\Tracking\DTO;

final readonly class TrackingRequest
{
    public function __construct(
        public string $trackingNumber,
        public ?string $carrierCode = null,
        public ?string $orderReference = null,
        public ?string $providerCode = null,
    ) {}

    public static function normalizeNumber(?string $number): string
    {
        return preg_replace('/\s+/u', '', trim((string) $number)) ?? '';
    }
}
