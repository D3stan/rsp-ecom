<?php

use App\Settings\Settings;

if (! function_exists('settings')) {
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
