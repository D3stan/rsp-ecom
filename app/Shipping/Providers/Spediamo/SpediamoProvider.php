<?php

namespace App\Shipping\Providers\Spediamo;

use App\Shipping\Auth\BearerTokenAuthenticator;
use App\Shipping\Contracts\Authenticator;
use App\Shipping\Support\CarrierCodeMap;
use App\Shipping\Tracking\Drivers\AbstractHttpProvider;
use App\Shipping\Tracking\Drivers\Concerns\CachesConditionalRequests;
use App\Shipping\Tracking\Drivers\ManualProvider;
use App\Shipping\Tracking\DTO\TrackingRequest;
use App\Shipping\Tracking\DTO\TrackingResult;
use App\Shipping\Tracking\Exceptions\TrackingProviderUnavailable;
use DateTimeZone;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Tracking adapter for the Spediamo shipping platform API (https://api.spediamo.it, docs/spediamo/).
 * Tracking only, by polling: the API has no webhooks, so this is deliberately NOT a PassiveProvider and
 * does not implement SupportsWebhooks.
 *
 *   GET {base_url}/v1/shipments/tracking?code={trackingNumber}
 *       Authorization: Bearer {token}        If-None-Match: {cached ETag}
 *
 *   200  mapped by SpediamoResponseMapper; the ETag and the parsed result are cached
 *   304  unchanged since the ETag: the cached result (nothing new when it was evicted)
 *   404  not scanned yet: TrackingResult::unknown(), polling continues
 *   401/403, 429, 5xx, timeouts: TrackingProviderUnavailable (retried by the job). 401/403 and a missing
 *        token also log one critical line per hour: nothing works until the token is fixed.
 *
 * Everything lives in this folder and in config/shipping.php. Nothing here logs request headers
 * or bodies, and the token never reaches a log line or an exception message.
 */
class SpediamoProvider extends AbstractHttpProvider
{
    use CachesConditionalRequests;

    public const NAME = 'spediamo';

    private const DEFAULT_BASE_URL = 'https://api.spediamo.it';

    private const DEFAULT_TIMEZONE = 'Europe/Rome';

    private readonly SpediamoStatusMap $statuses;

    private readonly CarrierCodeMap $carriers;

    /**
     * @param  array<string, mixed>  $config  shipping.tracking.drivers.spediamo
     */
    public function __construct(array $config = [], ?Authenticator $authenticator = null)
    {
        $config['base_url'] = trim((string) ($config['base_url'] ?? '')) ?: self::DEFAULT_BASE_URL;

        parent::__construct($config, $authenticator ?? new BearerTokenAuthenticator((string) ($config['token'] ?? ''), self::NAME));

        $this->statuses = new SpediamoStatusMap((array) ($config['status_map'] ?? []));
        $this->carriers = CarrierCodeMap::fromDriverConfig($config);
    }

    public function name(): string
    {
        return self::NAME;
    }

    /**
     * An aggregator: with an empty carrier_map every carrier is accepted, otherwise only mapped codes.
     */
    public function supports(string $carrierCode): bool
    {
        return $this->carriers->isEmpty() || $this->carriers->toProvider($carrierCode) !== null;
    }

    public function track(TrackingRequest $request): TrackingResult
    {
        $code = TrackingRequest::normalizeNumber($request->trackingNumber);

        if (trim((string) ($this->config['token'] ?? '')) === '') {
            $this->alertCredentials('the token is not configured');

            throw new TrackingProviderUnavailable(self::NAME.': API token is not configured');
        }

        try {
            $response = $this->send(
                fn (PendingRequest $http) => $http->withHeaders($this->conditionalHeaders($code))
                    ->get('/v1/shipments/tracking', ['code' => $code]),
                [304, 404],
            );
        } catch (TrackingProviderUnavailable $e) {
            if (in_array($e->httpStatus, [401, 403], true)) {
                $this->alertCredentials('HTTP '.$e->httpStatus);
            }

            throw $e;
        }

        if ($response->status() === 304) {
            return $this->cachedResult($code) ?? TrackingResult::unknown();
        }

        if ($response->status() === 404) {
            return TrackingResult::unknown(); // shipment not scanned yet
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw (new TrackingProviderUnavailable(self::NAME.': unexpected response'))->withHttpStatus($response->status());
        }

        $result = $this->mapper()->map($data);

        $this->rememberResult($code, $response, $result);

        return $result;
    }

    /**
     * The API publishes no public tracking page: only a template configured in shipping.carriers produces a link.
     */
    public function trackingUrl(string $trackingNumber, ?string $carrierCode): ?string
    {
        return (new ManualProvider)->trackingUrl($trackingNumber, $carrierCode);
    }

    protected function statusMap(): array
    {
        return $this->statuses->all();
    }

    private function mapper(): SpediamoResponseMapper
    {
        $timezone = (string) ($this->config['timezone'] ?? self::DEFAULT_TIMEZONE);

        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            $timezone = self::DEFAULT_TIMEZONE;
        }

        return new SpediamoResponseMapper($this->statuses, $timezone, fn (string $value) => $this->warnUnmapped($value));
    }

    /**
     * An API status we do not know (the tracking enum is not documented): visible in the log, once per value.
     */
    private function warnUnmapped(string $value): void
    {
        $value = mb_substr($value, 0, 64);

        if (Cache::add('tracking:'.self::NAME.':unmapped:'.sha1(SpediamoStatusMap::normalize($value)), 1, now()->addDays(7))) {
            Log::warning('Spediamo returned a tracking status that is not mapped, treated as unknown. Add it to shipping.tracking.drivers.spediamo.status_map', [
                'provider' => self::NAME,
                'status' => $value,
            ]);
        }
    }

    /**
     * The token is missing, expired or wrong: every poll fails until someone fixes it, so shout, but once an hour.
     */
    private function alertCredentials(string $reason): void
    {
        if (Cache::add('tracking:'.self::NAME.':credentials-alert', 1, now()->addHour())) {
            Log::critical('Spediamo tracking cannot authenticate ('.$reason.'): the API token is missing, expired or wrong. Check SHIPPING_TRACKING_SPEDIAMO_TOKEN; shipments are not being tracked.', [
                'provider' => self::NAME,
            ]);
        }
    }
}
