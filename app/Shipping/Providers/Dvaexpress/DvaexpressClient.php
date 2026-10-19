<?php

namespace App\Shipping\Providers\Dvaexpress;

use App\Shipping\Exceptions\ShippingCredentialsException;
use App\Shipping\Labels\Exceptions\LabelProviderUnavailable;
use App\Shipping\Labels\Exceptions\LabelRejected;
use App\Shipping\Rates\Exceptions\RateProviderUnavailable;
use App\Shipping\Rates\Exceptions\RateRejected;
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

    private const AUTH_ERROR_CODES = [101, 102, 103, 107, 120];

    private const AVAILABILITY_ERROR_CODES = [101, 102, 103, 104, 107, 120, 131];

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
            if ($response->successful()) {
                throw $makeUnavailable('Dvaexpress returned invalid JSON response', $httpStatus, null);
            }
            throw $makeRejected("Dvaexpress request failed (HTTP {$httpStatus})", $httpStatus, null);
        }

        $result = strtoupper(trim((string) ($data['result'] ?? '')));
        $errorCodeRaw = $data['errorcode'] ?? $data['error_code'] ?? null;
        $hasErrorCode = $errorCodeRaw !== null && $errorCodeRaw !== '' && (int) $errorCodeRaw !== 0;

        if ($result === 'KO' || $hasErrorCode || ! $response->successful()) {
            $errorCode = $errorCodeRaw !== null && $errorCodeRaw !== '' ? (int) $errorCodeRaw : null;
            $defaultMsg = $errorCode !== null ? "Dvaexpress error {$errorCode}" : "Dvaexpress request failed (HTTP {$httpStatus})";
            $message = $this->redact(self::flattenErrorMessage($data, $defaultMsg));

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
        $msg = trim((string) ($body['errormessage'] ?? $body['error_message'] ?? ''));
        $details = $body['errordetails'] ?? $body['error_details'] ?? null;

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
