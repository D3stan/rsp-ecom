<?php

namespace App\Shipping\Tracking\Contracts;

use App\Shipping\Tracking\Exceptions\TrackingProviderUnavailable;
use Illuminate\Http\Client\PendingRequest;

/**
 * How an HTTP adapter proves its identity to the carrier API (bearer token, API-key header, OAuth
 * client credentials, ...). Implementations live in App\Shipping\Tracking\Auth and are handed to
 * AbstractHttpProvider, so the same adapter code works with any scheme.
 *
 * Credentials must never be logged and never appear in exception messages.
 */
interface Authenticator
{
    /**
     * Add the credentials to the request (header, query, ...).
     *
     * @throws TrackingProviderUnavailable credentials are missing or cannot be obtained (a configuration problem)
     */
    public function authenticate(PendingRequest $request): PendingRequest;
}
