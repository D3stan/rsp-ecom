<?php

namespace App\Shipping\Tracking\Drivers;

use App\Shipping\Tracking\Contracts\SupportsWebhooks;
use App\Shipping\Tracking\Contracts\TrackingProvider;
use App\Shipping\Tracking\Drivers\Concerns\VerifiesHmacSignature;
use App\Shipping\Tracking\DTO\TrackingEvent;
use App\Shipping\Tracking\DTO\TrackingRequest;
use App\Shipping\Tracking\DTO\TrackingResult;
use App\Shipping\Tracking\DTO\TrackingUpdate;
use App\Shipping\Tracking\Enums\TrackingStatus;
use App\Shipping\Tracking\Exceptions\TrackingProviderUnavailable;
use App\Shipping\Tracking\Exceptions\TrackingRejected;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Throwable;

class FakeProvider implements SupportsWebhooks, TrackingProvider
{
    use VerifiesHmacSignature;

    public const SIGNATURE_HEADER = 'X-Fake-Signature';

    public array $unsupported = [];

    /** @var list<TrackingRequest> every track() call, in order */
    public array $requests = [];

    /** @var array<string, list<TrackingResult|Throwable>> */
    private array $scripts = [];

    /**
     * @param  array<string, mixed>  $config  shipping.tracking.drivers.fake
     */
    public function __construct(private readonly array $config = []) {}

    public function script(string $trackingNumber, TrackingResult|Throwable ...$outcomes): static
    {
        $this->scripts[$this->key($trackingNumber)] = array_values($outcomes);

        return $this;
    }

    public function name(): string
    {
        return 'fake';
    }

    public function supports(string $carrierCode): bool
    {
        return ! in_array($carrierCode, $this->unsupported, true);
    }

    public function track(TrackingRequest $request): TrackingResult
    {
        $this->requests[] = $request;

        $key = $this->key($request->trackingNumber);

        if (isset($this->scripts[$key])) {
            $outcome = count($this->scripts[$key]) > 1 ? array_shift($this->scripts[$key]) : $this->scripts[$key][0];

            return $outcome instanceof Throwable ? throw $outcome : $outcome;
        }

        return $this->scenario($request->trackingNumber);
    }

    public function trackingUrl(string $trackingNumber, ?string $carrierCode): ?string
    {
        $number = trim($trackingNumber);

        if ($number === '' || ($carrierCode !== null && ! $this->supports($carrierCode))) {
            return null;
        }

        return 'https://tracking.example.test/track/'.rawurlencode($number);
    }

    public function verifySignature(Request $request): bool
    {
        return $this->hmacMatches($request, (string) ($this->config['webhook_secret'] ?? ''), self::SIGNATURE_HEADER);
    }

    public function parse(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload) || ! isset($payload['updates']) || ! is_array($payload['updates'])) {
            throw new TrackingRejected('Malformed fake webhook payload');
        }

        $updates = [];

        foreach (array_slice(array_values($payload['updates']), 0, 100) as $update) {
            $number = is_array($update) ? ($update['tracking_number'] ?? null) : null;

            if (! is_string($number) || trim($number) === '') {
                throw new TrackingRejected('Webhook update without tracking_number');
            }

            $events = [];

            foreach ((array) ($update['events'] ?? []) as $event) {
                $events[] = $this->parseEvent($event);
            }

            $status = isset($update['status'])
                ? TrackingStatus::fromValue((string) $update['status'])
                : $this->strongest($events);

            $carrier = isset($update['carrier']) && is_string($update['carrier']) ? $update['carrier'] : null;

            $updates[] = new TrackingUpdate($number, $carrier, new TrackingResult($status, $events));
        }

        return $updates;
    }

    private function parseEvent(mixed $event): TrackingEvent
    {
        if (! is_array($event) || ! isset($event['occurred_at'])) {
            throw new TrackingRejected('Webhook event without occurred_at');
        }

        try {
            $occurredAt = CarbonImmutable::parse((string) $event['occurred_at']);
        } catch (Throwable) {
            throw new TrackingRejected('Webhook event with an invalid occurred_at');
        }

        return new TrackingEvent(
            status: TrackingStatus::fromValue((string) ($event['status'] ?? '')),
            description: (string) ($event['description'] ?? ''),
            location: isset($event['location']) ? (string) $event['location'] : null,
            occurredAt: $occurredAt,
            externalId: isset($event['id']) ? (string) $event['id'] : null,
        );
    }

    /**
     * @param  list<TrackingEvent>  $events
     */
    private function strongest(array $events): TrackingStatus
    {
        $best = TrackingStatus::Unknown;

        foreach ($events as $event) {
            if ($event->status->rank() >= $best->rank()) {
                $best = $event->status;
            }
        }

        return $best;
    }

    private function scenario(string $trackingNumber): TrackingResult
    {
        $upper = strtoupper(TrackingRequest::normalizeNumber($trackingNumber));

        $steps = match (true) {
            str_ends_with($upper, 'UNAVAILABLE') => throw new TrackingProviderUnavailable('Fake carrier is down'),
            str_ends_with($upper, 'REJECTED') => throw new TrackingRejected('Fake carrier does not know this shipment'),
            str_ends_with($upper, 'DELIVERED') => [TrackingStatus::InfoReceived, TrackingStatus::InTransit, TrackingStatus::Delivered],
            str_ends_with($upper, 'OUTFORDEL') => [TrackingStatus::InfoReceived, TrackingStatus::InTransit, TrackingStatus::OutForDelivery],
            str_ends_with($upper, 'TRANSIT') => [TrackingStatus::InfoReceived, TrackingStatus::InTransit],
            str_ends_with($upper, 'EXCEPTION') => [TrackingStatus::InfoReceived, TrackingStatus::InTransit, TrackingStatus::Exception],
            default => [TrackingStatus::InfoReceived],
        };

        $now = CarbonImmutable::now();
        $events = [];

        foreach ($steps as $i => $status) {
            $events[] = new TrackingEvent(
                status: $status,
                description: 'Fake: '.$status->value,
                location: 'Fake Hub',
                occurredAt: $now->subHours((count($steps) - $i) * 6),
                externalId: 'fake-'.$this->key($trackingNumber).'-'.$i,
            );
        }

        return new TrackingResult(end($steps), $events);
    }

    private function key(string $trackingNumber): string
    {
        return mb_strtolower(TrackingRequest::normalizeNumber($trackingNumber));
    }
}
