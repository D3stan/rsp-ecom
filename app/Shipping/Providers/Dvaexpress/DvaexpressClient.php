<?php

namespace App\Shipping\Providers\Dvaexpress;

use App\Shipping\Exceptions\ShippingCredentialsException;
use App\Shipping\Labels\Exceptions\LabelProviderUnavailable;
use App\Shipping\Labels\Exceptions\LabelRejected;
use App\Shipping\Rates\Exceptions\RateProviderUnavailable;
use App\Shipping\Rates\Exceptions\RateRejected;
use App\Shipping\Support\ShippingAlerts;
use App\Shipping\Tracking\Exceptions\TrackingProviderUnavailable;
use App\Shipping\Tracking\Exceptions\TrackingRejected;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class DvaexpressClient
{
    public const DEFAULT_BASE_URL = 'https://api.easyparcel.it';

    public const NAME = 'dvaexpress';

    /** Error 120 "Credito esaurito": the prepaid credit does not cover the purchase. Not an auth or outage problem. */
    public const INSUFFICIENT_CREDIT = 120;

    public const TEST_MODE_MESSAGE = 'DVA Express is in TEST mode: labels are not real and no courier will pick them up. Ask DVA Express to activate the API key (live mode), then buy the label again.';

    private const AUTH_ERROR_CODES = [101, 102, 103, 107];

    private const AVAILABILITY_ERROR_CODES = [101, 102, 103, 104, 107, 131];

    private const ACCOUNT_INFO_TTL_SECONDS = 300;

    private readonly string $baseUrl;

    private readonly string $apiKey;

    private readonly int $timeout;

    private readonly int $retryTimes;

    private readonly int $retrySleepMs;

    public function __construct(private readonly array $config = [])
    {
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? self::DEFAULT_BASE_URL), '/');
        $this->apiKey = trim((string) ($config['api_key'] ?? $config['key'] ?? ''));
        $this->timeout = max(1, (int) ($config['timeout'] ?? 15));
        $this->retryTimes = max(1, (int) ($config['retry_times'] ?? 1));
        $this->retrySleepMs = max(0, (int) ($config['retry_sleep_ms'] ?? 100));
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * The API key's account ("apikeyinfo" details, key removed): `credito_prepagato` is the credit an order is charged
     * against and `live` = "false" means test mode, where orders are simulated (placeholder waybill, nothing charged,
     * nothing picked up). Cached for a few minutes; reading it also raises or clears the admin's test-mode alert.
     *
     * @return array<string, mixed>
     *
     * @throws Throwable when the API cannot be read
     */
    public function accountInfo(): array
    {
        $info = Cache::remember('shipping:dvaexpress:apikeyinfo:'.sha1($this->baseUrl.$this->apiKey), self::ACCOUNT_INFO_TTL_SECONDS, function (): array {
            $response = $this->postRate('apikeyinfo', ['call' => 'apikeyinfo']);
            $details = (array) ($response['response']['dettagli'] ?? $response['dettagli'] ?? []);
            unset($details['apikey']);

            return $details;
        });

        $live = self::liveFlag($info);
        if ($live === false) {
            app(ShippingAlerts::class)->raise(self::NAME, ShippingAlerts::TEST_MODE, self::TEST_MODE_MESSAGE);
        } elseif ($live === true) {
            app(ShippingAlerts::class)->clear(self::NAME, ShippingAlerts::TEST_MODE);
        }

        return $info;
    }

    /**
     * Whether orders are real: true live, false test mode, null when the account cannot be read (callers must not
     * treat an outage as test mode).
     */
    public function isLive(): ?bool
    {
        try {
            return self::liveFlag($this->accountInfo());
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $info
     */
    private static function liveFlag(array $info): ?bool
    {
        if (! array_key_exists('live', $info)) {
            return null;
        }

        return filter_var($info['live'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Send tracking operation, throwing Tracking exceptions.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function postTracking(string $operation, array $payload = []): array
    {
        return $this->send(
            $operation,
            $payload,
            fn (string $msg, ?int $status, ?Throwable $prev) => $this->withHttpStatus(new TrackingProviderUnavailable($msg, 0, $prev), $status),
            fn (string $msg, ?int $status, ?Throwable $prev) => $this->withHttpStatus(new TrackingRejected($msg, 0, $prev), $status),
        );
    }

    /**
     * Send label operation, throwing Label exceptions.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function postLabel(string $operation, array $payload = []): array
    {
        return $this->send(
            $operation,
            $payload,
            fn (string $msg, ?int $status, ?Throwable $prev) => $this->withHttpStatus(new LabelProviderUnavailable($msg, 0, $prev), $status),
            fn (string $msg, ?int $status, ?Throwable $prev) => $this->withHttpStatus(new LabelRejected($msg, 0, $prev), $status),
        );
    }

    /**
     * Send rate operation, throwing Rate exceptions.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function postRate(string $operation, array $payload = []): array
    {
        return $this->send(
            $operation,
            $payload,
            fn (string $msg, ?int $status, ?Throwable $prev) => $this->withHttpStatus(new RateProviderUnavailable($msg, 0, $prev), $status),
            fn (string $msg, ?int $status, ?Throwable $prev) => $this->withHttpStatus(new RateRejected($msg, 0, $prev), $status),
        );
    }

    /**
     * Generalized post request with custom exception handlers.
     *
     * @param  array<string, mixed>  $payload
     * @param  Closure(string, ?int, ?Throwable): Throwable  $makeUnavailable
     * @param  Closure(string, ?int, ?Throwable): Throwable  $makeRejected
     * @return array<string, mixed>
     */
    public function send(
        string $operation,
        array $payload,
        Closure $makeUnavailable,
        Closure $makeRejected,
    ): array {
        if ($this->apiKey === '') {
            $this->alertAuth('API key is not configured');

            if (method_exists(ShippingCredentialsException::class, 'missing')) {
                throw ShippingCredentialsException::missing('dvaexpress', 'api_key');
            }

            throw new ShippingCredentialsException('dvaexpress: API key is not configured');
        }

        try {
            $http = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->acceptJson()
                ->asJson();

            if ($this->retryTimes > 1) {
                $http = $http->retry($this->retryTimes, $this->retrySleepMs, function (Throwable $e, ?Response $response) {
                    return $e instanceof ConnectionException || ($response && $response->status() >= 500);
                }, false);
            }

            $response = $http->post(ltrim($operation, '/').'/'.$this->apiKey, $payload);
        } catch (ConnectionException $e) {
            $msg = $this->redact($e->getMessage());
            throw $makeUnavailable("Dvaexpress connection failed: {$msg}", null, $e);
        } catch (Throwable $e) {
            $msg = $this->redact($e->getMessage());
            throw $makeUnavailable("Dvaexpress request failed: {$msg}", null, $e);
        }

        return $this->processResponse($response, $makeUnavailable, $makeRejected);
    }

    /**
     * @param  Closure(string, ?int, ?Throwable): Throwable  $makeUnavailable
     * @param  Closure(string, ?int, ?Throwable): Throwable  $makeRejected
     * @return array<string, mixed>
     */
    private function processResponse(Response $response, Closure $makeUnavailable, Closure $makeRejected): array
    {
        $httpStatus = $response->status();

        if (in_array($httpStatus, [401, 403], true)) {
            $this->alertAuth("HTTP {$httpStatus}");
            $body = trim($response->body());
            $detail = $body !== '' ? ': '.$this->redact($body) : '';
            throw $makeUnavailable("Dvaexpress authentication failed (HTTP {$httpStatus}){$detail}", $httpStatus, null);
        }

        if ($httpStatus === 429 || $httpStatus >= 500) {
            $body = trim($response->body());
            $detail = $body !== '' ? ': '.$this->redact($body) : '';
            throw $makeUnavailable("Dvaexpress service unavailable (HTTP {$httpStatus}){$detail}", $httpStatus, null);
        }

        $data = $response->json();

        if (! is_array($data)) {
            $rawBody = trim($response->body());
            $detail = $rawBody !== '' ? ': '.$this->redact(substr($rawBody, 0, 200)) : '';
            if ($response->successful()) {
                throw $makeUnavailable('Dvaexpress returned invalid JSON response', $httpStatus, null);
            }
            throw $makeRejected("Dvaexpress request failed (HTTP {$httpStatus}){$detail}", $httpStatus, null);
        }

        $result = strtoupper(trim((string) ($data['result'] ?? '')));
        $errorCodeRaw = $data['errorcode'] ?? $data['error_code'] ?? null;
        $hasErrorCode = $errorCodeRaw !== null && $errorCodeRaw !== '' && (int) $errorCodeRaw !== 0;

        if ($result === 'KO' || $hasErrorCode || ! $response->successful()) {
            $errorCode = $errorCodeRaw !== null && $errorCodeRaw !== '' ? (int) $errorCodeRaw : null;
            $defaultMsg = $errorCode !== null ? "Dvaexpress error {$errorCode}" : "Dvaexpress request failed (HTTP {$httpStatus})";
            $message = $this->redact(self::flattenErrorMessage($data, $defaultMsg));

            if ($errorCode === self::INSUFFICIENT_CREDIT) {
                // the admin must top up: show it on the form, like any other refusal
                throw $makeRejected(self::insufficientCreditMessage($message), $httpStatus, null);
            }

            if ($errorCode !== null && in_array($errorCode, self::AUTH_ERROR_CODES, true)) {
                $this->alertAuth("Error {$errorCode}: {$message}");
            }

            if ($errorCode !== null && in_array($errorCode, self::AVAILABILITY_ERROR_CODES, true)) {
                throw $makeUnavailable($message, $httpStatus, null);
            }

            // HTTP 400, 404, 422 or any other result === KO / error code
            throw $makeRejected($message, $httpStatus, null);
        }

        return $data;
    }

    /**
     * "Credito insufficiente (credito: 0.11, costo: 5.22)" as an instruction the admin can act on.
     */
    public static function insufficientCreditMessage(string $carrierMessage): string
    {
        if (preg_match('/credito:\s*(\d+(?:[.,]\d+)?).*?costo:\s*(\d+(?:[.,]\d+)?)/i', $carrierMessage, $m)) {
            return sprintf(
                'DVA Express prepaid credit is too low for this label: EUR %s available, EUR %s needed. Top up the DVA Express account, then buy the label again.',
                $m[1],
                $m[2],
            );
        }

        return "DVA Express prepaid credit is too low for this label ({$carrierMessage}). Top up the DVA Express account, then buy the label again.";
    }

    public function redact(string $message): string
    {
        if ($this->apiKey !== '') {
            $message = str_replace($this->apiKey, '[REDACTED]', $message);
        }

        return $message;
    }

    /**
     * Flatten errormessage/error_message and nested errordetails into a readable string.
     *
     * @param  array<string, mixed>  $body
     */
    public static function flattenErrorMessage(array $body, string $default = 'Dvaexpress request failed'): string
    {
        $msg = trim((string) ($body['errormessage'] ?? $body['error_message'] ?? $body['message'] ?? $body['error'] ?? ''));
        $details = $body['errordetails'] ?? $body['error_details'] ?? $body['errors'] ?? null;

        $detailStr = '';
        if (is_string($details)) {
            $detailStr = trim($details);
        } elseif (is_array($details)) {
            $parts = [];
            array_walk_recursive($details, function ($val, $key) use (&$parts) {
                if (is_scalar($val) && trim((string) $val) !== '') {
                    $parts[] = is_string($key) && ! is_numeric($key)
                        ? "{$key}: {$val}"
                        : (string) $val;
                }
            });
            $detailStr = implode('; ', $parts);
        }

        if ($msg !== '' && $detailStr !== '') {
            return "{$msg}: {$detailStr}";
        }

        if ($msg !== '') {
            return $msg;
        }

        if ($detailStr !== '') {
            return $detailStr;
        }

        return $default;
    }

    private function alertAuth(string $reason): void
    {
        if (Cache::add('shipping:dvaexpress:auth_alert', 1, 900)) {
            Log::critical("Dvaexpress API authentication/authorization failed ({$reason}). Check shipping credentials.", [
                'provider' => 'dvaexpress',
                'reason' => $reason,
            ]);
        }
    }

    private function withHttpStatus(Throwable $exception, ?int $status): Throwable
    {
        if ($status !== null && method_exists($exception, 'withHttpStatus')) {
            $exception->withHttpStatus($status);
        }

        return $exception;
    }
}
