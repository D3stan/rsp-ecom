<?php

namespace App\Shipping\Providers\Dvaexpress;

use App\Shipping\Tracking\DTO\TrackingEvent;
use App\Shipping\Tracking\DTO\TrackingResult;
use App\Shipping\Tracking\Enums\TrackingStatus;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Throwable;

final class DvaexpressResponseMapper
{
    private const DEFAULT_TIMEZONE = 'Europe/Rome';

    private readonly string $timezone;

    public function __construct(
        private readonly DvaexpressStatusMap $statuses,
        ?string $timezone = null,
    ) {
        $tz = trim((string) $timezone);
        $this->timezone = in_array($tz, DateTimeZone::listIdentifiers(), true) ? $tz : self::DEFAULT_TIMEZONE;
    }

    /**
     * Map EasyParcel POST /tracking/{APIKEY} JSON response to TrackingResult.
     *
     * @param  array<string, mixed>  $data
     */
    public function map(array $data): TrackingResult
    {
        $tracking = is_array($data['tracking'] ?? null) ? $data['tracking'] : [];
        $dettagli = is_array($data['dettagli'] ?? null) ? $data['dettagli'] : [];

        $events = [];

        foreach ($dettagli as $item) {
            if (! is_array($item)) {
                continue;
            }

            $event = $this->mapEvent($item);
            if ($event !== null) {
                $events[] = $event;
            }
        }

        usort($events, fn (TrackingEvent $a, TrackingEvent $b) => $a->occurredAt <=> $b->occurredAt);

        $overallStatus = $this->determineStatus($tracking, $events);

        return new TrackingResult(
            status: $overallStatus,
            events: $events,
            estimatedDelivery: null,
            raw: $data,
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function mapEvent(array $item): ?TrackingEvent
    {
        $occurredAt = $this->parseOccurredAt($item);
        if ($occurredAt === null) {
            return null;
        }

        $descrizione = trim((string) ($item['descrizione'] ?? ''));
        $note = trim((string) ($item['note'] ?? ''));

        if ($descrizione !== '' && $note !== '') {
            if (mb_strtolower($descrizione) !== mb_strtolower($note)) {
                $description = "{$descrizione} - {$note}";
            } else {
                $description = $descrizione;
            }
        } elseif ($descrizione !== '') {
            $description = $descrizione;
        } elseif ($note !== '') {
            $description = $note;
        } else {
            $description = trim((string) ($item['status'] ?? 'Aggiornamento tracking'));
        }

        $filiale = trim((string) ($item['filiale'] ?? ''));
        $location = $filiale !== '' ? $filiale : null;

        $status = $this->statuses->map(
            is_scalar($item['status'] ?? null) ? (string) $item['status'] : null,
            $descrizione !== '' ? $descrizione : null,
            is_scalar($item['firma'] ?? null) ? (string) $item['firma'] : null,
        );

        return new TrackingEvent(
            status: $status,
            description: $description,
            location: $location,
            occurredAt: $occurredAt,
            externalId: null,
            raw: $item,
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function parseOccurredAt(array $item): ?CarbonImmutable
    {
        $data = trim((string) ($item['data'] ?? ''));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}/', $data)) {
            return null;
        }

        $datePart = substr($data, 0, 10);
        $ora = trim((string) ($item['ora'] ?? '00:00:00'));

        if (! preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $ora)) {
            $ora = '00:00:00';
        }

        try {
            return CarbonImmutable::parse("{$datePart} {$ora}", $this->timezone)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $tracking
     * @param  list<TrackingEvent>  $events
     */
    private function determineStatus(array $tracking, array $events): TrackingStatus
    {
        foreach ($events as $event) {
            if ($event->status === TrackingStatus::Delivered) {
                return TrackingStatus::Delivered;
            }
        }

        if (count($events) > 0) {
            return $events[array_key_last($events)]->status;
        }

        $ldv = trim((string) ($tracking['lettera_vettura'] ?? ''));

        return $ldv !== '' ? TrackingStatus::InfoReceived : TrackingStatus::Unknown;
    }
}
