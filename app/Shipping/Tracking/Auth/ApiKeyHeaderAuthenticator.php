<?php

namespace App\Shipping\Tracking\Auth;

use App\Shipping\Auth\ApiKeyHeaderAuthenticator as BaseApiKeyHeaderAuthenticator;
use App\Shipping\Tracking\Contracts\Authenticator;
use App\Shipping\Tracking\Exceptions\TrackingProviderUnavailable;

/**
 * Static API key sent in a custom header, e.g. "X-Api-Key: <key>".
 */
final class ApiKeyHeaderAuthenticator extends BaseApiKeyHeaderAuthenticator implements Authenticator
{
    public function __construct(
        string $key,
        string $header = 'X-Api-Key',
        string $provider = 'tracking',
    ) {
        parent::__construct($key, $header, $provider);
    }

    protected function fail(string $message): never
    {
        throw new TrackingProviderUnavailable($message);
    }
}
