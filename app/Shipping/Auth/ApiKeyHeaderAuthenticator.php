<?php

namespace App\Shipping\Auth;

use App\Shipping\Contracts\Authenticator;
use App\Shipping\Exceptions\ShippingCredentialsException;
use Illuminate\Http\Client\PendingRequest;

/**
 * Static API key sent in a custom header, e.g. "X-Api-Key: <key>".
 */
class ApiKeyHeaderAuthenticator implements Authenticator
{
    public function __construct(
        private readonly string $key,
        private readonly string $header = 'X-Api-Key',
        private readonly string $provider = 'shipping',
    ) {}

    public function authenticate(PendingRequest $request): PendingRequest
    {
        if (trim($this->key) === '') {
            $this->fail("{$this->provider}: API key is not configured");
        }

        return $request->withHeaders([$this->header => trim($this->key)]);
    }

    protected function fail(string $message): never
    {
        throw new ShippingCredentialsException($message);
    }
}
