<?php

namespace App\Shipping\Tracking\Exceptions;

use RuntimeException;

class TrackingException extends RuntimeException
{
    public ?int $httpStatus = null;

    public function withHttpStatus(?int $status): static
    {
        $this->httpStatus = $status;

        return $this;
    }
}
