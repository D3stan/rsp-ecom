<?php

namespace App\Support;

/**
 * The VAT portion of a VAT-inclusive amount. One computation for the checkout pages, the stored order
 * (orders.tax_amount / orders.tax_rate), the emails and the order pages: they can never disagree.
 *
 * Source: settings `tax.rate` and `tax.prices_include_tax` (.ai/rules/settings.md). Shop prices are VAT-inclusive,
 * so nothing is added on top of what the customer pays; when `tax.prices_include_tax` is off there is no portion to
 * extract (no VAT is added to the charge either) and the result has no rate, so no "of which VAT" line is shown.
 */
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

    /**
     * The VAT contained in a gross amount for a given rate in percent (the rate stored on the order, so a later
     * change of the shop rate never rewrites history).
     */
    public static function portion(float $grossTotal, float $rate): float
    {
        $fraction = $rate / 100;

        return round(max(0.0, $grossTotal) * $fraction / (1 + $fraction), 2);
    }
}
