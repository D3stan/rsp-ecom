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

    /**
     * The frontend dictionary of a locale (lang/{locale}.json) with English underneath: a key the locale lacks is
     * served in English instead of reaching the page as the raw key. Deep merge, so nested groups ("cart": {...})
     * are filled key by key; the payload stays the size of one dictionary.
     *
     * @return array<string, mixed>
     */
    public static function translations(string $locale): array
    {
        $english = self::readDictionary('en');

        if ($locale === 'en') {
            return $english;
        }

        return array_replace_recursive($english, self::readDictionary($locale));
    }

    /**
     * @return array<string, mixed>
     */
    private static function readDictionary(string $locale): array
    {
        $path = lang_path("{$locale}.json");

        if (! is_file($path)) {
            return [];
        }

        $strings = json_decode((string) file_get_contents($path), true);

        return is_array($strings) ? $strings : [];
    }
}
