<?php

namespace App\Shipping\Providers\Spediamo;

use App\Shipping\Auth\BearerTokenAuthenticator;
use App\Shipping\Exceptions\ShippingCredentialsException;
use App\Shipping\Labels\Contracts\CancelsShipments;
use App\Shipping\Labels\Contracts\ConfirmsPayment;
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
 * Label adapter for the Spediamo platform API (docs/vendor/spediamo/shipments.md, waybills.md).
 *
 *   POST {base_url}/v1/shipments            create the shipment  -> id, trackingNumber, courier, status
 *   GET  {base_url}/v1/waybills/{id}/pdf    download the label PDF
 *       Authorization: Bearer {token}
 *
 * The API cannot pay a draft: createShipment() returns it flagged awaitingPayment (the owner pays it on Spediamo with
 * their prepaid balance) and confirmPurchase() later reads GET /v1/shipments/{id} to see whether it has been bought.
 * Statuses (Spediamo docs): draft = not bought, booked = bought, ready = scheduled for pickup, current = on its way,
 * on_hold, underpaid, failed. The response of a PAID shipment has not been recorded from the live API yet.
 *
 * It reuses the shared bearer authenticator and the SAME credentials
 * (shipping.tracking.drivers.spediamo via config('shipping.labels.drivers.spediamo')): no duplicate env var.
 * Failures become the label layer's own exceptions: 400/404/422 -> LabelRejected (shown to the admin);
 * 401/403/408/429/5xx, timeouts and a missing token -> LabelProviderUnavailable (retry). Nothing here logs the token,
 * headers or bodies: exception messages carry the HTTP status only.
 */
class SpediamoLabelProvider implements CancelsShipments, ConfirmsPayment, LabelProvider
{
    public const NAME = 'spediamo';

    private const DEFAULT_BASE_URL = 'https://api.spediamo.it';

    /** Statuses of a shipment that has been bought (anything else, including an unknown status, counts as unpaid). */
    private const PAID_STATUSES = ['BOOKED', 'READY', 'CURRENT', 'ON_HOLD'];

    private readonly string $baseUrl;

    private readonly string $token;

    private readonly int $timeout;

    private readonly CarrierCodeMap $carriers;

    private readonly SpediamoLabelRequestMapper $mapper;

    /**
     * @param  array<string, mixed>  $config  shipping.labels.drivers.spediamo (reuses the tracking token/base_url)
     */
    public function __construct(private readonly array $config = [])
    {
        $this->baseUrl = trim((string) ($config['base_url'] ?? '')) ?: self::DEFAULT_BASE_URL;
        $this->token = (string) ($config['token'] ?? '');
        $this->timeout = max(1, (int) ($config['timeout'] ?? 15));
        $this->carriers = CarrierCodeMap::fromDriverConfig($config);
        $this->mapper = new SpediamoLabelRequestMapper(
            (array) ($config['default_parcel'] ?? []),
            $this->carriers,
            $config,
        );
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
        if (trim((string) $request->sender->postalCode) === '' || trim((string) $request->sender->city) === '') {
            throw new LabelRejected('Spediamo: Sender address requires at least postal code and city (configure shipping.sender_postal_code and shipping.sender_city in settings or .env).');
        }

        if (trim((string) $request->recipient->postalCode) === '' || trim((string) $request->recipient->city) === '') {
            throw new LabelRejected('Spediamo: Recipient address requires at least postal code and city.');
        }

        $data = null;
        $quoteToken = isset($request->quoteToken) && $request->quoteToken !== null ? trim($request->quoteToken) : '';

        if ($quoteToken !== '') {
            try {
                $response = $this->send(fn (PendingRequest $http) => $http->get("/v1/shipments/{$quoteToken}"), method: 'get');
                if ($response->successful()) {
                    $shipmentData = $response->json();
                    if (is_array($shipmentData)) {
                        $draftId = trim((string) ($shipmentData['id'] ?? $shipmentData['shipmentId'] ?? ''));
                        $status = strtoupper(trim((string) ($shipmentData['status'] ?? '')));
                        if ($draftId !== '' && ($status === '' || $status === 'DRAFT')) {
                            $data = $shipmentData;
                        }
                    }
                }
            } catch (Throwable) {
                $data = null;
            }
        }

        if ($data === null) {
            $response = $this->send(fn (PendingRequest $http) => $http->asForm()->post('/v1/shipments', $this->mapper->map($request)), method: 'post');
            $data = $response->json();
        }

        if (! is_array($data)) {
            throw (new LabelProviderUnavailable(self::NAME.': unexpected response'))->withHttpStatus(isset($response) ? $response->status() : null);
        }

        $id = trim((string) ($data['id'] ?? $data['shipmentId'] ?? ''));
        $tracking = trim((string) ($data['trackingNumber'] ?? $data['code'] ?? $data['tracking_code'] ?? ''));
        $status = strtoupper(trim((string) ($data['status'] ?? '')));

        if ($tracking === '' && $id !== '' && $status === 'DRAFT') {
            $tracking = $id;
        }

        if ($id === '' && $tracking === '') {
            throw new LabelRejected(self::NAME.': the shipment has no tracking number yet.');
        }

        if ($tracking === '') {
            throw new LabelRejected(self::NAME.': the shipment has no tracking number yet.');
        }

        if ($id === '') {
            $id = $tracking;
        }

        $raw = array_intersect_key($data, array_flip(['id', 'shipmentId', 'trackingNumber', 'code', 'tracking_code', 'courier', 'status', 'waybillAvailable']));
        $awaitingPayment = $status === 'DRAFT';

        if ($awaitingPayment && ($this->config['purchase_url'] ?? '') !== '') {
            $raw['pay_url'] = (string) $this->config['purchase_url'];
        }

        return new ShipmentResult(
            externalId: $id,
            trackingNumber: $tracking,
            carrierCode: $request->carrierCode,
            raw: $raw,
            awaitingPayment: $awaitingPayment,
        );
    }

