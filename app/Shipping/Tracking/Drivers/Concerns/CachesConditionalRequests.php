<?php

namespace App\Shipping\Tracking\Drivers\Concerns;

use App\Shipping\Tracking\DTO\TrackingEvent;
use App\Shipping\Tracking\DTO\TrackingRequest;
use App\Shipping\Tracking\DTO\TrackingResult;
use App\Shipping\Tracking\Enums\TrackingStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Throwable;

trait CachesConditionalRequests
{
    /**
     * @return array<string, string> headers to add to the request: If-None-Match when an ETag is cached
     */
    protected function conditionalHeaders(string $trackingNumber): array
    {
        $entry = $this->conditionalEntry($trackingNumber);

        return $entry === null ? [] : ['If-None-Match' => $entry['etag']];
    }

    protected function cachedResult(string $trackingNumber): ?TrackingResult
    {
        $entry = $this->conditionalEntry($trackingNumber);

        return $entry === null ? null : $this->resultFromArray($entry['result']);
    }

    protected function rememberResult(string $trackingNumber, Response $response, TrackingResult $result): void
    {
        $etag = trim((string) $response->header('ETag'));

        if ($etag === '') {
            Cache::forget($this->conditionalKey($trackingNumber));

            return;
        }

        Cache::put($this->conditionalKey($trackingNumber), [
            'etag' => $etag,
            'result' => $this->resultToArray($result),
        ], now()->addMinutes($this->conditionalTtlMinutes()));
    }

    protected function forgetConditional(string $trackingNumber): void
    {
        Cache::forget($this->conditionalKey($trackingNumber));
    }

    protected function conditionalTtlMinutes(): int
    {
        return min(max((int) ($this->config['etag_ttl_minutes'] ?? 10080), 1), 20160);
    }

    private function conditionalKey(string $trackingNumber): string
    {
        $number = mb_strtolower(TrackingRequest::normalizeNumber($trackingNumber));

        return 'tracking:etag:'.$this->name().':'.sha1($number);
    }

    /**
     * @return array{etag: string, result: array<string, mixed>}|null
     */
    private function conditionalEntry(string $trackingNumber): ?array
    {
        $entry = Cache::get($this->conditionalKey($trackingNumber));

        if (! is_array($entry) || ! is_string($entry['etag'] ?? null) || $entry['etag'] === '' || ! is_array($entry['result'] ?? null)) {
            return null;
        }

        return $entry;
    }

    /**
     * @return array<string, mixed>
     */
    private function resultToArray(TrackingResult $result): array
    {
        return [
            'status' => $result->status->value,
            'estimated_delivery' => $result->estimatedDelivery?->toIso8601String(),
            'events' => array_map(fn (TrackingEvent $event) => [
                'status' => $event->status->value,
                'description' => $event->description,
                'location' => $event->location,
                'occurred_at' => $event->occurredAt->toIso8601String(),
                'external_id' => $event->externalId,
            ], $result->events),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resultFromArray(array $data): ?TrackingResult
    {
        try {
            $events = [];

            foreach ((array) ($data['events'] ?? []) as $event) {
                $events[] = new TrackingEvent(
                    status: TrackingStatus::fromValue($event['status'] ?? null),
                    description: (string) ($event['description'] ?? ''),
                    location: $event['location'] ?? null,
                    occurredAt: CarbonImmutable::parse((string) $event['occurred_at']),
                    externalId: $event['external_id'] ?? null,
                );
            }

            return new TrackingResult(
                TrackingStatus::fromValue($data['status'] ?? null),
                $events,
                isset($data['estimated_delivery']) ? CarbonImmutable::parse((string) $data['estimated_delivery']) : null,
            );
        } catch (Throwable) {
            return null;
        }
    }
}
