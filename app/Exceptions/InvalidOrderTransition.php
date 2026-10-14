<?php

namespace App\Exceptions;

use DomainException;

/**
 * A status change the order lifecycle does not allow (App\Domain\Orders\OrderTransitions). The message is safe to
 * show to the admin who asked for it.
 */
class InvalidOrderTransition extends DomainException
{
    public static function notAllowed(string $orderNumber, ?string $from, string $to): self
    {
        return new self("Order {$orderNumber} cannot move from \"{$from}\" to \"{$to}\".");
    }

    public static function reasonRequired(string $orderNumber, ?string $from, string $to): self
    {
        return new self("Order {$orderNumber}: moving from \"{$from}\" to \"{$to}\" needs a reason.");
    }
}
