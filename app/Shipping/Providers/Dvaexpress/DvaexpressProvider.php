<?php

namespace App\Shipping\Providers\Dvaexpress;

use App\Shipping\Support\CarrierCodeMap;
use App\Shipping\Tracking\Contracts\TrackingProvider;
use App\Shipping\Tracking\DTO\TrackingRequest;
use App\Shipping\Tracking\DTO\TrackingResult;
use App\Shipping\Tracking\Exceptions\TrackingRejected;
use Illuminate\Http\Request;

class DvaexpressProvider implements TrackingProvider
{
    use ResolvesCarrierTrackingUrl;

    public const NAME = 'dvaexpress';

    private readonly DvaexpressClient $client;

    private readonly DvaexpressStatusMap $statuses;

    private readonly DvaexpressResponseMapper $mapper;

    private readonly CarrierCodeMap $carriers;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config = [],
        ?DvaexpressClient $client = null,
    ) {
        $this->client = $client ?? new DvaexpressClient($this->config);
        $this->statuses = new DvaexpressStatusMap((array) ($this->config['status_map'] ?? []));
        $this->mapper = new DvaexpressResponseMapper($this->statuses, (string) ($this->config['timezone'] ?? 'Europe/Rome'));
        $this->carriers = CarrierCodeMap::fromDriverConfig($this->config);
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function supports(string $carrierCode): bool
    {
        if (method_exists($this->carriers, 'supports')) {
            return $this->carriers->supports($carrierCode);
        }

        return $this->carriers->isEmpty() || $this->carriers->toProvider($carrierCode) !== null;
    }

    public function fetch(string $trackingNumber, ?string $carrierCode = null): TrackingResult
    {
        $trackingNumber = trim($trackingNumber);

        if ($trackingNumber === '') {
            throw new TrackingRejected('Tracking number cannot be empty.');
        }

        $response = $this->client->postTracking('tracking', [
            'call' => 'tracking',
            'dettagli' => [
                'ldv' => $trackingNumber,
            ],
        ]);

        return $this->mapper->map($response);
    }

    public function track(TrackingRequest $request): TrackingResult
    {
        return $this->fetch($request->trackingNumber, $request->carrierCode);
    }

    public function parseWebhook(Request $request): TrackingResult
    {
        $secret = (string) ($this->config['webhook_secret'] ?? '');
        $headerSecret = (string) $request->header('X-Webhook-Secret', $request->header('X-API-KEY', ''));

        if ($secret !== '' && ! hash_equals($secret, $headerSecret)) {
            throw new TrackingRejected('Invalid webhook secret.');
        }

        $payload = $request->json()->all();
        if (! is_array($payload) || ! isset($payload['dettagli'])) {
            throw new TrackingRejected('Dvaexpress webhook parsing is not supported.');
        }

        return $this->mapper->map($payload);
    }

    public function trackingUrl(string $trackingNumber, ?string $carrierCode = null): ?string
    {
        return $this->carrierTrackingUrl($trackingNumber, $carrierCode);
    }
}
