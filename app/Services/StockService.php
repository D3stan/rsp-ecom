<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Stock bookkeeping of paid orders. product_variants.stock_quantity is the source of truth; the legacy mirror
 * products.stock_quantity follows the default variant through Product::syncStockMirror().
 *
 * A paid order is never rejected for lack of stock (the customer already paid): the shortfall is recorded per line in
 * order_items.oversold_qty and on orders.has_oversold_items, and the shop owner is alerted. Stock never goes below zero
 * through an order.
 *
 * Idempotent through orders.stock_deducted_at / stock_restored_at, claimed with a conditional UPDATE, so replays and
 * concurrent callers deduct and restore exactly once. Policy: an order that is cancelled gets its stock back
 * once; moving it out of "cancelled" later does NOT deduct again (an admin who reopens a cancelled order adjusts
 * the stock by hand, as for any other manual correction).
 *
 * Call inside the caller's transaction when there is one; each method also opens its own (nested) transaction.
 */
class StockService
{
    /**
     * Take the quantities of a paid order from the stock. Returns true when this call did it (false: already done).
     */
    public function deductForOrder(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            $now = now();

            $claimed = Order::query()->whereKey($order->id)->whereNull('stock_deducted_at')->update(['stock_deducted_at' => $now]);

            if (! $claimed) {
                return false;
            }

            $items = OrderItem::query()->where('order_id', $order->id)->orderBy('id')->get();
            $variantIds = $this->variantIdsByItem($items);
            $variants = $this->lockVariants($variantIds);

            $available = $variants->map(fn (ProductVariant $variant) => max(0, (int) $variant->stock_quantity))->all();
            $taken = [];
            $oversold = false;

            foreach ($items as $item) {
                $variantId = $variantIds[$item->id] ?? null;

                if ($variantId === null || ! isset($available[$variantId])) {
                    Log::warning('Stock not deducted for an order line: no variant to take it from', [
                        'order_id' => $order->id,
                        'order_item_id' => $item->id,
                        'product_id' => $item->product_id,
                    ]);

                    continue;
                }

                $quantity = max(0, (int) $item->quantity);
                $take = min($available[$variantId], $quantity);
                $short = $quantity - $take;

                $available[$variantId] -= $take;
                $taken[$variantId] = ($taken[$variantId] ?? 0) + $take;

                if ($short > 0) {
                    OrderItem::query()->whereKey($item->id)->update(['oversold_qty' => $short]);
                    $oversold = true;
                }
            }

            $this->applyDelta($variants, array_map(fn (int $quantity) => -$quantity, $taken));

            Order::query()->whereKey($order->id)->update(['has_oversold_items' => $oversold]);

            // Mirror in memory without marking the model dirty or changing wasChanged() of the caller
            $order->setAttribute('stock_deducted_at', $now);
            $order->setAttribute('has_oversold_items', $oversold);
            $order->syncOriginalAttributes(['stock_deducted_at', 'has_oversold_items']);

            if ($oversold) {
                Log::warning('Order sold more units than were in stock', ['order_id' => $order->id]);
            }

            return true;
        });
    }

    /**
     * Give back what deductForOrder() took (the units actually deducted, not the oversold ones). Only for an order
     * whose stock was deducted and not yet restored. Returns true when this call did it.
     */
    public function restoreForOrder(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            $now = now();

            $claimed = Order::query()
                ->whereKey($order->id)
                ->whereNotNull('stock_deducted_at')
                ->whereNull('stock_restored_at')
                ->update(['stock_restored_at' => $now]);

            if (! $claimed) {
                return false;
            }

            $items = OrderItem::query()->where('order_id', $order->id)->orderBy('id')->get();
            $variantIds = $this->variantIdsByItem($items);
            $variants = $this->lockVariants($variantIds);

            $give = [];

            foreach ($items as $item) {
                $variantId = $variantIds[$item->id] ?? null;

                if ($variantId === null || ! $variants->has($variantId)) {
                    continue;
                }

                // units already given back with a refund (restoreItems) are not given back twice
                $give[$variantId] = ($give[$variantId] ?? 0) + max(0, (int) $item->quantity - (int) $item->oversold_qty - (int) $item->restocked_qty);
            }

            $this->applyDelta($variants, $give);

            $order->setAttribute('stock_restored_at', $now);
            $order->syncOriginalAttributes(['stock_restored_at']);

            return true;
        });
    }

    /**
     * Give back units of some lines (items returned with a refund), once per $idempotencyKey (e.g. "refund:{id}"): the
     * key is claimed in stock_restorations before the stock moves, so a retried call does nothing. Only for an order
     * whose stock was deducted and not already restored by a cancellation; each line gives back at most what it took
     * (quantity - oversold) minus what earlier calls gave back (order_items.restocked_qty, which restoreForOrder also
     * respects).
     *
     * @param  array<int, int>  $qtyByItem  order item id => units to give back
     * @return array<int, int> order item id => units actually given back (empty: nothing to do, or a retry)
     */
    public function restoreItems(Order $order, array $qtyByItem, string $idempotencyKey): array
    {
        $qtyByItem = array_filter(array_map('intval', $qtyByItem), fn (int $quantity) => $quantity > 0);

        if ($qtyByItem === []) {
            return [];
        }

        return DB::transaction(function () use ($order, $qtyByItem, $idempotencyKey) {
            $state = Order::query()->whereKey($order->id)->lockForUpdate()->first(['id', 'stock_deducted_at', 'stock_restored_at']);

            if (! $state || $state->stock_deducted_at === null || $state->stock_restored_at !== null) {
                return [];
            }

            $claimed = DB::table('stock_restorations')->insertOrIgnore([
                'idempotency_key' => $idempotencyKey,
                'order_id' => $order->id,
                'quantities' => '{}',
                'created_at' => now(),
            ]);

            if (! $claimed) {
                return [];
            }

            $items = OrderItem::query()->where('order_id', $order->id)->whereIn('id', array_keys($qtyByItem))->orderBy('id')->get();
            $variantIds = $this->variantIdsByItem($items);
            $variants = $this->lockVariants($variantIds);
            $give = [];
            $done = [];

            foreach ($items as $item) {
                $variantId = $variantIds[$item->id] ?? null;

                if ($variantId === null || ! $variants->has($variantId)) {
                    continue;
                }

                $left = max(0, (int) $item->quantity - (int) $item->oversold_qty - (int) $item->restocked_qty);
                $quantity = min($left, $qtyByItem[$item->id]);

                if ($quantity <= 0) {
                    continue;
                }

                $give[$variantId] = ($give[$variantId] ?? 0) + $quantity;
                $done[$item->id] = $quantity;
                OrderItem::query()->whereKey($item->id)->increment('restocked_qty', $quantity);
            }

            $this->applyDelta($variants, $give);

            DB::table('stock_restorations')->where('idempotency_key', $idempotencyKey)->update(['quantities' => json_encode($done)]);

            return $done;
        });
    }

    /**
     * The variant each order line draws from: its own, else the default variant of its product.
     *
     * @param  Collection<int, OrderItem>  $items
     * @return array<int, int> order item id => variant id (lines without any variant are absent)
     */
    private function variantIdsByItem(Collection $items): array
    {
        $defaults = ProductVariant::query()
            ->whereIn('product_id', $items->whereNull('product_variant_id')->pluck('product_id')->filter()->unique())
            ->where('is_default', true)
            ->pluck('id', 'product_id');

        $ids = [];

        foreach ($items as $item) {
            $variantId = $item->product_variant_id ?? ($item->product_id ? $defaults->get($item->product_id) : null);

            if ($variantId !== null) {
                $ids[$item->id] = (int) $variantId;
            }
        }

        return $ids;
    }

    /**
     * Lock the variant rows in id order (a fixed order keeps concurrent orders from deadlocking).
     *
     * @param  array<int, int>  $variantIds
     * @return \Illuminate\Support\Collection<int, ProductVariant> keyed by id
     */
    private function lockVariants(array $variantIds)
    {
        return ProductVariant::query()
            ->whereIn('id', array_values(array_unique($variantIds)))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * Add a signed quantity to each variant and keep the products' stock mirror in step.
     *
     * @param  \Illuminate\Support\Collection<int, ProductVariant>  $variants
     * @param  array<int, int>  $deltas  variant id => change
     */
    private function applyDelta($variants, array $deltas): void
    {
        $productIds = [];

        foreach ($deltas as $variantId => $delta) {
            if ($delta === 0 || ! $variants->has($variantId)) {
                continue;
            }

            $variant = $variants->get($variantId);
            $variant->update(['stock_quantity' => (int) $variant->stock_quantity + $delta]);
            $productIds[$variant->product_id] = true;
        }

        foreach (Product::query()->whereIn('id', array_keys($productIds))->get() as $product) {
            $product->syncStockMirror();
        }
    }
}
