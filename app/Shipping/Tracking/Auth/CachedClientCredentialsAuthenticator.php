<?php

namespace App\Shipping\Tracking\Auth;

use App\Shipping\Tracking\Contracts\RefreshesCredentials;
use App\Shipping\Tracking\Exceptions\TrackingProviderUnavailable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * OAuth2 "client credentials" skeleton for APIs that issue short-lived access tokens.
 *
 * Not used by any shipped adapter: it documents (and tests) the pattern so a future adapter only has to
 * pass its token endpoint and client id/secret from its driver config:
 *
 *     new CachedClientCredentialsAuthenticator(
 *         provider: 'example',
 *         tokenUrl: $config['token_url'],
 *         clientId: $config['client_id'],
 *         clientSecret: $config['client_secret'],
 *     )
 *
 * The token is requested with grant_type=client_credentials, cached (encrypted, in the shared cache
 * store) until shortly before it expires, and discarded by AbstractHttpProvider after a 401 so the
 * failed call is repeated once with a fresh token. Secrets and tokens never appear in messages or logs.
 */
final class CachedClientCredentialsAuthenticator implements RefreshesCredentials
{
    /** Tokens are renewed this many seconds before the issuer says they expire. */
    private const EXPIRY_MARGIN_SECONDS = 60;

    /** Used when the issuer sends no expires_in. */
    private const DEFAULT_TTL_SECONDS = 3600;

    /** No token is cached longer than this, whatever the issuer claims. */
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
            throw new TrackingProviderUnavailable("{$this->provider}: client credentials are not configured");
        }

        try {
            $response = Http::asForm()->acceptJson()->timeout(max(1, $this->timeout))->connectTimeout(5)->post($this->tokenUrl, array_filter([
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => $this->scope,
            ], fn ($value) => $value !== null && $value !== ''));
        } catch (ConnectionException $e) {
            throw new TrackingProviderUnavailable("{$this->provider}: token endpoint unreachable", 0, $e);
        }

        $token = $response->successful() ? $response->json('access_token') : null;

        if (! is_string($token) || $token === '') {
            throw (new TrackingProviderUnavailable("{$this->provider}: token endpoint answered HTTP {$response->status()} without an access token"))
                ->withHttpStatus($response->status());
        }

        $ttl = (int) $response->json('expires_in', self::DEFAULT_TTL_SECONDS) - self::EXPIRY_MARGIN_SECONDS;
        $ttl = min(max($ttl, 0), self::MAX_TTL_SECONDS);

        if ($ttl > 0) {
            Cache::put($this->cacheKey(), Crypt::encryptString($token), $ttl);
        }

        return $token;
    }

    private function cacheKey(): string
    {
        return 'tracking:auth:'.$this->provider.':'.sha1($this->clientId.'|'.$this->tokenUrl);
    }
}
