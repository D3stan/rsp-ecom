<?php

namespace App\Shipping\Tracking\Drivers\Spediamo;

use App\Shipping\Tracking\Enums\TrackingStatus;

/**
 * API status string => TrackingStatus, for both the shipment's top-level `status` and each `tracking[].status`.
 *
 * Case-insensitive and trimmed. The built-in table follows the statuses the API documents for shipments
 * (the tracking enum itself is not published, see docs/shipping-tracking-module.md); config
 * shipping.tracking.drivers.spediamo.status_map overrides or extends it without code changes.
 * Blank values are "no information" (Unknown, not reported); anything else that is not listed is
 * Unknown too, and isMapped() lets the caller warn about it.
 */
final class SpediamoStatusMap
{
    /** @var array<string, TrackingStatus> */
    private const DEFAULTS = [
        'DRAFT' => TrackingStatus::InfoReceived,
        'OPEN' => TrackingStatus::InfoReceived,
        'BOOKED' => TrackingStatus::InfoReceived,
        'READY' => TrackingStatus::InfoReceived,
        'CURRENT' => TrackingStatus::InTransit,
        'DELIVERED' => TrackingStatus::Delivered,
        'ON_HOLD' => TrackingStatus::Exception,
        'FAILED' => TrackingStatus::Exception,
        'UNDERPAID' => TrackingStatus::Exception,
    ];

    /** @var array<string, TrackingStatus> */
    private array $map;

    /**
     * @param  array<array-key, mixed>  $overrides  API status => TrackingStatus (enum case or its string value)
     */
    public function __construct(array $overrides = [])
    {
        $this->map = self::DEFAULTS;

        foreach ($overrides as $code => $status) {
            $key = self::normalize((string) $code);
            $resolved = $status instanceof TrackingStatus ? $status : TrackingStatus::tryFrom(strtolower(trim((string) $status)));

            if ($key !== '' && $resolved !== null) {
                $this->map[$key] = $resolved;
            }
        }
    }

    public function map(?string $apiStatus): TrackingStatus
    {
        return $this->map[self::normalize($apiStatus)] ?? TrackingStatus::Unknown;
    }

    /**
     * Is this value known to the map (or blank, which carries no information and needs no warning)?
     */
    public function isMapped(?string $apiStatus): bool
    {
        $key = self::normalize($apiStatus);

        return $key === '' || isset($this->map[$key]);
    }

    /**
     * @return array<string, TrackingStatus>
     */
    public function all(): array
    {
        return $this->map;
    }

    public static function normalize(?string $apiStatus): string
    {
        return strtoupper(trim((string) $apiStatus));
    }
}
