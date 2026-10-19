<?php

namespace App\Shipping\Contracts;

use App\Shipping\Labels\Contracts\LabelProvider;
use App\Shipping\Rates\Contracts\RateProvider;
use App\Shipping\Tracking\Contracts\TrackingProvider;

/**
 * Self-contained descriptor for an external shipping provider module (App\Shipping\Providers\<Name>).
 *
 * Adding or removing a provider requires only its folder under app/Shipping/Providers/ and listing
 * its manifest class in config('shipping.manifests').
 */
interface ShippingProviderManifest
{
    /**
     * Unique driver identifier stored on orders.tracking_driver and shipments.driver.
     */
    public static function name(): string;

    /**
     * Whether this provider implements carrier tracking.
     */
    public static function hasTracking(): bool;

    /**
     * Whether this provider implements label creation.
     */
    public static function hasLabels(): bool;

    /**
     * Whether this provider implements live rate calculation.
     */
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
