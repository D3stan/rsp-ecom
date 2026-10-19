<?php

namespace App\Shipping\Exceptions;

use RuntimeException;

/**
 * Thrown by shared authenticators (App\Shipping\Auth) when credentials are missing or a token
 * endpoint fails. Subsystem HTTP layers (Tracking, Labels) translate this into their own
 * provider-unavailable exception so authenticators remain subsystem-neutral.
 */
class ShippingCredentialsException extends RuntimeException
{
    public ?int $httpStatus = null;

    public function withHttpStatus(?int $status): static
    {
        $this->httpStatus = $status;

        return $this;
    }
}
