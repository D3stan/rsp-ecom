<?php

namespace App\Shipping\Contracts;

use App\Shipping\Labels\Contracts\LabelProvider;
use App\Shipping\Rates\Contracts\RateProvider;
use App\Shipping\Tracking\Contracts\TrackingProvider;

interface ShippingProviderManifest
{
    public static function name(): string;

    public static function displayName(): string;

    public static function hasTracking(): bool;

    public static function hasLabels(): bool;

    public static function hasRates(): bool;

    /**
     * Build the provider's tracking adapter from merged driver config, or null if label-only.
     *
     * @param  array<string, mixed>  $config
     */
    public static function createTrackingDriver(array $config): ?TrackingProvider;

    /**
     * Build the provider's label adapter from merged driver config, or null if tracking-only.
     *
     * @param  array<string, mixed>  $config
     */
    public static function createLabelDriver(array $config): ?LabelProvider;

    /**
     * Build the provider's rate adapter from merged driver config, or null if not supported.
     *
     * @param  array<string, mixed>  $config
     */
    public static function createRateDriver(array $config): ?RateProvider;
}
