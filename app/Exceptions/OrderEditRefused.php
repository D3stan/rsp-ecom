<?php

namespace App\Exceptions;

use DomainException;

/**
 * An admin order edit that is not allowed (App\Domain\Orders\OrderFinancialEdit). Nothing was written; the message is
 * safe to show to the admin who sent it.
 */
class OrderEditRefused extends DomainException
{
    public static function paymentStatus(): self
    {
        return new self('The payment status cannot be edited: it follows Stripe and refunds.');
    }

    public static function financialsLocked(string $orderNumber): self
    {
        return new self("Order {$orderNumber} is paid or its payment is in progress: its items, prices, quantities and totals can no longer be edited. Use a refund to return money.");
    }

    public static function invalidLines(string $detail): self
    {
        return new self("The order lines were not saved: {$detail}");
    }
}
