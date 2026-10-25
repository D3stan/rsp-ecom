<?php

namespace App\Shipping\Providers\Spediamo;

use App\Shipping\Auth\BearerTokenAuthenticator;
use App\Shipping\Contracts\ShippingProviderManifest;
use App\Shipping\Labels\Contracts\LabelProvider;
use App\Shipping\Rates\Contracts\RateProvider;
use App\Shipping\Tracking\Contracts\TrackingProvider;

final class SpediamoManifest implements ShippingProviderManifest
{
    public static function name(): string
    {
        return SpediamoProvider::NAME;
    }

    public static function displayName(): string
    {
        return 'Spediamo';
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

    public static function createTrackingDriver(array $config): ?TrackingProvider
    {
        return new SpediamoProvider($config);
    }

    public static function createLabelDriver(array $config): ?LabelProvider
    {
        return new SpediamoLabelProvider($config);
    }

    public static function createRateDriver(array $config): ?RateProvider
    {
        return new SpediamoRateProvider(
            $config,
            new BearerTokenAuthenticator((string) ($config['api_key'] ?? $config['token'] ?? '')),
        );
    }
}
