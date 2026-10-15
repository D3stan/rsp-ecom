<?php

namespace App\Services\Reviews;

use App\Models\Order;
use App\Models\OrderEmail;
use App\Models\Product;
use App\Models\Review;
use App\Services\Newsletter\MarketingConsent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Eligibility for the consented review-request email.
 *
 * An order qualifies when it was delivered at least `reviews.request_delay_days` ago, belongs to a registered
 * customer (reviews need an account), is neither cancelled nor refunded, has at least one product the customer has
 * not reviewed yet, and the customer's address has marketing consent. The structural conditions are a query so the
 * command can stream orders; consent and "has an unreviewed product" are per-order checks.
 */
class ReviewRequests
{
    /** Payment states that mean money was (partly) returned: no review request then. */
    private const REFUNDED_PAYMENT_STATUSES = ['refunded', 'partially_refunded'];

    public function __construct(private readonly MarketingConsent $consent) {}

    public function isEnabled(): bool
    {
        return (bool) settings('reviews.request_enabled', false);
    }

    public function delayDays(): int
    {
        return max(1, (int) settings('reviews.request_delay_days', 10));
    }

    /**
     * Orders that pass the structural filters (delivered long enough ago, registered customer, not cancelled or
     * refunded, no review request sent yet). Consent and unreviewed-product checks are applied per order.
     *
     * @return Builder<Order>
     */
    public function eligibleOrdersQuery(): Builder
    {
        $cutoff = now()->subDays($this->delayDays());

        return Order::query()
            ->whereNotNull('user_id')
            ->where('status', 'delivered')
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '<=', $cutoff)
            ->whereNotIn('payment_status', self::REFUNDED_PAYMENT_STATUSES)
            ->whereDoesntHave('emails', fn (Builder $query) => $query->where('type', OrderEmail::TYPE_REVIEW_REQUEST));
    }

    /**
     * Products in the order the customer has not reviewed yet (distinct, keyed by product id).
     *
     * @return Collection<int, Product>
     */
    public function unreviewedProducts(Order $order): Collection
    {
        if ($order->user_id === null) {
            return collect();
        }

        $productIds = $order->orderItems()
            ->whereNotNull('product_id')
            ->pluck('product_id')
            ->unique();

        if ($productIds->isEmpty()) {
            return collect();
        }

        $reviewed = Review::query()
            ->where('user_id', $order->user_id)
            ->whereIn('product_id', $productIds)
            ->pluck('product_id')
            ->all();

        return Product::query()
            ->whereIn('id', $productIds->diff($reviewed)->values())
            ->get()
            ->keyBy('id');
    }

    /**
     * Whether a review request may actually be sent for this order right now (consent + an unreviewed product).
     * The structural conditions are assumed already met (eligibleOrdersQuery).
     */
    public function maySend(Order $order): bool
    {
        $email = $order->getCustomerEmail();

        if ($email === null || ! $this->consent->allows($email)) {
            return false;
        }

        return $this->unreviewedProducts($order)->isNotEmpty();
    }
}
