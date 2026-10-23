<?php

namespace App\Shipping\Support;

/**
 * One concrete problem behind a failed quote. `message` is the carrier's own wording: admin-only, never for customers.
 */
final readonly class ShippingIssue
{
    /** Recipient data the admin can correct on the order. */
    public const ADDRESS = 'address';

    /** Sender data: fixed in the shipping settings. */
    public const SETTINGS = 'settings';

    public const PARCEL = 'parcel';

    /** Timeout, 5xx, bad credentials: nothing to correct on the order. */
    public const UNAVAILABLE = 'unavailable';

    public const OTHER = 'other';

    public function __construct(
        public string $provider,
        public string $kind,
        public ?string $field,
        public string $code,
        public string $message,
    ) {}

    /** @return array{provider: string, kind: string, field: ?string, code: string, message: string} */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
