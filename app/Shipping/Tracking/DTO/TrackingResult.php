<?php

namespace App\Shipping\Tracking\DTO;

use App\Shipping\Tracking\Enums\TrackingStatus;
use Carbon\CarbonImmutable;

/**
 * The current picture of one shipment, as returned by an adapter (poll) or carried by a webhook.
 */
final readonly class TrackingResult
{
    /**
     * @param  list<TrackingEvent>  $events  any order; may be a partial history (webhooks usually carry one event)
     * @param  CarbonImmutable|null  $estimatedDelivery  informational, not persisted yet
     * @param  array<string, mixed>|null  $raw  the carrier's original answer, for diagnostics
     * @param  string|null  $carrierTrackingNumber  courier's real waybill number when different from platform shipment code
     * @param  string|null  $trackingUrl  direct carrier tracking URL if provided by provider
     */
    public function __construct(
        public TrackingStatus $status,
        public array $events = [],
        public ?CarbonImmutable $estimatedDelivery = null,
        public ?array $raw = null,
        public ?string $carrierTrackingNumber = null,
        public ?string $trackingUrl = null,
    ) {}

    /**
     * "Nothing known": what passive drivers answer, and what a carrier that has not scanned the parcel yet means.
     */
    public static function unknown(): self
    {
        return new self(TrackingStatus::Unknown);
    }
}
