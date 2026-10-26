<?php

namespace App\Support;

/**
 * A cookie lifetime in the visitor's language, for the cookie banner and the cookie policy page. Carbon's cascade
 * assumes 4-week months, which turns a one-year cookie into "1 year 1 month 1 day".
 */
class CookieDuration
{
    public static function forHumans(int $minutes): string
    {
        if ($minutes <= 0) {
            return __('cookieConsent::cookies.session');
        }

        if ($minutes % 525600 === 0) {
            return self::unit('year', intdiv($minutes, 525600));
        }

        if ($minutes % 1440 === 0) {
            return self::unit('day', intdiv($minutes, 1440));
        }

        if ($minutes % 60 === 0) {
            return self::unit('hour', intdiv($minutes, 60));
        }

        return self::unit('minute', $minutes);
    }

    private static function unit(string $unit, int $amount): string
    {
        return $amount.' '.trans_choice('cookieConsent::cookies.'.$unit, $amount);
    }
}
