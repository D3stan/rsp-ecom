<?php

namespace App\Shipping\Tracking\DTO;

use App\Shipping\Tracking\Enums\TrackingStatus;
use Carbon\CarbonImmutable;

/**
 * One scan/checkpoint of a shipment, already normalised by the adapter.
 */
final readonly class TrackingEvent
{
    /**
     * @param  CarbonImmutable  $occurredAt  when it happened (any timezone; stored as UTC)
     * @param  string|null  $externalId  the carrier's own id for the event, when it has one (best dedupe key)
     * @param  array<string, mixed>|null  $raw  the carrier's original record, for diagnostics (size-capped when stored)
     */
    public function __construct(
        public TrackingStatus $status,
        public string $description,
        public ?string $location,
        public CarbonImmutable $occurredAt,
        public ?string $externalId = null,
        public ?array $raw = null,
    ) {}

    /**
     * Stable identity of the event for one provider and shipment: the same event delivered twice
     * (poll + webhook, carrier retry, overlapping poll windows) always yields the same hash.
     */
    public function hash(string $provider, string $trackingNumber): string
    {
        $number = mb_strtolower(TrackingRequest::normalizeNumber($trackingNumber));

        if ($this->externalId !== null && $this->externalId !== '') {
            return hash('sha256', implode('|', [$provider, $number, 'id', $this->externalId]));
        }

        return hash('sha256', implode('|', [
            $provider,
            $number,
            $this->status->value,
            $this->occurredAt->utc()->format('Y-m-d\TH:i:s'),
            mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $this->description))),
            mb_strtolower(trim((string) $this->location)),
        ]));
    }
}
