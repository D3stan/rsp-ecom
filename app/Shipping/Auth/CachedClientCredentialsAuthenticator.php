<?php

namespace App\Shipping\Auth;

use App\Shipping\Contracts\RefreshesCredentials;
use App\Shipping\Exceptions\ShippingCredentialsException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

class CachedClientCredentialsAuthenticator implements RefreshesCredentials
{
    private const EXPIRY_MARGIN_SECONDS = 60;

    private const DEFAULT_TTL_SECONDS = 3600;

    private const MAX_TTL_SECONDS = 86400;

    public function __construct(
        private readonly string $provider,
        private readonly string $tokenUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly ?string $scope = null,
        private readonly int $timeout = 10,
    ) {}

    public function authenticate(PendingRequest $request): PendingRequest
    {
        return $request->withToken($this->token());
    }

    public function shouldRetryAfter(Response $response): bool
    {
        return $response->status() === 401;
    }

    public function refresh(): void
    {
        Cache::forget($this->cacheKey());
    }

    private function token(): string
    {
        $cached = Cache::get($this->cacheKey());

        if (is_string($cached)) {
            try {
                return Crypt::decryptString($cached);
            } catch (DecryptException) {
                Cache::forget($this->cacheKey());
            }
        }

        return $this->requestToken();
    }

    private function requestToken(): string
    {
        if (trim($this->tokenUrl) === '' || trim($this->clientId) === '' || trim($this->clientSecret) === '') {
            $this->fail("{$this->provider}: client credentials are not configured");
        }

        try {
            $response = Http::asForm()->acceptJson()->timeout(max(1, $this->timeout))->connectTimeout(5)->post($this->tokenUrl, array_filter([
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => $this->scope,
            ], fn ($value) => $value !== null && $value !== ''));
        } catch (ConnectionException $e) {
            $this->fail("{$this->provider}: token endpoint unreachable", null, $e);
        }

        $token = $response->successful() ? $response->json('access_token') : null;

        if (! is_string($token) || $token === '') {
            $this->fail("{$this->provider}: token endpoint answered HTTP {$response->status()} without an access token", $response->status());
        }

        $ttl = (int) $response->json('expires_in', self::DEFAULT_TTL_SECONDS) - self::EXPIRY_MARGIN_SECONDS;
        $ttl = min(max($ttl, 0), self::MAX_TTL_SECONDS);

        if ($ttl > 0) {
            Cache::put($this->cacheKey(), Crypt::encryptString($token), $ttl);
        }

        return $token;
    }

    protected function fail(string $message, ?int $httpStatus = null, ?Throwable $previous = null): never
    {
        throw (new ShippingCredentialsException($message, 0, $previous))->withHttpStatus($httpStatus);
    }

    private function cacheKey(): string
    {
        return 'tracking:auth:'.$this->provider.':'.sha1($this->clientId.'|'.$this->tokenUrl);
    }
}
