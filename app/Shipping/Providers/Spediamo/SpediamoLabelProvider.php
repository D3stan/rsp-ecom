<?php

namespace App\Shipping\Providers\Spediamo;

use App\Shipping\Auth\BearerTokenAuthenticator;
use App\Shipping\Exceptions\ShippingCredentialsException;
use App\Shipping\Labels\Contracts\CancelsShipments;
use App\Shipping\Labels\Contracts\LabelProvider;
use App\Shipping\Labels\DTO\LabelDocument;
use App\Shipping\Labels\DTO\ShipmentRequest;
use App\Shipping\Labels\DTO\ShipmentResult;
use App\Shipping\Labels\Exceptions\LabelProviderUnavailable;
use App\Shipping\Labels\Exceptions\LabelRejected;
use App\Shipping\Support\CarrierCodeMap;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Label adapter for the Spediamo platform API (docs/spediamo/shipments.md, waybills.md).
 *
 *   POST {base_url}/v1/shipments            create the shipment  -> id, trackingNumber, courier, status
 *   GET  {base_url}/v1/waybills/{id}/pdf    download the label PDF
 *       Authorization: Bearer {token}
 *
 * It reuses the shared bearer authenticator and the SAME credentials
 * (shipping.tracking.drivers.spediamo via config('shipping.labels.drivers.spediamo')): no duplicate env var.
 * Failures become the label layer's own exceptions: 400/404/422 -> LabelRejected (shown to the admin);
 * 401/403/408/429/5xx, timeouts and a missing token -> LabelProviderUnavailable (retry). Nothing here logs the token,
 * headers or bodies: exception messages carry the HTTP status only.
 */
class SpediamoLabelProvider implements CancelsShipments, LabelProvider
{
    public const NAME = 'spediamo';

    private const DEFAULT_BASE_URL = 'https://api.spediamo.it';

    private readonly string $baseUrl;

    private readonly string $token;

    private readonly int $timeout;

    private readonly CarrierCodeMap $carriers;

    private readonly SpediamoLabelRequestMapper $mapper;

    /**
     * @param  array<string, mixed>  $config  shipping.labels.drivers.spediamo (reuses the tracking token/base_url)
     */
    public function __construct(array $config = [])
    {
        $this->baseUrl = trim((string) ($config['base_url'] ?? '')) ?: self::DEFAULT_BASE_URL;
        $this->token = (string) ($config['token'] ?? '');
        $this->timeout = max(1, (int) ($config['timeout'] ?? 15));
        $this->carriers = CarrierCodeMap::fromDriverConfig($config);
        $this->mapper = new SpediamoLabelRequestMapper;
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function supports(string $carrierCode): bool
    {
        return $this->carriers->isEmpty() || $this->carriers->toProvider($carrierCode) !== null;
    }

    public function createShipment(ShipmentRequest $request): ShipmentResult
    {
        $response = $this->send(fn (PendingRequest $http) => $http->post('/v1/shipments', $this->mapper->map($request)));

        $data = $response->json();

        if (! is_array($data) || ! isset($data['id'])) {
            throw (new LabelProviderUnavailable(self::NAME.': unexpected response'))->withHttpStatus($response->status());
        }

        $tracking = trim((string) ($data['trackingNumber'] ?? ''));

        if ($tracking === '') {
            // created but not yet assigned a tracking number: treat as a carrier rejection so nothing is shipped blind
            throw new LabelRejected(self::NAME.': the shipment has no tracking number yet.');
        }

        return new ShipmentResult(
            externalId: (string) $data['id'],
            trackingNumber: $tracking,
            carrierCode: $request->carrierCode,
            raw: array_intersect_key($data, array_flip(['id', 'trackingNumber', 'courier', 'status', 'waybillAvailable'])),
        );
    }

    public function label(ShipmentResult|string $externalId): LabelDocument
    {
        $id = $externalId instanceof ShipmentResult ? $externalId->externalId : $externalId;

        $response = $this->send(fn (PendingRequest $http) => $http->get("/v1/waybills/{$id}/pdf", ['download' => 'true']));

        $contents = $response->body();

        if (! str_starts_with($contents, '%PDF')) {
            throw (new LabelProviderUnavailable(self::NAME.': waybill is not a PDF'))->withHttpStatus($response->status());
        }

        return new LabelDocument($contents, 'label-'.$id.'.pdf');
    }

    public function cancelShipment(string $externalId): void
    {
        $this->send(fn (PendingRequest $http) => $http->delete("/v1/shipments/{$externalId}"));
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(callable $call): Response
    {
        try {
            $http = (new BearerTokenAuthenticator($this->token, self::NAME))->authenticate(
                Http::baseUrl($this->baseUrl)->acceptJson()->timeout($this->timeout)->connectTimeout(5),
            );

            $response = $call($http);
        } catch (ShippingCredentialsException $e) {
            throw (new LabelProviderUnavailable($e->getMessage(), 0, $e))->withHttpStatus($e->httpStatus);
        } catch (ConnectionException $e) {
            throw new LabelProviderUnavailable(self::NAME.': connection failed', 0, $e);
        }

        if ($response->successful()) {
            return $response;
        }

        $status = $response->status();

        if (in_array($status, [401, 403, 408, 425, 429], true) || $status >= 500) {
            throw (new LabelProviderUnavailable(self::NAME.': HTTP '.$status))->withHttpStatus($status);
        }

        throw (new LabelRejected($this->carrierMessage($response) ?? self::NAME.': HTTP '.$status))->withHttpStatus($status);
    }

    /**
     * The carrier's own validation message, when it sends one (safe to show: no secrets).
     */
    private function carrierMessage(Response $response): ?string
    {
        try {
            $body = $response->json();
        } catch (Throwable) {
            return null;
        }

        $message = is_array($body) ? ($body['message'] ?? $body['error'] ?? null) : null;

        return is_string($message) && trim($message) !== '' ? trim($message) : null;
    }
}
