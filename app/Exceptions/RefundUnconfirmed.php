<?php

namespace App\Exceptions;

use App\Models\Refund;
use RuntimeException;
use Throwable;

/**
 * Stripe did not answer clearly (timeout, connection error, rate limit, 5xx): it may or may not have refunded. The
 * refund stays pending with its amount reserved; the charge.refunded webhook or a retry under the same idempotency key
 * settles it. Never issue a new refund for it.
 */
class RefundUnconfirmed extends RuntimeException
{
    public function __construct(public readonly Refund $refund, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
