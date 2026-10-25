<?php

namespace App\Shipping\Contracts;

use Illuminate\Http\Client\Response;

interface RefreshesCredentials extends Authenticator
{
    public function shouldRetryAfter(Response $response): bool;

    public function refresh(): void;
}
