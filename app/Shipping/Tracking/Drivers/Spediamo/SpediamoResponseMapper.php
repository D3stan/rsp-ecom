<?php

namespace App\Shipping\Tracking\Drivers\Spediamo;

use App\Shipping\Tracking\DTO\TrackingEvent;
use App\Shipping\Tracking\DTO\TrackingResult;
use App\Shipping\Tracking\Enums\TrackingStatus;
use Carbon\CarbonImmutable;
use Closure;
use Throwable;

/**
 * Pure translation of one `GET /v1/shipments/tracking` answer into a TrackingResult. No I/O.
 *
 * Fields used (docs/spediamo/shipments.md): top level `status`, `expectedDeliveryDate`, `deliveryDate`
 * (YYYY-MM-DD), `courier`, `trackingNumber`, `code`, and `tracking[]` items
 * `{date, time{hour,minute,second}, description, longDescription, site, status}`.
 *
 * Event times are wall-clock times in the configured timezone (default Europe/Rome) and are converted to
 * UTC; a wall-clock time skipped by the spring DST jump moves forward by the missing hour, an ambiguous
 * autumn time resolves to its second (winter-time) occurrence (PHP's rule). Events have no id, so externalId stays null
 * and the module dedupes on content.
 *
 * The result status is Delivered when the mapped top-level (or latest event) status is Delivered OR the
 * top-level `deliveryDate` is not empty; otherwise it is the most advanced mapped status (the top-level
 * one wins ties, then the latest event).
 */
final class SpediamoResponseMapper
{
    /**
     * @param  Closure(string): void|null  $onUnmapped  called once per unmapped status string met in an answer
     */
    public function __construct(
        private readonly SpediamoStatusMap $statuses,
        private readonly string $timezone = 'Europe/Rome',
        private readonly ?Closure $onUnmapped = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  decoded response body
     */
    public function map(array $data): TrackingResult
    {
        $events = [];

        foreach ((array) ($data['tracking'] ?? []) as $item) {
            $event = is_array($item) ? $this->event($item) : null;

            if ($event !== null) {
                $events[] = $event;
            }
        }

        usort($events, fn (TrackingEvent $a, TrackingEvent $b) => $a->occurredAt <=> $b->occurredAt);

        return new TrackingResult(
            $this->resultStatus($data, $events),
            $events,
            $this->date($data['expectedDeliveryDate'] ?? null),
            $data,
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function event(array $item): ?TrackingEvent
    {
        $occurredAt = $this->occurredAt($item);

        if ($occurredAt === null) {
            return null;
        }

        $description = trim((string) ($item['description'] ?? ''));
        $long = trim((string) ($item['longDescription'] ?? ''));

        if ($description === '') {
            $description = $long;
        } elseif ($long !== '' && mb_strtolower($long) !== mb_strtolower($description)) {
            $description .= ' - '.$long;
        }

        $site = trim((string) ($item['site'] ?? ''));

        return new TrackingEvent(
            status: $this->status($item['status'] ?? null),
            description: $description !== '' ? $description : trim((string) ($item['status'] ?? '')),
            location: $site !== '' ? $site : null,
            occurredAt: $occurredAt,
            externalId: null,
            raw: $item,
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function occurredAt(array $item): ?CarbonImmutable
    {
        $date = trim((string) ($item['date'] ?? ''));

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $date) !== 1) {
            return null;
        }

        $time = is_array($item['time'] ?? null) ? $item['time'] : [];

        try {
            return CarbonImmutable::parse(sprintf(
                '%s %02d:%02d:%02d',
                substr($date, 0, 10),
                min(23, max(0, (int) ($time['hour'] ?? 0))),
                min(59, max(0, (int) ($time['minute'] ?? 0))),
                min(59, max(0, (int) ($time['second'] ?? 0))),
            ), $this->timezone)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<TrackingEvent>  $events  oldest first
     */
    private function resultStatus(array $data, array $events): TrackingStatus
    {
        $top = $this->status($data['status'] ?? null);
        $latest = $events === [] ? TrackingStatus::Unknown : $events[array_key_last($events)]->status;

        if ($top === TrackingStatus::Delivered || $latest === TrackingStatus::Delivered || trim((string) ($data['deliveryDate'] ?? '')) !== '') {
            return TrackingStatus::Delivered;
        }

        // Candidates in priority order: top level, then events newest first; the first one wins a tie.
        $best = $top;

        foreach (array_reverse($events) as $event) {
            if ($event->status->rank() > $best->rank()) {
                $best = $event->status;
            }
        }

        return $best;
    }

    private function status(mixed $apiStatus): TrackingStatus
    {
        $value = is_scalar($apiStatus) ? (string) $apiStatus : null;

        if (! $this->statuses->isMapped($value) && $this->onUnmapped !== null) {
            ($this->onUnmapped)(trim((string) $value));
        }

        return $this->statuses->map($value);
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        $value = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::parse(substr($value, 0, 10), $this->timezone)->startOfDay()->utc();
        } catch (Throwable) {
            return null;
        }
    }
}
