<?php

namespace App\Shipping\Tracking\Exceptions;

use RuntimeException;

/**
 * Base class of everything the tracking module throws on purpose. Adapters must only throw
 * subclasses of this, so callers can tell "the carrier said no" from a bug.
 */
class TrackingException extends RuntimeException
{
    /**
     * HTTP status the carrier answered with, when there was one (diagnostics only).
     */
    public ?int $httpStatus = null;

    public function withHttpStatus(?int $status): static
    {
        $this->httpStatus = $status;

        return $this;
    }
}
