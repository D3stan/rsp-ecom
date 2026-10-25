<?php

namespace App\Shipping\Contracts;

use Illuminate\Http\Client\PendingRequest;

interface Authenticator
{
    public function authenticate(PendingRequest $request): PendingRequest;
}
