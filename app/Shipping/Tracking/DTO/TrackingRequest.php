<?php

namespace App\Shipping\Tracking\DTO;

/**
 * What an adapter needs to look up one shipment.
 */
final readonly class TrackingRequest
{
    public function __construct(
        public string $trackingNumber,
        public ?string $carrierCode = null,
        public ?string $orderReference = null,
        public ?string $providerCode = null,
    ) {}

    /**
     * Canonical form for comparing tracking numbers: no surrounding or inner whitespace.
     * Case is kept (some carriers are case sensitive); lookups compare case-insensitively.
     */
    public static function normalizeNumber(?string $number): string
    {
        return preg_replace('/\s+/u', '', trim((string) $number)) ?? '';
    }
}
