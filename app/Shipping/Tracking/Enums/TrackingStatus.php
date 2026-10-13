<?php

namespace App\Shipping\Tracking\Enums;

/**
 * The single, carrier-independent vocabulary of a shipment's progress.
 *
 * Every adapter maps its carrier's own codes into one of these values; nothing outside
 * the adapter ever sees a carrier code. The stored value (orders.tracking_status,
 * shipment_tracking_events.status) is the string backing value.
 */
enum TrackingStatus: string
{
    case Unknown = 'unknown';
    case InfoReceived = 'info_received';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Exception = 'exception';
    case Returned = 'returned';

    /**
     * Nothing can follow a terminal status: polling stops and later updates are ignored.
     */
    public function isTerminal(): bool
    {
        return $this === self::Delivered || $this === self::Returned;
    }

    /**
     * Position on the happy path. Exception sits on the transit level (it can be left in either
     * direction); Returned shares the top rank with Delivered (both end the journey).
     */
    public function rank(): int
    {
        return match ($this) {
            self::Unknown => 0,
            self::InfoReceived => 1,
            self::InTransit, self::Exception => 2,
            self::OutForDelivery => 3,
            self::Delivered, self::Returned => 4,
        };
    }

    /**
     * May a shipment currently at $current move to this status?
     *
     * Never backwards (out-of-order events), never out of a terminal status, never from "unknown"
     * information; Exception and Returned may interrupt the journey at any point.
     */
    public function canReplace(?self $current): bool
    {
        if ($this === self::Unknown) {
            return false;
        }

        if ($current === null || $current === self::Unknown) {
            return true;
        }

        if ($current->isTerminal()) {
            return false;
        }

        if ($this === self::Exception || $this === self::Returned) {
            return true;
        }

        return $this->rank() >= $current->rank();
    }

    /**
     * Lenient parse for values read from storage or from an adapter: anything unrecognised is Unknown.
     */
    public static function fromValue(?string $value): self
    {
        return $value === null ? self::Unknown : (self::tryFrom(strtolower(trim($value))) ?? self::Unknown);
    }
}
