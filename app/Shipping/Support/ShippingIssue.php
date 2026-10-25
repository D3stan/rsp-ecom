<?php

namespace App\Shipping\Support;

final readonly class ShippingIssue
{
    public const ADDRESS = 'address';

    public const SETTINGS = 'settings';

    public const PARCEL = 'parcel';

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
