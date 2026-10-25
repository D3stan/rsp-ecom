<?php

namespace App\Shipping\Providers\Spediamo;

final class SpediamoPackages
{
    private const UNKNOWN_SIZE_CM = 10;

    /**
     * @param  array<int, object>  $parcels  weightKg, lengthCm, widthCm, heightCm, optional quantity
     * @param  array<string, mixed>  $defaultParcel  used when $parcels is empty
     * @return array<string, string>
     */
    public static function fields(array $parcels, array $defaultParcel = []): array
    {
        $entries = [];

        foreach ($parcels as $parcel) {
            $entries[] = self::entry(
                max(1, (int) ($parcel->quantity ?? 1)),
                (float) $parcel->weightKg,
                (float) ($parcel->heightCm ?? 0),
                (float) ($parcel->lengthCm ?? 0),
                (float) ($parcel->widthCm ?? 0),
            );
        }

        if ($entries === [] && $defaultParcel !== []) {
            $entries[] = self::entry(
                1,
                (float) ($defaultParcel['weight'] ?? $defaultParcel['weightKg'] ?? $defaultParcel['weight_kg'] ?? 1),
                (float) ($defaultParcel['height'] ?? $defaultParcel['heightCm'] ?? $defaultParcel['height_cm'] ?? 0),
                (float) ($defaultParcel['length'] ?? $defaultParcel['lengthCm'] ?? $defaultParcel['length_cm'] ?? 0),
                (float) ($defaultParcel['width'] ?? $defaultParcel['widthCm'] ?? $defaultParcel['width_cm'] ?? 0),
            );
        }

        return ['packages' => implode(',', $entries ?: [self::entry(1, 1.0, 0, 0, 0)])];
    }

    private static function entry(int $quantity, float $weightKg, float $height, float $length, float $width): string
    {
        $weight = rtrim(rtrim(number_format($weightKg, 2, '.', ''), '0'), '.');

        return implode(':', [
            $quantity,
            $weight !== '' ? $weight : '0',
            ...array_map(fn (float $size) => $size > 0 ? (int) ceil($size) : self::UNKNOWN_SIZE_CM, [$height, $length, $width]),
        ]);
    }
}
