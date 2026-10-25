<?php

namespace App\Shipping\Labels\Exceptions;

use RuntimeException;

abstract class LabelException extends RuntimeException
{
    public ?int $httpStatus = null;

    public function withHttpStatus(?int $status): static
    {
        $this->httpStatus = $status;

        return $this;
    }
}
