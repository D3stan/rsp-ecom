<?php

namespace App\Support;

/**
 * The shop's locales, from a single source (config('app.supported_locales')).
 */
class Locales
{
    /**
     * @return array<int, string>
     */
    public static function supported(): array
    {
        return array_values(config('app.supported_locales', []));
    }

    public static function isSupported(mixed $locale): bool
    {
        return is_string($locale) && in_array($locale, self::supported(), true);
    }

    /**
     * Locale used when a request / job / user has no (supported) preference.
     */
    public static function fallback(): string
    {
        return config('app.locale');
    }
}
