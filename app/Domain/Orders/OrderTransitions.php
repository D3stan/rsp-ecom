<?php

namespace App\Domain\Orders;

/**
 * The order lifecycle: which status an order may move to from the one it is in. Every status change goes through
 * Order::transitionTo(), which enforces this map.
 *
 *   pending    -> processing, cancelled
 *   processing -> shipped, cancelled
 *   shipped    -> delivered, processing ("undo ship": needs a reason)
 *   delivered  -> (none; refunds after delivery do not change the status)
 *   cancelled  -> (none)
 */
final class OrderTransitions
{
    /** @var array<string, list<string>> */
    public const MAP = [
        'pending' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'processing'],
        'delivered' => [],
        'cancelled' => [],
    ];

    /** @var array<string, list<string>> moves that must carry a reason */
    public const REASON_REQUIRED = [
        'shipped' => ['processing'],
    ];

    /**
     * @return list<string> the statuses an order in $from may move to
     */
    public static function allowedFrom(?string $from): array
    {
        return self::MAP[(string) $from] ?? [];
    }

    public static function allows(?string $from, string $to): bool
    {
        return in_array($to, self::allowedFrom($from), true);
    }

    public static function requiresReason(?string $from, string $to): bool
    {
        return in_array($to, self::REASON_REQUIRED[(string) $from] ?? [], true);
    }
}
