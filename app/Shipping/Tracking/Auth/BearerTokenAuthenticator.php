<?php

namespace App\Shipping\Tracking\Auth;

use App\Shipping\Tracking\Contracts\Authenticator;
use App\Shipping\Tracking\Exceptions\TrackingProviderUnavailable;
use Illuminate\Http\Client\PendingRequest;

/**
 * Static token sent as "Authorization: Bearer <token>". A blank token is a configuration problem and
 * is reported when a request is made (never when the driver is resolved), without echoing anything.
 */
final class BearerTokenAuthenticator implements Authenticator
{
    public function __construct(
        private readonly string $token,
        private readonly string $provider = 'tracking',
    ) {}

    public function authenticate(PendingRequest $request): PendingRequest
    {
        if (trim($this->token) === '') {
            throw new TrackingProviderUnavailable("{$this->provider}: API token is not configured");
        }

        return $request->withToken(trim($this->token));
    }
}
