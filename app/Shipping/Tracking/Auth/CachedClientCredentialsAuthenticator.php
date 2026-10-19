<?php

namespace App\Shipping\Tracking\Auth;

use App\Shipping\Auth\CachedClientCredentialsAuthenticator as BaseCachedClientCredentialsAuthenticator;
use App\Shipping\Tracking\Contracts\RefreshesCredentials;
use App\Shipping\Tracking\Exceptions\TrackingProviderUnavailable;
use Throwable;

/**
 * OAuth2 "client credentials" skeleton for APIs that issue short-lived access tokens.
 */
final class CachedClientCredentialsAuthenticator extends BaseCachedClientCredentialsAuthenticator implements RefreshesCredentials
{
    protected function fail(string $message, ?int $httpStatus = null, ?Throwable $previous = null): never
    {
        throw (new TrackingProviderUnavailable($message, 0, $previous))->withHttpStatus($httpStatus);
    }
}
