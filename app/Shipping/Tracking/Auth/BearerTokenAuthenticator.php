<?php

namespace App\Shipping\Tracking\Auth;

use App\Shipping\Auth\BearerTokenAuthenticator as BaseBearerTokenAuthenticator;
use App\Shipping\Tracking\Contracts\Authenticator;
use App\Shipping\Tracking\Exceptions\TrackingProviderUnavailable;

/**
 * Static token sent as "Authorization: Bearer <token>". A blank token is a configuration problem and
 * is reported when a request is made (never when the driver is resolved), without echoing anything.
 */
final class BearerTokenAuthenticator extends BaseBearerTokenAuthenticator implements Authenticator
{
    public function __construct(
        string $token,
        string $provider = 'tracking',
    ) {
        parent::__construct($token, $provider);
    }

    protected function fail(string $message): never
    {
        throw new TrackingProviderUnavailable($message);
    }
}
