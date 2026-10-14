<?php

namespace App\Exceptions;

use App\Models\Refund;
use RuntimeException;
use Throwable;

/**
 * Stripe did not execute a refund. The refund row is marked failed with Stripe's message; nothing was emailed.
 */
class RefundFailed extends RuntimeException
{
    public function __construct(public readonly Refund $refund, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
