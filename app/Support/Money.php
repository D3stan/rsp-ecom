<?php

namespace App\Support;

/**
 * Currency-aware amount in the current locale, without the intl extension (not guaranteed in the production image).
 *
 * it/de/es "1.234,56 €", fr "1 234,56 €" (narrow no-break space), en "€1,234.56".
 */
final class Money
{
    public static function format(float|string|null $amount, ?string $currency = null, ?string $locale = null): string
    {
        $currency = strtoupper($currency ?: 'EUR');
        $amount = (float) $amount;
        $locale ??= app()->getLocale();

        [$decimal, $thousands] = match ($locale) {
            'en' => ['.', ','],
            'fr' => [',', "\u{202F}"],
            default => [',', '.'],
        };

        $digits = number_format(abs($amount), 2, $decimal, $thousands);

        // Spanish does not group four-digit numbers ("1234,56 €")
        if ($locale === 'es' && abs($amount) < 10000) {
            $digits = number_format(abs($amount), 2, $decimal, '');
        }

        $symbol = ['EUR' => '€', 'USD' => '$', 'GBP' => '£'][$currency] ?? $currency;
        $sign = $amount < 0 ? '-' : '';

        if ($locale === 'en') {
            return $sign.$symbol.(mb_strlen($symbol) > 1 ? "\u{00A0}" : '').$digits;
        }

        return $sign.$digits."\u{00A0}".$symbol;
    }
}
