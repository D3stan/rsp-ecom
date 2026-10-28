---
paths:
  - app/Services/StockService.php
  - app/Services/MarkdownRenderer.php
  - app/Models/Product.php
  - app/Models/ProductVariant.php
  - app/Http/Controllers/Admin/ProductController.php
---
# Catalog and inventory

Products, variants, images, stock bookkeeping, back-in-stock emails, GPSR fields and markdown descriptions. Does not cover checkout or payments (`.ai/rules/checkout-payments.md`).

## Entry points
- `app/Services/StockService.php`: the only place that moves stock for orders, refunds and manual corrections.
- `app/Observers/ProductVariantObserver.php`: announces restocks for every Eloquent write to a variant.
- `app/Listeners/QueueRestockNotifications.php`: recipients, cooldown, cart/wishlist rules; `app/Jobs/SendRestockEmail.php` sends.
- `app/Http/Controllers/Admin/ProductController.php`: create/update with variants, image upload, `quickStock`.
- `app/Services/MarkdownRenderer.php`: the only markdown code; `resources/js/components/product/RichText.tsx` just prints its HTML.
- `app/Http/Controllers/ProductsController.php`: storefront page, builds the `compliance` block and description props.

## Rules
- MUST treat `product_variants.stock_quantity` as the source of truth. `products.price`/`stock_quantity` are a legacy mirror (price copied from the default variant on admin save, stock via `Product::syncStockMirror()`); NEVER read them for a purchase decision. Unenforced.
- MUST change stock only through `StockService`. NEVER write `stock_quantity` with a query-builder UPDATE; if unavoidable call `announceIfRestocked()` yourself (no model events fire). Enforced by `tests/Feature/Inventory/BackInStockTest.php` (any-code-path restock case).
- NEVER reject a paid order for lack of stock: deduct what exists, record the rest in `order_items.oversold_qty`. Enforced by `tests/Feature/Orders/OrderStockTest.php`.
- MUST keep deduct/restore idempotent (`orders.stock_deducted_at`/`stock_restored_at` claimed by conditional UPDATE; refunds by `stock_restorations.idempotency_key`). Unenforced by a dedicated test; `tests/Feature/Orders/RefundTest.php` touches the refund path.
- MUST lock variant rows `ORDER BY id` (`lockForUpdate`) inside a transaction. Admin stock edits are deltas against the loaded value, clamped at 0. Enforced by `tests/Feature/Admin/ProductStockConcurrencyTest.php`.
- Product admin form MUST send at least one variant (`variants` required, min 1); the first (or flagged) one becomes `is_default`. Enforced by validation in `ProductController`. Code reading `defaultVariant` still needs a null guard (`syncStockMirror` returns null without one).
- NEVER pass an already-encoded string as variant `images`; the column is cast `array`, so it double-encodes to the JSON string `"[]"`. Enforced by `tests/Feature/Catalog/ProductVariantImagesTest.php` (read path falls back to default image).
- NEVER relax `html_input: strip`, `allow_unsafe_links: false` or the image/link allowlists in `MarkdownRenderer`. Enforced by `tests/Feature/Catalog/MarkdownRendererTest.php`.
- NEVER render description markdown on the client; use `description_html` from the server. Unenforced.
- MUST keep `safety_information` max 5000 and `description` max `MarkdownRenderer::MAX_LENGTH`. Enforced by validation; `tests/Feature/Catalog/ProductSafetyTest.php` covers the 5000 limit.
- NEVER send back-in-stock mail to users who are opted out, inactive, unverified, or mailed for that variant within `RestockNotification::COOLDOWN_DAYS`. Enforced by `BackInStockTest.php`.

## How it works
1. Admin form, quick stock, order cancel or any Eloquent save changes a variant; the observer calls `StockService::announceIfRestocked()`.
2. A restock = was unsellable (stock 0 or inactive), now active with stock > 0. It dispatches `VariantRestocked` after commit.
3. Wishlist owners are notified only when no other variant of the product is buyable (flag `notifyWishlists`); cart owners always.
4. `QueueRestockNotifications` (queued) takes a cache lock per variant, writes one `restock_notifications` row per user (the row is the cooldown), queues `SendRestockEmail`.
5. `SendRestockEmail` re-checks opt-in, stock and email settings, mails in the language of the user's last order, sets `sent_at`.
6. Unsubscribe is a signed link: GET only shows a confirm page, POST acts (also RFC 8058 one-click, CSRF-exempt).

## Traps
- Product-level `images` and `Product::getImageUrlAttribute` fallback to `storage/products/{id}/{file}` are legacy; current files live in `storage/products/{productId}/variants/{variantId}/` and the variant is the first source.
- Default image `/images/product.png` is resolved twice: server (`ProductVariant::getImageUrlsAttribute`) and client `fallbackToDefaultImage` in `resources/js/lib/utils.ts` for broken URLs. Description images live in `products/descriptions` (`ImageUploadService`).
- Oversold is by design; cancelling restores only units actually deducted. A cancelled order moved back out of cancelled does NOT deduct again.
- Product `scopeInStock`/`scopeLowStock` read the mirror column, not variants.
- Markdown headings are demoted by 2 levels (no second h1); soft breaks render as `<br>` for legacy plain text.
- Compliance block falls back to `store.*` and `contact.support_email` when `compliance.*` is empty.

## Operate
- Settings: `inventory.low_stock_threshold`, `email.enabled`, `email.back_in_stock`, `compliance.manufacturer_name|manufacturer_address|manufacturer_email|eu_responsible_person` (`config/settings.php`).
- Restock mail needs a queue worker; `SendRestockEmail` retries 5 times with backoff.
- Per-user opt-out: `users.notify_back_in_stock` (account settings).

## Tests
- `tests/Feature/Inventory/BackInStockTest.php`: restock triggers, recipients, cooldown, locale, unsubscribe.
- `tests/Feature/Orders/OrderStockTest.php`: deduction, oversold, restore on cancel.
- `tests/Feature/Admin/ProductStockConcurrencyTest.php`: delta semantics, clamp, stale-form warning.
- `tests/Feature/Catalog/`: markdown, images, safety/compliance, description props, foreign keys.
- `tests/Support/CatalogFixtures.php`: use for product + default variant setup.
