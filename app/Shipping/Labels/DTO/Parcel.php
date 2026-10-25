<?php

namespace App\Shipping\Labels\DTO;

final readonly class Parcel
{
    public function __construct(
        public float $weightKg,
        public ?float $lengthCm = null,
        public ?float $widthCm = null,
        public ?float $heightCm = null,
        public string $description = '',
    ) {}
}
