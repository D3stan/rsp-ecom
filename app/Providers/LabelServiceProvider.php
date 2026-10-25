<?php

namespace App\Providers;

use App\Shipping\Contracts\ShippingProviderManifest;
use App\Shipping\Labels\LabelManager;
use Illuminate\Support\ServiceProvider;

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
