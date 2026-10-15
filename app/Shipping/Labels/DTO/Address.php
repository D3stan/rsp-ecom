<?php

namespace App\Shipping\Labels\DTO;

/**
 * A sender or recipient address for a shipment. Provider neutral: the driver maps it to the carrier's field names.
 */
final readonly class Address
{
    public function __construct(
        public string $name,
        public string $street,
        public string $city,
        public string $postalCode,
        public string $province = '',
        public string $country = 'IT',
        public string $phone = '',
        public string $email = '',
        public string $company = '',
    ) {}
}
