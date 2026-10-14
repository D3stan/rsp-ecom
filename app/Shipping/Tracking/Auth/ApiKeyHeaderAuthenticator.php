<?php

namespace App\Shipping\Tracking\Auth;

use App\Shipping\Tracking\Contracts\Authenticator;
use App\Shipping\Tracking\Exceptions\TrackingProviderUnavailable;
use Illuminate\Http\Client\PendingRequest;

/**
 * Static API key sent in a custom header, e.g. "X-Api-Key: <key>".
 */
final class ApiKeyHeaderAuthenticator implements Authenticator
{
    public function __construct(
        private readonly string $key,
        private readonly string $header = 'X-Api-Key',
        private readonly string $provider = 'tracking',
    ) {}

    public function authenticate(PendingRequest $request): PendingRequest
    {
        if (trim($this->key) === '') {
            throw new TrackingProviderUnavailable("{$this->provider}: API key is not configured");
        }

        return $request->withHeaders([$this->header => trim($this->key)]);
    }
}
