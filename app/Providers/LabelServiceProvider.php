<?php

namespace App\Providers;

use App\Shipping\Contracts\ShippingProviderManifest;
use App\Shipping\Labels\LabelManager;
use Illuminate\Support\ServiceProvider;

/**
 * Registers label adapters declared in config('shipping.manifests') with LabelManager.
 * Adding or removing a provider requires only its folder in app/Shipping/Providers/ and its manifest
 * entry in config/shipping.php: this service provider never names a concrete carrier, and nothing is
 * resolved at all while label creation is off.
 */
class LabelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(LabelManager::class, function (LabelManager $manager) {
            foreach ((array) config('shipping.manifests', []) as $manifest) {
                if (! is_string($manifest) || ! is_subclass_of($manifest, ShippingProviderManifest::class) || ! $manifest::hasLabels()) {
                    continue;
                }

                $name = $manifest::name();
                $manager->extend($name, fn () => $manifest::createLabelDriver($manager->driverConfig($name)));
            }
        });
    }
}
