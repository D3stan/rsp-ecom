<?php

namespace App\Shipping\Labels\DTO;

/**
 * One parcel in a shipment. Dimensions are centimetres, weight kilograms.
 */
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
