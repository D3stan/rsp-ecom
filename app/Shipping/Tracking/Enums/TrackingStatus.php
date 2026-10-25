<?php

namespace App\Shipping\Tracking\Enums;

enum TrackingStatus: string
{
    case Unknown = 'unknown';
    case InfoReceived = 'info_received';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Exception = 'exception';
    case Returned = 'returned';

    public function isTerminal(): bool
    {
        return $this === self::Delivered || $this === self::Returned;
    }

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

    public static function fromValue(?string $value): self
    {
        return $value === null ? self::Unknown : (self::tryFrom(strtolower(trim($value))) ?? self::Unknown);
    }
}
