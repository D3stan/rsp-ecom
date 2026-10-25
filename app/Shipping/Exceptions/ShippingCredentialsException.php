<?php

namespace App\Shipping\Exceptions;

use RuntimeException;

class ShippingCredentialsException extends RuntimeException
{
    public ?int $httpStatus = null;

    public function withHttpStatus(?int $status): static
    {
        $this->httpStatus = $status;

        return $this;
    }
}
