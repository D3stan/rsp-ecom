<?php

namespace App\Support;

use App\Models\Cart;

/**
 * What the customer pays, in one place (the cart page, both details pages and both Stripe checkouts):
 * items (VAT-inclusive) - coupon discount + shipping. Shipping is the sum of the package sizes' own shipping_cost,
 * waived once the items after the discount reach the free-shipping threshold (Shipping). VAT is the portion
 * contained in the total.
 */
final class CheckoutTotals
{
    /**
     * @param  array<string, mixed>|null  $appliedCoupon  the `applied_coupon` session entry
     * @return array{items: float, shipping: float, discount: float, total: float, vat: array{rate: float|null, amount: float}, free_shipping_remaining: float|null}
     */
    public static function for(Cart $cart, ?array $appliedCoupon): array
    {
        $items = round((float) $cart->cartItems->sum(fn ($item) => $item->price * $item->quantity), 2);
        $discount = self::couponDiscount($items, $appliedCoupon);
        $shipping = round($cart->shippingCostFor($discount), 2);
        $total = round(max(0, $items + $shipping - $discount), 2);

        return [
            'items' => $items,
            'shipping' => $shipping,
            'discount' => $discount,
            'total' => $total,
            'vat' => Vat::includedIn($total),
            'free_shipping_remaining' => Shipping::remainingForFree($items - $discount),
        ];
    }

    /**
     * Discount of the applied coupon on a VAT-inclusive items total. Percentage coupons are recalculated on the
     * current total; fixed ones never exceed it.
     *
     * @param  array<string, mixed>|null  $appliedCoupon
     */
    public static function couponDiscount(float $itemsTotal, ?array $appliedCoupon): float
    {
        if (! $appliedCoupon) {
            return 0.0;
        }

        if (($appliedCoupon['discount_type'] ?? null) === 'percentage' && ($appliedCoupon['discount_percentage'] ?? 0) > 0) {
            return round($itemsTotal * ($appliedCoupon['discount_percentage'] / 100), 2);
        }

        return round((float) min($appliedCoupon['discount_amount'] ?? 0, $itemsTotal), 2);
    }
}
