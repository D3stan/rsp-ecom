<?php

namespace App\Shipping\Rates\DTO;

final readonly class RateAddress
{
    public function __construct(
        public string $postalCode,
        public string $city,
        public string $province,
        public string $country = 'IT',
        public string $name = '',
        public string $street = '',
        public string $phone = '',
        public string $email = '',
    ) {}
}
