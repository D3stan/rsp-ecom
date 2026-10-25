---
domain: catalog-and-inventory
version: 1.0.0
budget_lines: 150
deep_reference: docs/archive/plans/2026-03-06-product-variants-design.md
---

# Catalog and Inventory Domain Specification

## 1. Scope & Responsibility

Governs product catalog structure (products, variants, categories, sizes), pricing tiers, inventory tracking, and stock movement bookkeeping. Guarantees atomic stock reservations, deadlock-free deductions, idempotent cancellation and refund restocking, and automated back-in-stock notifications.

Key domain responsibilities include:
- Product and variant hierarchy, attributes, and SKU generation.
- Source-of-truth inventory management across multi-variant merchandise.
- Atomic stock decrementing and oversell tracking for paid checkouts.
- Idempotent stock restoration on order cancellation and partial returns.
- Back-in-stock event dispatching and restock notification cooldowns.

## 2. Core Invariants

- **Variant as Inventory Source of Truth**: `product_variants.stock_quantity` is the sole source of truth for stock levels. The parent attribute `products.stock_quantity` is a legacy synchronized mirror reflecting the default variant's stock (`Product::syncStockMirror()`).
- **Atomic Stock Deduction**: Inventory deduction (`StockService::deductForOrder`) executes atomically using a conditional UPDATE on `orders.stock_deducted_at`. Deadlocks between concurrent multi-item checkouts are prevented by locking variant rows in ascending primary key order (`lockVariants()`).
- **No Refusal on Paid Overselling**: A paid order is never cancelled or rejected due to insufficient stock. Available units are deducted down to zero; any shortfall is recorded in `order_items.oversold_qty`, `orders.has_oversold_items` is set to `true`, and an immediate alert is dispatched to administrators.
- **Restock Idempotency on Cancellation**: `StockService::restoreForOrder()` claims `orders.stock_restored_at` conditionally. Units already returned via prior refunds (`order_items.restocked_qty`) or units never deducted (`oversold_qty`) are strictly subtracted to prevent inventory inflation:
  $$\text{Units Restored} = \max\left(0, \text{quantity} - \text{oversold\_qty} - \text{restocked\_qty}\right)$$
- **Refund Restock Deduplication**: Partial line restocking (`StockService::restoreItems()`) requires a unique `idempotencyKey` recorded in `stock_restorations`. Replayed or duplicate refund attempts are ignored.
- **Variant Attribute Fallback**: Line items draw price, compare price, and SKU from their assigned `product_variant_id`. When referencing a base product without an explicit variant, values fall back to the product's default variant (`is_default = true`).
- **Slug Uniqueness**: Product and category URL slugs are generated via `Str::slug` and enforced unique by database unique indices (`products_slug_unique`, `categories_slug_unique`). Collision handling must prompt user correction or resolve duplicates.
- **Restock Cooldown**: Back-in-stock notifications (`RestockNotification`) enforce a 7-day cooldown (`COOLDOWN_DAYS = 7`) per user and variant to prevent customer notification spam.

## 3. State Machines & Transitions

### Product & Variant Status Lifecycle
- Variant Status: `draft` -> `active` <-> `inactive` / `archived`
- Product Inventory State:
  - `in_stock`: `stock_quantity > 0` and status is `active`.
  - `low_stock`: `stock_quantity <= low_stock_threshold` and `stock_quantity > 0`.
  - `out_of_stock`: `stock_quantity <= 0`.

### Inventory Order Lifecycle
```
[ Checkout Succeeded ]
         │
         ▼
[ StockService::deductForOrder ] ──(Claims orders.stock_deducted_at via conditional UPDATE)
         │
         ├─► [ Units Available ] ──────► Variants decremented; products.stock_quantity mirrored
         │
         └─► [ Units Short / 0 ] ──────► order_items.oversold_qty recorded; admin alerted
         │
         ▼
    (Post-Order Outcomes)
         │
         ├─► [ Partial Refund / Return ] ──► StockService::restoreItems() (idempotency key claimed)
         │
         └─► [ Full Order Cancellation ] ──► StockService::restoreForOrder() (claims stock_restored_at)
```

### Back-in-Stock Notification Lifecycle
1. Trigger: Eloquent variant mutation triggers `ProductVariantObserver::updated` or `StockService::announceIfRestocked()`.
2. Qualification: Evaluated only if variant transitioned from unsellable (`stock <= 0` or inactive) to sellable (`stock > 0` and active).
3. Wishlist Boundary Check: If other variants of the product are already sellable, wishlist notifications are suppressed (wishlists target products, not individual variants).
4. Dispatch: `VariantRestocked` event is dispatched after transaction commit, queueing customer restock notifications.

## 4. Key Contracts & Entry Points

- `App\Services\StockService::deductForOrder(Order $order): bool`
  Deducts purchased quantities from variant stock upon payment confirmation. Returns false if already deducted.
- `App\Services\StockService::restoreForOrder(Order $order): bool`
  Restores deducted stock upon order cancellation, respecting previously refunded units.
- `App\Services\StockService::restoreItems(Order $order, array $qtyByItem, string $idempotencyKey): array`
  Restores returned items for partial refunds under an idempotent key.
- `App\Services\StockService::adjustVariantStock(int $variantId, int $delta): ?array`
  Adjusts inventory for manual stock receipts or audits; clamps final stock level at `0`.
- `App\Services\StockService::announceIfRestocked(int $variantId, int $stockBefore, int $stockAfter, bool $wasActive, bool $isActive): bool`
  Validates restock thresholds and dispatches `VariantRestocked` event.
- `App\Observers\ProductVariantObserver`
  Monitors variant model updates to automatically announce restock events on Eloquent saves.
- `App\Models\Product::syncStockMirror(): int`
  Recalculates and persists the default variant's stock into `products.stock_quantity`.

## 5. External Boundaries & Failure Modes

- **High-Concurrency Checkout Races**: Multiple customers purchasing the last available unit simultaneously succeed at payment capture; the first transaction takes stock, and the subsequent transaction flags oversold quantities without crashing checkout.
- **Direct Query-Builder Stock Bypasses**: Code executing bulk updates via direct SQL queries (`DB::table('product_variants')->update(...)`) bypasses `ProductVariantObserver`. Such operations must manually trigger `StockService::announceIfRestocked()`.
- **Negative Stock Clamping**: Stock levels cannot be decremented below zero through customer orders or manual adjustments. Attempted negative deltas are clamped at zero and logged as warnings.
- **Slug Collisions on Bulk Import or Duplicate Titles**: Slug collisions throw PDO exception `23000` with `products_slug_unique`. Admin controllers catch this exception and provide user validation errors.
