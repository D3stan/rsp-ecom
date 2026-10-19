<?php

namespace App\Shipping\Providers\Dvaexpress;

use App\Shipping\Contracts\ShippingProviderManifest;
use App\Shipping\Labels\Contracts\LabelProvider;
use App\Shipping\Rates\Contracts\RateProvider;
use App\Shipping\Tracking\Contracts\TrackingProvider;

/**
 * Self-contained manifest for the DVA Express shipping provider module.
 */
final class DvaexpressManifest implements ShippingProviderManifest
{
    public static function name(): string
    {
        return DvaexpressProvider::NAME;
    }

    public static function hasTracking(): bool
    {
        return true;
    }

    public static function hasLabels(): bool
    {
        return true;
    }

    public static function hasRates(): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function createTrackingDriver(array $config): ?TrackingProvider
    {
        return new DvaexpressProvider($config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function createLabelDriver(array $config): ?LabelProvider
    {
        return new DvaexpressLabelProvider($config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function createRateDriver(array $config): ?RateProvider
    {
        return new DvaexpressRateProvider($config);
    }
}
