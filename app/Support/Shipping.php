<?php

namespace App\Support;

/**
 * The free-shipping rule, in one place. Shipping is the sum of the package sizes' shipping_cost (Cart), and it is
 * waived when the items total after the coupon discount reaches the setting `shipping.free_threshold` (0 = off).
 * Used by the cart page, both details pages and the checkout totals (so the snapshot, the Stripe amount, the stored
 * order and the emails follow it).
 */
final class Shipping
{
    /** The threshold in the shop currency; 0 when free shipping is off. */
    public static function freeThreshold(): float
    {
        return max(0.0, round((float) settings('shipping.free_threshold', 0), 2));
    }

    /**
     * @param  float  $itemsTotal  items total after the coupon discount (VAT-inclusive)
     * @param  float  $baseShipping  the sum of the package sizes' shipping_cost
     */
    public static function costFor(float $itemsTotal, float $baseShipping): float
    {
        $threshold = self::freeThreshold();

        if ($threshold > 0 && round($itemsTotal, 2) >= $threshold) {
            return 0.0;
        }

        return round($baseShipping, 2);
    }

    /**
     * What is still missing for free shipping: null when there is no threshold, 0 once it is reached.
     */
    public static function remainingForFree(float $itemsTotal): ?float
    {
        $threshold = self::freeThreshold();

        if ($threshold <= 0) {
            return null;
        }

        return max(0.0, round($threshold - round($itemsTotal, 2), 2));
    }
}
