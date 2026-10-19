<?php

namespace App\Shipping\Contracts;

use Illuminate\Http\Client\PendingRequest;

/**
 * How an HTTP shipping adapter proves its identity to a carrier API (bearer token, API-key header,
 * OAuth client credentials, ...). Implementations live in App\Shipping\Auth and are shared by both
 * tracking and label providers.
 *
 * Credentials must never be logged and never appear in exception messages.
 */
interface Authenticator
{
    /**
     * Add the credentials to the request (header, query, ...).
     */
    public function authenticate(PendingRequest $request): PendingRequest;
}
