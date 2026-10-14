<?php

use App\Settings\Settings;

if (! function_exists('settings')) {
    /**
     * settings()            the Settings service
     * settings('tax.rate')  the typed value ($default when the key is not part of the catalogue or cannot be read)
     */
    function settings(?string $key = null, mixed $default = null): mixed
    {
        $settings = app(Settings::class);

        if ($key === null) {
            return $settings;
        }

        if (! $settings->registry()->has($key)) {
            return $default;
        }

        try {
            return $settings->get($key);
        } catch (Throwable) {
            return $default;
        }
    }
}
