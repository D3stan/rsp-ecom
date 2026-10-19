<?php

namespace App\Shipping\Contracts;

use Illuminate\Http\Client\Response;

/**
 * Optional capability of an Authenticator whose credentials expire (OAuth access tokens, session
 * tokens): HTTP providers ask it after a failed call and, when it agrees, discard the credentials
 * and repeat the call exactly once.
 */
interface RefreshesCredentials extends Authenticator
{
    /**
     * Does this response mean "the credentials expired" (typically HTTP 401)?
     */
    public function shouldRetryAfter(Response $response): bool;

    /**
     * Forget the current credentials so that the next authenticate() obtains fresh ones.
     */
    public function refresh(): void;
}
