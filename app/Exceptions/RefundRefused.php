<?php

namespace App\Exceptions;

use DomainException;

/**
 * A refund that was not attempted (nothing sent to Stripe, nothing recorded). The message is safe to show the admin.
 */
class RefundRefused extends DomainException
{
    public static function notCaptured(string $orderNumber): self
    {
        return new self("Order {$orderNumber} has no captured payment to refund.");
    }

    public static function noPaymentIntent(string $orderNumber): self
    {
        return new self("Order {$orderNumber} has no Stripe payment to refund (no payment intent).");
    }

    public static function invalidAmount(string $orderNumber, int $refundableCents): self
    {
        return new self(sprintf('Order %s: the refund must be more than 0.00 and at most %s.', $orderNumber, number_format($refundableCents / 100, 2, '.', '')));
    }

    public static function notPending(int $refundId): self
    {
        return new self("Refund #{$refundId} is not waiting for Stripe: only a pending refund can be retried.");
    }

    public static function invalidRestock(string $orderNumber): self
    {
        return new self("Order {$orderNumber}: the items to restock do not match the order lines.");
    }
}
