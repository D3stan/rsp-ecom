<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Builder;

/**
 * Net revenue of a set of orders (admin dashboard, order list KPIs): what the shop keeps.
 *
 *   orders with a captured payment that are not cancelled  ->  sum of their totals
 *   minus the refunds that succeeded on those same orders
 *
 * A cancelled order counts for nothing, refunded or not (its money is owed back or already returned).
 */
final class Revenue
{
    /**
     * @param  Builder<Order>  $orders  the period (or any other filter) to sum
     */
    public static function net(Builder $orders): float
    {
        $kept = (clone $orders)
            ->whereIn('payment_status', Order::CAPTURED_PAYMENT_STATUSES)
            ->where('status', '!=', 'cancelled');

        $grossCents = (int) round((float) (clone $kept)->sum('total_amount') * 100);

        $refundedCents = (int) Refund::query()
            ->where('status', Refund::STATUS_SUCCEEDED)
            ->whereIn('order_id', (clone $kept)->select('orders.id'))
            ->sum('amount_cents');

        return ($grossCents - $refundedCents) / 100;
    }
}
