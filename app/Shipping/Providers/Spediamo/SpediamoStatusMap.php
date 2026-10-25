<?php

namespace App\Shipping\Providers\Spediamo;

use App\Shipping\Tracking\Enums\TrackingStatus;

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
