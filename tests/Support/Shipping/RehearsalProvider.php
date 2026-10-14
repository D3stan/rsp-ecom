<?php

namespace Tests\Support\Shipping;

use App\Shipping\Tracking\Auth\ApiKeyHeaderAuthenticator;
use App\Shipping\Tracking\Drivers\AbstractHttpProvider;
use App\Shipping\Tracking\DTO\TrackingEvent;
use App\Shipping\Tracking\DTO\TrackingRequest;
use App\Shipping\Tracking\DTO\TrackingResult;
use App\Shipping\Tracking\Enums\TrackingStatus;
use App\Shipping\Tracking\Exceptions\TrackingProviderUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;

/**
 * A second, fictitious adapter used only to rehearse swapping the carrier API. It differs from the
 * real one on every axis an adapter may differ on: API-key header instead of a bearer token, a path
 * parameter instead of a query string, another status vocabulary and "d/m/Y H:i" dates in UTC.
 *
 *   GET {base_url}/parcels/{number}      X-Api-Key: {api_key}
 *   {"state": "CLOSED", "scans": [{"at": "01/09/2026 14:30", "what": "...", "where": "...", "state": "MOVING"}]}
 *
 * Registered by the test with TrackingManager::extend('rehearsal', ...); the core is never touched.
 */
class RehearsalProvider extends AbstractHttpProvider
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config, new ApiKeyHeaderAuthenticator((string) ($config['api_key'] ?? ''), 'X-Api-Key', 'rehearsal'));
    }

    public function name(): string
    {
        return 'rehearsal';
    }

    public function supports(string $carrierCode): bool
    {
        return true;
    }

    public function trackingUrl(string $trackingNumber, ?string $carrierCode): ?string
    {
        return null;
    }

    protected function statusMap(): array
    {
        return [
            'ACCEPTED' => TrackingStatus::InfoReceived,
            'MOVING' => TrackingStatus::InTransit,
            'DELAYED' => TrackingStatus::Exception,
            'CLOSED' => TrackingStatus::Delivered,
        ];
    }

    public function track(TrackingRequest $request): TrackingResult
    {
        $response = $this->send(
            fn (PendingRequest $http) => $http->get('/parcels/'.rawurlencode($request->trackingNumber)),
            [404],
        );

        if ($response->status() === 404) {
            return TrackingResult::unknown();
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new TrackingProviderUnavailable('rehearsal: unexpected response');
        }

        $events = [];

        foreach ((array) ($data['scans'] ?? []) as $scan) {
            $events[] = new TrackingEvent(
                status: $this->mapStatus($scan['state'] ?? null),
                description: (string) ($scan['what'] ?? ''),
                location: $scan['where'] ?? null,
                occurredAt: CarbonImmutable::createFromFormat('d/m/Y H:i', (string) $scan['at'], 'UTC'),
            );
        }

        return new TrackingResult($this->mapStatus($data['state'] ?? null), $events);
    }
}
