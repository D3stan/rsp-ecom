<?php

namespace App\Shipping\Labels\DTO;

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
