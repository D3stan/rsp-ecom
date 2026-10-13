<?php

namespace App\Shipping\Tracking\Drivers;

use App\Shipping\Tracking\Contracts\TrackingProvider;
use App\Shipping\Tracking\Enums\TrackingStatus;
use App\Shipping\Tracking\Exceptions\TrackingProviderUnavailable;
use App\Shipping\Tracking\Exceptions\TrackingRejected;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Optional base class for adapters that talk to a carrier's HTTP API.
 *
 * Provides what every such adapter needs: a pre-configured Http client (base URL, timeout, retry on
 * transient errors), one place to add authentication, a uniform translation of HTTP failures into
 * the module's exceptions, and helpers for status mapping and UTC dates. It is not registered
 * anywhere and contains nothing carrier specific.
 *
 * Settings come from shipping.tracking.drivers.<name> (base_url, api_key, timeout, retries,
 * retry_sleep_ms). Nothing in this class ever logs a request, a header or a body: exception messages
 * carry the HTTP status only, so credentials cannot leak into logs.
 */
abstract class AbstractHttpProvider implements TrackingProvider
{
    /**
     * @param  array<string, mixed>  $config  shipping.tracking.drivers.<name>
     */
    public function __construct(protected readonly array $config = []) {}

    /**
     * Carrier status code (upper-cased, trimmed) => normalised status. Unlisted codes map to Unknown.
     *
     * @return array<string, TrackingStatus>
     */
    abstract protected function statusMap(): array;

    /**
     * Add credentials (Authorization header, API key header, ...) to every request.
     */
    protected function authenticate(PendingRequest $request): PendingRequest
    {
        return $request;
    }

    protected function http(): PendingRequest
    {
        $retries = max(0, (int) ($this->config['retries'] ?? 1));

        $request = Http::baseUrl((string) ($this->config['base_url'] ?? ''))
            ->acceptJson()
            ->timeout(max(1, (int) ($this->config['timeout'] ?? 10)))
            ->connectTimeout(5)
            ->retry(
                $retries + 1,
                max(0, (int) ($this->config['retry_sleep_ms'] ?? 200)),
                fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()),
                throw: false,
            );

        return $this->authenticate($request);
    }

    /**
     * Run a request and turn every failure into a Tracking exception.
     *
     * 401/403, 408, 429 and 5xx are the carrier's or our configuration's problem, not the shipment's:
     * TrackingProviderUnavailable (retried later). Any other 4xx means this request is wrong:
     * TrackingRejected (a 404 carries httpStatus 404 so adapters can treat "not scanned yet" specially).
     *
     * @param  callable(PendingRequest): Response  $call
     */
    protected function send(callable $call): Response
    {
        try {
            $response = $call($this->http());
        } catch (ConnectionException $e) {
            throw new TrackingProviderUnavailable($this->name().': connection failed', 0, $e);
        }

        if ($response->successful()) {
            return $response;
        }

        $status = $response->status();
        $message = $this->name().': HTTP '.$status;

        if (in_array($status, [401, 403, 408, 425, 429], true) || $status >= 500) {
            throw (new TrackingProviderUnavailable($message))->withHttpStatus($status);
        }

        throw (new TrackingRejected($message))->withHttpStatus($status);
    }

    protected function mapStatus(?string $carrierStatus): TrackingStatus
    {
        $key = strtoupper(trim((string) $carrierStatus));

        return $this->statusMap()[$key] ?? TrackingStatus::Unknown;
    }

    /**
     * Parse a carrier date/time that is expressed in the carrier's local timezone (or carries its own offset).
     */
    protected function toUtc(string $dateTime, string $timezone = 'UTC'): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, $timezone)->utc();
    }
}
