<?php

namespace App\Shipping\Support;

use Carbon\CarbonImmutable;

/**
 * Italian working days for pickups: Monday to Friday except national holidays (incl. Easter Monday).
 * shortcut: carrier-specific closures (e.g. 24 and 31 December at one carrier) are not modelled; the carrier rejects those
 * dates and the admin sees its message. Upgrade: read the carrier's own settings endpoint (earliest pickup date + holidays) where it has one.
 */
final class NextWorkingDay
{
    private const FIXED = ['01-01', '01-06', '04-25', '05-01', '06-02', '08-15', '11-01', '12-08', '12-25', '12-26'];

    public static function after(CarbonImmutable $from): CarbonImmutable
    {
        $day = $from->startOfDay()->addDay();

        while (! self::isWorkingDay($day)) {
            $day = $day->addDay();
        }

        return $day;
    }

    /** What a pickup request may ask for: a working day after today. */
    public static function isFutureWorkingDay(string $date): bool
    {
        $day = CarbonImmutable::parse($date);

        return $day->greaterThan(now()->startOfDay()) && self::isWorkingDay($day);
    }

    public static function isWorkingDay(CarbonImmutable $day): bool
    {
        return ! $day->isWeekend()
            && ! in_array($day->format('m-d'), self::FIXED, true)
            && $day->toDateString() !== self::easterMonday($day->year)->toDateString();
    }

    /** Anonymous Gregorian algorithm (no ext-calendar needed). */
    private static function easterMonday(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $dayOfMonth = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $dayOfMonth)->addDay();
    }
}
