<?php

namespace App\Shipping\Tracking\Drivers;

use App\Shipping\Tracking\Contracts\Authenticator;
use App\Shipping\Tracking\Contracts\RefreshesCredentials;
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
 * transient errors), pluggable authentication (an Authenticator: bearer token, API-key header, OAuth
 * client credentials, ... with one refresh-and-retry on expired credentials), a uniform translation
 * of HTTP failures into the module's exceptions, and helpers for status mapping and UTC dates. It is
 * not registered anywhere and contains nothing carrier specific.
 *
 * Adapters that give a status a meaning of their own (a 404 that means "not scanned yet", a 304 that
 * means "unchanged since the ETag") list those statuses in send()'s $accept argument and get the
 * Response back instead of an exception.
 *
 * Settings come from shipping.tracking.drivers.<name> (base_url, api_key, timeout, retries,
 * retry_sleep_ms). Nothing in this class ever logs a request, a header or a body: exception messages
 * carry the HTTP status only, so credentials cannot leak into logs.
 */
abstract class AbstractHttpProvider implements TrackingProvider
{
    /**
     * @param  array<string, mixed>  $config  shipping.tracking.drivers.<name>
     * @param  Authenticator|null  $authenticator  credentials strategy; null = unauthenticated (or override authenticate())
     */
    public function __construct(
        protected readonly array $config = [],
        protected readonly ?Authenticator $authenticator = null,
    ) {}

    /**
     * Carrier status code (upper-cased, trimmed) => normalised status. Unlisted codes map to Unknown.
     *
     * @return array<string, TrackingStatus>
     */
    abstract protected function statusMap(): array;

    /**
     * Add credentials (Authorization header, API key header, ...) to every request. Delegates to the
     * Authenticator; adapters written before Authenticators existed may still override this instead.
     */
    protected function authenticate(PendingRequest $request): PendingRequest
    {
        return $this->authenticator?->authenticate($request) ?? $request;
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
                // The exception is null for answers that are neither successful nor failed (e.g. 304): never retried.
                fn (?Throwable $e) => $e instanceof ConnectionException
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
     * An adapter that wants to decide for itself lists the statuses it handles in $accept (e.g. [304, 404]):
     * for those the Response is returned untouched. If the Authenticator can refresh its credentials and
     * says the answer means "expired" (401), they are refreshed and the call repeated once.
     *
     * @param  callable(PendingRequest): Response  $call
     * @param  list<int>  $accept  non-2xx statuses the caller handles itself
     */
    protected function send(callable $call, array $accept = []): Response
    {
        $response = $this->dispatch($call);

        if ($this->authenticator instanceof RefreshesCredentials && $this->authenticator->shouldRetryAfter($response)) {
            $this->authenticator->refresh();
            $response = $this->dispatch($call);
        }

        if ($response->successful() || in_array($response->status(), $accept, true)) {
            return $response;
        }

        $status = $response->status();
        $message = $this->name().': HTTP '.$status;

        if (in_array($status, [401, 403, 408, 425, 429], true) || $status >= 500) {
            throw (new TrackingProviderUnavailable($message))->withHttpStatus($status);
        }

        throw (new TrackingRejected($message))->withHttpStatus($status);
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function dispatch(callable $call): Response
    {
        try {
            return $call($this->http());
        } catch (ConnectionException $e) {
            throw new TrackingProviderUnavailable($this->name().': connection failed', 0, $e);
        }
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
