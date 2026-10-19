<?php

namespace App\Shipping\Support;

/**
 * Translates between our provider-neutral carrier codes (keys of config('shipping.carriers'), stored on
 * orders) and the strings one carrier API uses for the same courier. Comes from a driver's config:
 *
 *     'carrier_map' => ['sda' => 'SDA', 'brt' => 'BARTOLINI'],   // our code => the provider's courier
 *
 * Both directions are case-insensitive and trim whitespace; anything unknown yields null, never a guess.
 * An empty map means "no restriction": supports() may then accept every carrier.
 */
class CarrierCodeMap
{
    /** @var array<string, string> lower-cased our code => provider courier string */
    private array $forward = [];

    /** @var array<string, string> lower-cased provider courier => our code */
    private array $reverse = [];

    /**
     * @param  array<array-key, mixed>  $map  our code => provider courier string
     */
    public function __construct(array $map = [])
    {
        foreach ($map as $ours => $theirs) {
            $ours = trim((string) $ours);
            $theirs = is_scalar($theirs) ? trim((string) $theirs) : '';

            if ($ours === '' || $theirs === '') {
                continue;
            }

            $this->forward[mb_strtolower($ours)] ??= $theirs;
            $this->reverse[mb_strtolower($theirs)] ??= $ours;
        }
    }

    /**
     * @param  array<string, mixed>  $driverConfig
     */
    public static function fromDriverConfig(array $driverConfig): static
    {
        return new static((array) ($driverConfig['carrier_map'] ?? []));
    }

    public function isEmpty(): bool
    {
        return $this->forward === [];
    }

    /** Our code => the provider's courier string, or null when unmapped. */
    public function toProvider(?string $ourCode): ?string
    {
        return $this->forward[mb_strtolower(trim((string) $ourCode))] ?? null;
    }

    /** The provider's courier string => our code, or null when unmapped. */
    public function fromProvider(?string $courier): ?string
    {
        return $this->reverse[mb_strtolower(trim((string) $courier))] ?? null;
    }
}
