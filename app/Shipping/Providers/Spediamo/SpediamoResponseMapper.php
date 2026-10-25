<?php

namespace App\Shipping\Providers\Spediamo;

use App\Shipping\Tracking\DTO\TrackingEvent;
use App\Shipping\Tracking\DTO\TrackingResult;
use App\Shipping\Tracking\Enums\TrackingStatus;
use Carbon\CarbonImmutable;
use Closure;
use Throwable;

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

        $carrierNumber = is_scalar($data['trackingNumber'] ?? null) ? trim((string) $data['trackingNumber']) : '';
        $code = is_scalar($data['code'] ?? null) ? trim((string) $data['code']) : '';
        $carrierTrackingNumber = ($carrierNumber !== '' && strcasecmp($carrierNumber, $code) !== 0) ? $carrierNumber : null;

        return new TrackingResult(
            $this->resultStatus($data, $events),
            $events,
            $this->date($data['expectedDeliveryDate'] ?? null),
            $data,
            carrierTrackingNumber: $carrierTrackingNumber,
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

        $description = is_scalar($item['description'] ?? null) ? trim((string) $item['description']) : '';
        $long = is_scalar($item['longDescription'] ?? null) ? trim((string) $item['longDescription']) : '';

        if ($description === '') {
            $description = $long;
        } elseif ($long !== '' && mb_strtolower($long) !== mb_strtolower($description)) {
            $description .= ' - '.$long;
        }

        $site = is_scalar($item['site'] ?? null) ? trim((string) $item['site']) : '';

        return new TrackingEvent(
            status: $this->status($item['status'] ?? null),
            description: $description !== '' ? $description : (is_scalar($item['status'] ?? null) ? trim((string) $item['status']) : ''),
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
        $dateStr = $this->parseDateString($item['date'] ?? null);

        if ($dateStr === null || preg_match('/^\d{4}-\d{2}-\d{2}/', $dateStr) !== 1) {
            return null;
        }

        $time = is_array($item['time'] ?? null) ? $item['time'] : [];

        try {
            return CarbonImmutable::parse(sprintf(
                '%s %02d:%02d:%02d',
                substr($dateStr, 0, 10),
                min(23, max(0, (int) ($time['hour'] ?? 0))),
                min(59, max(0, (int) ($time['minute'] ?? 0))),
                min(59, max(0, (int) ($time['second'] ?? 0))),
            ), $this->timezone)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    private function parseDateString(mixed $date): ?string
    {
        if (is_string($date) || is_numeric($date)) {
            $str = trim((string) $date);

            return $str !== '' ? $str : null;
        }

        if (is_array($date)) {
            $year = $date['year'] ?? ($date[0] ?? null);
            $month = $date['monthValue'] ?? ($date['month'] ?? ($date[1] ?? null));
            $day = $date['dayOfMonth'] ?? ($date['day'] ?? ($date[2] ?? null));

            if ($year !== null && $month !== null && $day !== null) {
                if (is_string($month) && ! is_numeric($month)) {
                    $monthNum = date_parse($month)['month'] ?? null;
                    if ($monthNum) {
                        $month = $monthNum;
                    }
                }

                return sprintf('%04d-%02d-%02d', (int) $year, (int) $month, (int) $day);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<TrackingEvent>  $events  oldest first
     */
    private function resultStatus(array $data, array $events): TrackingStatus
    {
        $top = $this->status($data['status'] ?? null);
        $latest = $events === [] ? TrackingStatus::Unknown : $events[array_key_last($events)]->status;
        $hasDeliveryDate = $this->parseDateString($data['deliveryDate'] ?? null) !== null;

        if ($top === TrackingStatus::Delivered || $latest === TrackingStatus::Delivered || $hasDeliveryDate) {
            return TrackingStatus::Delivered;
        }

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
        $valueStr = $this->parseDateString($value);

        if ($valueStr === null || preg_match('/^\d{4}-\d{2}-\d{2}/', $valueStr) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::parse(substr($valueStr, 0, 10), $this->timezone)->startOfDay()->utc();
        } catch (Throwable) {
            return null;
        }
    }
}
