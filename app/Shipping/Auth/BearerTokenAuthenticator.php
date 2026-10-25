<?php

namespace App\Shipping\Auth;

use App\Shipping\Contracts\Authenticator;
use App\Shipping\Exceptions\ShippingCredentialsException;
use Illuminate\Http\Client\PendingRequest;

class BearerTokenAuthenticator implements Authenticator
{
    public function __construct(
        private readonly string $token,
        private readonly string $provider = 'shipping',
    ) {}

    public function authenticate(PendingRequest $request): PendingRequest
    {
        if (trim($this->token) === '') {
            $this->fail("{$this->provider}: API token is not configured");
        }

        return $request->withToken(trim($this->token));
    }

    protected function fail(string $message): never
    {
        throw new ShippingCredentialsException($message);
    }
}
