<?php

namespace App\Shipping\Providers\Spediamo;

use App\Shipping\Contracts\Authenticator;
use App\Shipping\Exceptions\ShippingCredentialsException;
use App\Shipping\Rates\Contracts\RateProvider;
use App\Shipping\Rates\DTO\RateQuote;
use App\Shipping\Rates\DTO\RateRequest;
use App\Shipping\Rates\Exceptions\RateProviderUnavailable;
use App\Shipping\Rates\Exceptions\RateRejected;
use App\Shipping\Support\CarrierCodeMap;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class SpediamoRateProvider implements RateProvider
{
    public const NAME = 'spediamo';

    private const DEFAULT_BASE_URL = 'https://api.spediamo.it';

    /** Fields a draft reports as missing until it is booked, irrelevant for a quote. */
    private const BOOKING_FIELDS = ['requestedPickupDate', 'requestedPickupTime'];

    private readonly string $baseUrl;

    private readonly int $timeout;

    private readonly CarrierCodeMap $carriers;

    public function __construct(
        private readonly array $config,
        private readonly Authenticator $authenticator,
        private readonly ?SpediamoLabelRequestMapper $mapper = null,
    ) {
        $this->baseUrl = trim((string) ($config['base_url'] ?? '')) ?: self::DEFAULT_BASE_URL;
        $this->timeout = max(1, (int) ($config['timeout'] ?? 15));
        $this->carriers = CarrierCodeMap::fromDriverConfig($config);
    }

    public function name(): string
    {
        return self::NAME;
    }

    /**
     * @return list<RateQuote>
     */
    public function quote(RateRequest $request): array
    {
        $payload = $this->buildPayload($request);

        $cleanup = (bool) ($this->config['cleanup_quote_drafts'] ?? true);
        $shipmentId = null;

        try {
            $response = $this->send(fn (PendingRequest $http) => $http->asForm()->post('/v1/shipments', $payload));
            $data = $response->json();

            if (! is_array($data)) {
                $e = new RateProviderUnavailable(self::NAME.': unexpected response');
                if (method_exists($e, 'withHttpStatus')) {
                    $e->withHttpStatus($response->status());
                }
                throw $e;
            }

            $shipmentId = trim((string) ($data['id'] ?? ''));

            $errors = $this->blockingErrors($data['errors'] ?? null);

            if ($errors !== []) {
                $errorMsg = $this->formatErrors($errors);
                $e = (new RateRejected(self::NAME.': '.$errorMsg))->withIssues(SpediamoIssueMapper::fromErrors($errors));
                if (method_exists($e, 'withHttpStatus')) {
                    $e->withHttpStatus($response->status());
                }
                throw $e;
            }

            $quote = $this->mapQuote($data, $shipmentId, $request->carrierCode);
            $quotes = [$quote];

            if ($request->carrierCode !== null && trim($request->carrierCode) !== '') {
                $quotes = array_values(array_filter(
                    $quotes,
                    fn (RateQuote $q) => $this->carriers->areSameCourier($q->carrierCode, $request->carrierCode),
                ));
            }

            return $quotes;
        } finally {
            if ($cleanup && $shipmentId !== null && $shipmentId !== '') {
                try {
                    $this->send(fn (PendingRequest $http) => $http->delete("/v1/shipments/{$shipmentId}"));
                } catch (Throwable) {
                    // ignore cleanup errors so quoting never fails because of cleanup
                }
            }
        }
    }

    /**
     * @return array<string, scalar>
     */
    private function buildPayload(RateRequest $request): array
    {
        $sender = $request->sender;
        $recipient = $request->recipient;

        if (trim((string) $sender->postalCode) === '' || trim((string) $sender->city) === '') {
            throw new RateRejected(
                'Spediamo: Sender address requires at least postal code and city (configure shipping.sender_postal_code and shipping.sender_city in settings or .env).'
            );
        }

        if (trim((string) $recipient->postalCode) === '' || trim((string) $recipient->city) === '') {
            throw new RateRejected('Spediamo: Recipient address requires at least postal code and city.');
        }

        $senderCountry = strtoupper(trim((string) $sender->country));
        $senderCountry = $senderCountry !== '' ? $senderCountry : 'IT';

        $recipientCountry = strtoupper(trim((string) $recipient->country));
        $recipientCountry = $recipientCountry !== '' ? $recipientCountry : 'IT';

        $type = ($senderCountry === 'IT' && $recipientCountry === 'IT') ? 'DOMESTIC' : 'INTERNATIONAL';

        $senderPostal = trim((string) $sender->postalCode);
        if ($senderCountry === 'IT' && is_numeric($senderPostal)) {
            $senderPostal = str_pad($senderPostal, 5, '0', STR_PAD_LEFT);
        }

        $recipientPostal = trim((string) $recipient->postalCode);
        if ($recipientCountry === 'IT' && is_numeric($recipientPostal)) {
            $recipientPostal = str_pad($recipientPostal, 5, '0', STR_PAD_LEFT);
        }

        $senderName = property_exists($sender, 'company') && ! empty($sender->company) ? $sender->company : $sender->name;
        $recipientName = property_exists($recipient, 'company') && ! empty($recipient->company) ? $recipient->company : $recipient->name;

        $payload = [
            'type' => $type,
            'senderName' => $senderName,
            'senderContactName' => $sender->name,
            'senderCountry' => $senderCountry,
            'senderProvince' => $sender->province,
            'senderTown' => $sender->city,
            'senderPostalCode' => $senderPostal,
            'senderAddress' => $sender->street,
            'senderPhone' => $sender->phone,
            'senderEmail' => $sender->email,
            'recipientName' => $recipientName,
            'recipientContactName' => $recipient->name,
            'recipientCountry' => $recipientCountry,
            'recipientProvince' => $recipient->province,
            'recipientTown' => $recipient->city,
            'recipientPostalCode' => $recipientPostal,
            'recipientAddress' => $recipient->street,
            'recipientPhone' => $recipient->phone,
            'recipientEmail' => $recipient->email,
            ...SpediamoPackages::fields($request->parcels),
        ];

        if ($request->carrierCode !== null && trim($request->carrierCode) !== '') {
            $courier = $this->carriers->toProvider($request->carrierCode) ?? strtoupper(trim($request->carrierCode));
            if ($courier !== '') {
                $payload['courier'] = $courier;
            }
        }

        if ($request->insuranceAmount !== null && $request->insuranceAmount > 0) {
            $payload['insurance'] = 'true';
            $payload['insuranceAmount'] = number_format($request->insuranceAmount, 2, '.', '');
        }

        if ($request->codAmount !== null && $request->codAmount > 0) {
            $payload['cashOnDelivery'] = 'true';
            $payload['cashOnDeliveryAmount'] = number_format($request->codAmount, 2, '.', '');
        }

        if ($request->coupon !== null && trim($request->coupon) !== '') {
            $payload['coupon'] = trim($request->coupon);
        }

        return array_filter($payload, fn ($v) => $v !== '' && $v !== null);
    }

    private function mapQuote(array $data, ?string $shipmentId, ?string $requestedCarrier = null): RateQuote
    {
        $rawCourier = strtoupper(trim((string) ($data['courier'] ?? 'SDA')));
        if ($rawCourier === '') {
            $rawCourier = 'SDA';
        }

        $preferred = ($requestedCarrier !== null && trim($requestedCarrier) !== '') ? [trim($requestedCarrier)] : null;
        $carrierCode = $this->carriers->fromProvider($rawCourier, $preferred) ?? strtolower($rawCourier);

        $cleanup = (bool) ($this->config['cleanup_quote_drafts'] ?? true);
        $quoteToken = $cleanup ? null : ($shipmentId !== '' && $shipmentId !== null ? $shipmentId : null);

        $surcharges = array_filter([
            'fuel' => $this->parseAmount($data['fuelSurcharge'] ?? null),
            'destination' => $this->parseAmount($data['destinationSurcharge'] ?? null),
            'cod' => $this->parseAmount($data['cashOnDeliveryCommission'] ?? null),
            'insurance' => $this->parseAmount($data['insuranceCommission'] ?? null),
        ], fn (float $val) => abs($val) > 0.0001);

        $warnings = [];
        if (! empty($data['warnings'])) {
            if (is_array($data['warnings'])) {
                foreach ($data['warnings'] as $warning) {
                    if (is_string($warning) && trim($warning) !== '') {
                        $warnings[] = trim($warning);
                    } elseif (is_scalar($warning)) {
                        $warnings[] = (string) $warning;
                    }
                }
            } elseif (is_scalar($data['warnings'])) {
                $warnings[] = (string) $data['warnings'];
            }
        }

        $netPrice = $this->parseAmountNullable($data['netPrice'] ?? null)
            ?? $this->parseAmountNullable($data['basePrice'] ?? null)
            ?? 0.0;
        $totalPrice = $this->parseAmountNullable($data['totalPrice'] ?? null)
            ?? $this->parseAmountNullable($data['netPrice'] ?? null)
            ?? $netPrice;
        $listPrice = isset($data['basePrice']) ? $this->parseAmountNullable($data['basePrice']) : null;
        $taxableWeight = isset($data['taxableWeight']) ? $this->parseAmountNullable($data['taxableWeight']) : null;

        return new RateQuote(
            provider: self::NAME,
            carrierCode: $carrierCode,
            carrierName: $rawCourier,
            rawCarrierCode: $rawCourier,
            serviceName: (string) ($data['type'] ?? 'DOMESTIC'),
            netPrice: $netPrice,
            totalPrice: $totalPrice,
            listPrice: $listPrice,
            currency: (string) ($data['currency'] ?? 'EUR'),
            taxableWeightKg: $taxableWeight,
            dimensionalWeightApplied: (bool) ($data['dimensionalWeightApplied'] ?? false),
            estimatedDelivery: $this->dateValue($data['expectedDeliveryDate'] ?? null),
            quoteToken: $quoteToken,
            surcharges: $surcharges,
            warnings: $warnings,
            raw: $data,
        );
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(callable $call): Response
    {
        try {
            $pending = Http::baseUrl($this->baseUrl)
                ->acceptJson()
                ->timeout($this->timeout)
                ->connectTimeout(5);

            $http = $this->authenticator->authenticate($pending);

            $response = $call($http);
        } catch (ShippingCredentialsException $e) {
            $ex = new RateProviderUnavailable($e->getMessage(), 0, $e);
            if (method_exists($ex, 'withHttpStatus')) {
                $ex->withHttpStatus($e->httpStatus);
            }
            throw $ex;
        } catch (ConnectionException $e) {
            throw new RateProviderUnavailable(self::NAME.': connection failed', 0, $e);
        }

        if ($response->successful()) {
            return $response;
        }

        $status = $response->status();

        if (in_array($status, [400, 404, 422], true)) {
            $msg = $this->extractErrorMessage($response);
            $ex = (new RateRejected($msg ?? self::NAME.': HTTP '.$status))
                ->withIssues(SpediamoIssueMapper::fromErrors((array) ($response->json('errors') ?? [])));
            if (method_exists($ex, 'withHttpStatus')) {
                $ex->withHttpStatus($status);
            }
            throw $ex;
        }

        // 401, 403, 429, 5xx, etc.
        $msg = $this->extractErrorMessage($response);
        $ex = new RateProviderUnavailable($msg ?? self::NAME.': HTTP '.$status);
        if (method_exists($ex, 'withHttpStatus')) {
            $ex->withHttpStatus($status);
        }
        throw $ex;
    }

    /**
     * A priced draft still lists what is missing before it can be booked. The pickup date is chosen at purchase time,
     * so "required" on it does not make the quote invalid.
     *
     * @return array<int|string, mixed>
     */
    private function blockingErrors(mixed $errors): array
    {
        if (! is_array($errors)) {
            return [];
        }

        return array_filter($errors, fn ($error) => ! (
            is_array($error)
            && ($error['code'] ?? null) === 'required'
            && in_array($error['field'] ?? null, self::BOOKING_FIELDS, true)
        ));
    }

    private function formatErrors(array $errors): string
    {
        $parts = [];
        foreach ($errors as $k => $v) {
            $prefix = is_string($k) && ! is_numeric($k) ? "{$k}: " : '';
            if (is_scalar($v)) {
                $valStr = trim((string) $v);
                $valStr = (string) preg_replace('/^\d+:\s*/', '', $valStr);
                $parts[] = $prefix.$valStr;
            } elseif (is_array($v)) {
                $subParts = [];
                foreach ($v as $subK => $subV) {
                    $subPrefix = is_string($subK) && ! is_numeric($subK) ? "{$subK}: " : '';
                    if (is_scalar($subV)) {
                        $subValStr = trim((string) $subV);
                        $subValStr = (string) preg_replace('/^\d+:\s*/', '', $subValStr);
                        $subParts[] = $subPrefix.$subValStr;
                    } else {
                        $subParts[] = json_encode($subV);
                    }
                }
                $parts[] = $prefix.implode('; ', $subParts);
            } else {
                $parts[] = $prefix.json_encode($v);
            }
        }

        return implode(', ', array_filter($parts, fn ($p) => $p !== ''));
    }

    private function extractErrorMessage(Response $response): ?string
    {
        try {
            $body = $response->json();
        } catch (Throwable) {
            return null;
        }

        if (! is_array($body)) {
            return null;
        }

        if (! empty($body['errors']) && is_array($body['errors'])) {
            $formatted = $this->formatErrors($body['errors']);

            return $formatted !== '' ? $formatted : null;
        }

        $message = $body['message'] ?? $body['error'] ?? $body['description'] ?? null;

        return is_string($message) && trim($message) !== '' ? trim($message) : null;
    }

    /** Spediamo sends dates as {"value": "2026-10-29", "localizedValue": "29/10/2026"}. */
    private function dateValue(mixed $value): ?string
    {
        $date = is_array($value) ? ($value['value'] ?? null) : $value;

        return is_string($date) && trim($date) !== '' ? trim($date) : null;
    }

    private function parseAmount(mixed $value, float $default = 0.0): float
    {
        return $this->parseAmountNullable($value) ?? $default;
    }

    private function parseAmountNullable(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }
            if (is_numeric($trimmed)) {
                return (float) $trimmed;
            }
            $normalized = str_replace(',', '.', $trimmed);
            if (is_numeric($normalized)) {
                return (float) $normalized;
            }

            return null;
        }

        if (is_array($value)) {
            if (array_key_exists('value', $value)) {
                return $this->parseAmountNullable($value['value']);
            }
            if (array_key_exists('amount', $value)) {
                return $this->parseAmountNullable($value['amount']);
            }

            return null;
        }

        if (is_object($value)) {
            if (isset($value->value)) {
                return $this->parseAmountNullable($value->value);
            }
            if (isset($value->amount)) {
                return $this->parseAmountNullable($value->amount);
            }

            return null;
        }

        return null;
    }
}
