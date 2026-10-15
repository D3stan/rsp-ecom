<?php

namespace App\Shipping\Labels\Exceptions;

use RuntimeException;

/**
 * Base for the label layer's failures. Messages never contain credentials or request bodies.
 */
abstract class LabelException extends RuntimeException
{
    public ?int $httpStatus = null;

    public function withHttpStatus(?int $status): static
    {
        $this->httpStatus = $status;

        return $this;
    }
}
