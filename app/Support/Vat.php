<?php

namespace App\Support;

final class Vat
{
    /**
     * @param  float  $grossTotal  what the customer pays: items after any discount + shipping
     * @return array{rate: float|null, amount: float} rate in percent (null: no VAT breakdown applies)
     */
    public static function includedIn(float $grossTotal): array
    {
        if (! (bool) settings('tax.prices_include_tax', true)) {
            return ['rate' => null, 'amount' => 0.0];
        }

        $rate = round((float) settings('tax.rate', 22), 2);

        return ['rate' => $rate, 'amount' => self::portion($grossTotal, $rate)];
    }

    public static function portion(float $grossTotal, float $rate): float
    {
        $fraction = $rate / 100;

        return round(max(0.0, $grossTotal) * $fraction / (1 + $fraction), 2);
    }
}
