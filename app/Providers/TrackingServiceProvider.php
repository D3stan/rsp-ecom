<?php

namespace App\Providers;

use App\Shipping\Contracts\ShippingProviderManifest;
use App\Shipping\Tracking\TrackingManager;
use Illuminate\Support\ServiceProvider;

class TrackingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(TrackingManager::class, function (TrackingManager $manager) {
            foreach ((array) config('shipping.manifests', []) as $manifest) {
                if (! is_string($manifest) || ! is_subclass_of($manifest, ShippingProviderManifest::class) || ! $manifest::hasTracking()) {
                    continue;
                }

                $name = $manifest::name();
                $manager->extend($name, fn () => $manifest::createTrackingDriver($manager->driverConfig($name)));
            }
        });
    }
}
