<?php

namespace App\Shipping\Providers\Spediamo;

use App\Shipping\Contracts\ShippingProviderManifest;
use App\Shipping\Labels\Contracts\LabelProvider;
use App\Shipping\Tracking\Contracts\TrackingProvider;

/**
 * Self-contained manifest for the Spediamo shipping platform adapter.
 */
final class SpediamoManifest implements ShippingProviderManifest
{
    public static function name(): string
    {
        return SpediamoProvider::NAME;
    }

    public static function hasTracking(): bool
    {
        return true;
    }

    public static function hasLabels(): bool
    {
        return true;
    }

    public static function createTrackingDriver(array $config): ?TrackingProvider
    {
        return new SpediamoProvider($config);
    }

    public static function createLabelDriver(array $config): ?LabelProvider
    {
        return new SpediamoLabelProvider($config);
    }
}