    public function confirmPurchase(string $externalId): ?ShipmentResult
    {
        $data = $this->send(fn (PendingRequest $http) => $http->get("/v1/shipments/{$externalId}"), method: 'get')->json();

        if (! is_array($data)) {
            throw new LabelProviderUnavailable(self::NAME.': unexpected response');
        }

        $status = strtoupper(trim((string) ($data['status'] ?? '')));

        if (! in_array($status, self::PAID_STATUSES, true)) {
            return null;
        }

        $tracking = trim((string) ($data['trackingNumber'] ?? $data['code'] ?? ''));

        return new ShipmentResult(
            externalId: $externalId,
            trackingNumber: $tracking !== '' ? $tracking : $externalId,
            carrierCode: '',
            raw: array_intersect_key($data, array_flip(['id', 'trackingNumber', 'code', 'courier', 'status', 'waybillAvailable'])),
        );
    }

    public function label(ShipmentResult|string $externalId): LabelDocument
    {
        $id = $externalId instanceof ShipmentResult ? $externalId->externalId : $externalId;

        $response = $this->send(
            fn (PendingRequest $http) => $http->get("/v1/waybills/{$id}/pdf", ['download' => 'true']),
            acceptPdf: true,
            method: 'get',
        );

        $contents = $response->body();
        $pdf = $this->extractPdf($contents, $response);

        if ($pdf === null || ! str_starts_with($pdf, '%PDF')) {
            throw (new LabelProviderUnavailable('Spediamo returned an invalid PDF label.'))->withHttpStatus($response->status());
        }

        $courierTrackingNumber = null;
        if ($externalId instanceof ShipmentResult) {
            try {
                $shipmentRes = $this->send(fn (PendingRequest $http) => $http->get("/v1/shipments/{$id}"), method: 'get');
                if ($shipmentRes->successful() && is_array($sData = $shipmentRes->json())) {
                    $trk = trim((string) ($sData['trackingNumber'] ?? $sData['tracking_code'] ?? ''));
                    $code = trim((string) ($sData['code'] ?? ''));
                    if ($trk !== '' && strcasecmp($trk, $code) !== 0 && strcasecmp($trk, $id) !== 0) {
                        $courierTrackingNumber = $trk;
                    }
                }
            } catch (Throwable) {
                // Ignore lookup errors so label creation never fails
            }
        }

        return new LabelDocument($pdf, 'label-'.$id.'.pdf', trackingNumber: $courierTrackingNumber);
    }

    public function cancelShipment(string $externalId): void
    {
        $this->send(fn (PendingRequest $http) => $http->delete("/v1/shipments/{$externalId}"), method: 'delete');
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(callable $call, bool $acceptPdf = false, string $method = 'get'): Response
    {
        try {
            $pending = Http::baseUrl($this->baseUrl)->timeout($this->timeout)->connectTimeout(5);
            if ($acceptPdf) {
                $pending = $pending->accept('application/pdf, application/json');
            } else {
                $pending = $pending->acceptJson();
            }

            $http = (new BearerTokenAuthenticator($this->token, self::NAME))->authenticate($pending);

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

        if (strtolower($method) === 'delete' && $status === 403) {
            $carrierMsg = $this->carrierMessage($response);
            $body = $response->body();
            if ($carrierMsg !== null || str_contains(strtolower($body), 'draft') || str_contains(strtolower($body), 'status')) {
                $msg = $this->errorMessage($response, 'Shipment cannot be cancelled because it is no longer in DRAFT status.');
                throw (new LabelRejected($msg))->withHttpStatus(403);
            }
        }

        if (in_array($status, [401, 403, 408, 425, 429], true) || $status >= 500) {
            throw (new LabelProviderUnavailable(self::NAME.': HTTP '.$status))->withHttpStatus($status);
        }

        throw (new LabelRejected($this->errorMessage($response)))->withHttpStatus($status);
    }

    private function extractPdf(string $contents, Response $response): ?string
    {
        if (str_starts_with($contents, '%PDF')) {
            return $contents;
        }

        $trimmed = trim($contents);
        if ($trimmed !== '') {
            $decoded = base64_decode($trimmed, true);
            if ($decoded !== false && str_starts_with($decoded, '%PDF')) {
                return $decoded;
            }
        }

        $data = null;
        try {
            $data = $response->json();
        } catch (Throwable) {
            // ignore
        }

        if (! is_array($data)) {
            $decodedJson = json_decode($contents, true);
            if (is_array($decodedJson)) {
                $data = $decodedJson;
            }
        }

        if (is_array($data)) {
            $candidate = $data['data'] ?? $data['content'] ?? $data['pdf'] ?? $data['file'] ?? null;
            if (is_string($candidate)) {
                if (str_starts_with($candidate, '%PDF')) {
                    return $candidate;
                }
                $decoded = base64_decode(trim($candidate), true);
                if ($decoded !== false && str_starts_with($decoded, '%PDF')) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    private function errorMessage(Response $response, string $default = ''): string
    {
        $message = $this->carrierMessage($response);

        if ($message !== null && $message !== '') {
            return $message;
        }

        return $default !== '' ? $default : self::NAME.': HTTP '.$response->status();
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

        if (! is_array($body)) {
            return null;
        }

        $message = $body['message'] ?? $body['error'] ?? $body['description'] ?? null;

        return is_string($message) && trim($message) !== '' ? trim($message) : null;
    }
}
