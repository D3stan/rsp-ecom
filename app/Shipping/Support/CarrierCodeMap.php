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

    /** @var array<string, list<string>> lower-cased provider courier => list of our codes */
    private array $reverseAll = [];

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

            $lowerTheirs = mb_strtolower($theirs);
            $this->forward[mb_strtolower($ours)] ??= $theirs;
            $this->reverse[$lowerTheirs] ??= $ours;
            $this->reverseAll[$lowerTheirs][] = $ours;
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

    /**
     * The provider's courier string => our code, or null when unmapped.
     *
     * @param  list<string>|null  $preferredCodes
     */
    public function fromProvider(?string $courier, ?array $preferredCodes = null): ?string
    {
        $key = mb_strtolower(trim((string) $courier));
        if ($key === '') {
            return null;
        }

        if (! empty($preferredCodes)) {
            $aliases = $this->reverseAll[$key] ?? [];
            $aliasMap = [];
            foreach ($aliases as $alias) {
                $aliasMap[mb_strtolower(trim($alias))] = $alias;
            }

            foreach ($preferredCodes as $preferred) {
                if (! is_scalar($preferred)) {
                    continue;
                }
                $preferredKey = mb_strtolower(trim((string) $preferred));
                if (isset($aliasMap[$preferredKey])) {
                    return $aliasMap[$preferredKey];
                }
            }
        }

        return $this->reverse[$key] ?? null;
    }

    /**
     * Check whether two carrier codes map to the same courier.
     */
    public function areSameCourier(?string $codeA, ?string $codeB): bool
    {
        if ($codeA === null || $codeB === null) {
            return false;
        }

        $trimmedA = trim($codeA);
        $trimmedB = trim($codeB);

        if ($trimmedA === '' || $trimmedB === '') {
            return false;
        }

        if (mb_strtolower($trimmedA) === mb_strtolower($trimmedB)) {
            return true;
        }

        $provA = $this->toProvider($trimmedA);
        $provB = $this->toProvider($trimmedB);

        if ($provA !== null && $provB !== null && mb_strtolower(trim($provA)) === mb_strtolower(trim($provB))) {
            return true;
        }

        return false;
    }
}
