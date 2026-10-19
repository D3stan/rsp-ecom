<?php

namespace App\Shipping\Rates\DTO;

final readonly class RateParcel
{
    public function __construct(
        public float $weightKg,
        public ?float $lengthCm = null,
        public ?float $widthCm = null,
        public ?float $heightCm = null,
        public int $quantity = 1,
    ) {}
}
