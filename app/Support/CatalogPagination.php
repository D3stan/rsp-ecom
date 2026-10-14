<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Page size of the storefront product lists: the `catalog.per_page` setting, optionally overridden by a
 * numeric `per_page` query value, always clamped to the allowed range (an unbounded value is a cheap DoS).
 */
final class CatalogPagination
{
    public const MIN = 6;

    public const MAX = 60;

    public static function perPage(Request $request): int
    {
        $requested = $request->query('per_page');

        if (is_string($requested) && preg_match('/^-?\d{1,9}$/', $requested) === 1) {
            return self::clamp((int) $requested);
        }

        return self::clamp((int) settings('catalog.per_page', 12));
    }

    private static function clamp(int $value): int
    {
        return max(self::MIN, min(self::MAX, $value));
    }
}
