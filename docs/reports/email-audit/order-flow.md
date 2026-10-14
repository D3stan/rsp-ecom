# Category A - Order flow & data integrity: discovery log

Line numbers refer to the code as it was BEFORE the Category A changes (HEAD `b7ac81b`).

## Baseline

`vendor/bin/pest` before any change: 92 passed, 1 skipped (`checkout requires authentication`), 346 assertions.

## Discovery checklist results

### 1. Order creation sites
| Site | Used by | Notes |
|---|---|---|
| `StripeWebhookListener.php:188` `Order::create` | webhook (live) | subtotal = sum of cart items; shipping from cart; discount only from `total_details.amount_discount` when a coupon code is in metadata (`isset` is true even for 0, so coupon orders were stored with discount 0); items saved WITHOUT `size_id` / `product_variant_id`; country fallback `US`; swallowed every exception (HTTP 200, no Stripe retry); no cart => `Log::error` + return (paid order lost) |
| `CheckoutController.php:373` `Order::create` (`createOrderFromSession`) | success page (live) | subtotal = Stripe `amount_subtotal` (== total, lump-sum line item, i.e. includes shipping minus discount); no `discount_amount` / `coupon_code`; items without `size_id` / `product_variant_id`; country fallback `IT` (and the Stripe fallback could never win because `'IT'` is truthy); unpaid sessions created as `processing`; fell back to Stripe line items when the cart was gone |
| `CheckoutService.php:246` (`createOrderFromSession`) | `/checkout/session` -> `createCheckoutSession` | writes columns that do not exist (`shipping_cost`, `billing_address`, `shipping_address`), omits NOT NULL `billing_address_id` / `shipping_address_id` => always threw AFTER the Stripe session was created. Unreachable from the UI (`Checkout/Index.tsx` is not rendered by any route) |
| `CheckoutService.php:531, 584, 639` | nobody (no callers in `app/`, `routes/`, `tests/`) | `createGuestOrderFromCartSession`, `createOrderFromCheckout`, `createGuestOrderFromCheckout`: omit `billing_address_id`, `product_name`, wrong currency (`usd`), invented order numbers, hard-coded 8.75% tax |
| `Order::createFromCart` (`Order.php:233`) | nobody | dead, no payment fields |
| `Console/Commands/SendTestOrderConfirmation.php:194` | artisan test command | out of scope |

### 2. Existing duplicates
Cannot be read from production from here. The new migration `2026_10_13_100000_add_unique_index_to_orders_stripe_checkout_session_id` runs the check itself (`GROUP BY stripe_checkout_session_id HAVING COUNT(*) > 1`) and throws a `RuntimeException` listing the sessions, WITHOUT touching data, if any exist. Before deploying, the owner should run that SELECT on the production database.

### 3. DB driver
`config/database.php` default and `.env.example` => `sqlite`; `phpunit.xml` => in-memory sqlite. `docker-compose.yml` has no DB service and only the `storage/app` and `storage/logs` volumes, so production either uses an external MySQL/Postgres through Dokploy env vars or keeps `database/database.sqlite` inside the container filesystem (would be lost on redeploy) - to be confirmed by the owner. The migration uses only portable constructs (`Blueprint::unique`, `havingRaw('COUNT(*) > 1')`, `string(...)->default()`); NULLs are allowed multiple times in unique indexes by sqlite, MySQL and Postgres. Verified up / down / up on a throw-away sqlite file; MySQL/Postgres could not be run here.

### 4. `cart_items`
Columns: `id, cart_id, product_id, product_variant_id (nullable, FK), quantity, price, size_id (nullable, FK), timestamps`; `UNIQUE(cart_id, product_id)` (a product can appear only once per cart). `CartItem::getTotalAttribute` = quantity x price. `Cart::getShippingCostAttribute` = sum of each item's `size.shipping_cost` (NOT multiplied by quantity). `order_items` has `product_id, product_variant_id, product_name (NOT NULL), size_id, quantity, price, total`.

### 5. Stripe session creation / metadata
- `CheckoutController::cartCheckout` (registered): `checkoutCharge` with ONE lump-sum line item "Cart Purchase" = items + shipping - discount. Metadata: `cart_id, user_id, user_email, type, description, shipping_address, customer_phone, order_notes, coupon_code`.
- `CheckoutController::guestCartCheckout`: raw `sessions->create`, same lump-sum line item. Metadata: `guest_session_id, guest_email, type, description, shipping_address, customer_phone, order_notes, coupon_code`.
- `CheckoutService::createCheckoutSession` / `createGuestCheckoutSession` (legacy routes `/checkout/session`, `/guest/checkout/session`).
- Neither flow sent a locale. Now all four add `metadata.locale = app()->getLocale()`.
- Consequence for totals: Stripe's `total_details.amount_discount`, `amount_shipping` and `amount_tax` are 0 for the two live flows; the discount is only visible as `cart items + shipping - amount_total`.
- `payment_intent.succeeded` handler needs `metadata.checkout_session_id` on the payment intent; nothing in the code base sets it, so that handler never creates an order today.

### 6. Status literals outside the enums
Enums (create_orders migration): `status` = pending|processing|shipped|delivered|cancelled; `payment_status` = pending|processing|succeeded|failed|cancelled.
- `routes/web.php:197` `whereIn('status', ['completed', 'delivered'])` - `completed` never exists (harmless dead literal). NOT edited: routes/web.php is in Category D's file list.
- `OrdersController::update` validated `payment_status` without `cancelled` (fixed).
- `resources/js/pages/Admin/Orders/Edit.tsx:101-105` offers payment statuses `paid` and `refunded` that the DB enum does not have (a selection fails validation); `components/admin/EditOrderDialog.tsx:72-88` and `Admin/Orders/Show.tsx:92-97` lack `cancelled` colours/options. Frontend, not edited.
- `Order::cancel()` (`Order.php:163`) set only `status`; `CheckoutController::cancel` (`:615`) set only `payment_status` on ANY order found by a public session id, including paid ones.
- `Admin/DashboardController` uses only enum values; `ReviewController` uses `delivered`.
- `OrderFactory::getPaymentStatusForOrder` returns `paid`/`refunded` (unused helper, not in enum).

### 7. Stock
No stock decrement anywhere in order creation (report-only). `CartController` only validates against `stock_quantity` when adding.

### 8. Existing tests
`CheckoutTest` (class style, `WithoutMiddleware`) and `CheckoutServiceTest` (only `calculateTotals` + `retrieveCheckoutSession` signature) read; nothing depends on the removed CheckoutService methods. `test_checkout_cancel_updates_order_status` uses `Order::factory()` with a random status, so cancel must always set `payment_status = cancelled` for pending payments regardless of order status (kept).

## Changes made
- `app/Services/OrderCreationService.php` (new): `createFromStripeSession(array|object $session, array|object|null $paymentIntent = null): Order` and `retrieveSession(string): array`.
  - `DB::transaction` (3 attempts for deadlocks) with `lockForUpdate` lookup by `stripe_checkout_session_id`; existing order => returned (and upgraded to paid when a later event confirms payment); `UniqueConstraintViolationException` => re-fetch the winner (retries up to 3 times for other collisions such as order-number clashes).
  - One totals rule: total = `amount_total`; tax = `total_details.amount_tax`; subtotal/shipping from the cart (fallback: Stripe subtotal/shipping); discount = Stripe discount if > 0, else `max(0, subtotal + shipping - total)`; coupon code from metadata, blank => NULL.
  - Items copy `product_id, product_variant_id, size_id, product_name, quantity, price, total`. Without a cart: Stripe line items, else one item named after `metadata.description` (logged at `error`).
  - `locale` validated against it|en|de|es|fr (primary subtag, case-insensitive), fallback `it`.
  - Cart (items + row) deleted inside the transaction, only for paid sessions.
  - Address helpers moved here from both callers (country order fixed: metadata, then Stripe, then `IT`).
  - `EmailService::sendOrderConfirmation($order)` is called after the transaction for paid orders (new or already existing, B's layer dedupes); failures are logged, never fail order creation.
- `app/Listeners/StripeWebhookListener.php`: delegates to the service; any failure => `Log::critical` with context, then rethrow (Stripe gets non-2xx and retries).
- `app/Http/Controllers/CheckoutController.php`: `success()` delegates to the service (fallback display data kept); private creation/address helpers removed; `cancel()` only closes unpaid orders; `metadata.locale` added to both cart checkouts; clears `applied_coupon` after a paid order.
- `app/Services/CheckoutService.php`: removed the order-writing methods listed above (all broken/dead), `createCheckoutSession` no longer writes an order before payment and sends `cart_id` + `locale` metadata.
- `app/Models/Order.php`: `locale` fillable; `updating` hook closing an unpaid payment when status becomes `cancelled`.
- `app/Http/Controllers/Admin/OrdersController.php`: `payment_status` validation includes `cancelled`; bulk status update now goes through models so the hook applies.
- Migrations `2026_10_13_100000_add_unique_index_to_orders_stripe_checkout_session_id`, `2026_10_13_100100_add_locale_to_orders_table`.

## Status semantics decided
- `cancel()` / status set to `cancelled` (admin cancel, status update, bulk, hook in `Order::updating`): `payment_status` becomes `cancelled` only if it was `pending`/`processing`; a `succeeded` payment stays `succeeded` (money captured; refund is a separate step owned by Category E / out of scope); an explicit `payment_status` in the same update wins.
- `/checkout/cancel?session_id=`: only orders with a pending payment are touched (`payment_status = cancelled`, and `status = cancelled` when cancellable). Paid orders are ignored.
- A session that is not `paid` creates `status = pending`, `payment_status = processing`; it becomes `processing` / `succeeded` when a later event confirms payment.

## Out-of-scope findings (for the orchestrator)
1. `.env` contains `STRIPE_SECRET`; pre-existing `CheckoutTest::test_checkout_show_page_renders` can issue a real Stripe API call. New tests install a fake HTTP transport (`Tests\Feature\Orders\Support\FakeStripeHttpClient`) that throws on unexpected requests.
2. `routes/admin.php:18,22`: `PATCH orders/bulk-status` is declared after the resource route `orders/{order}` (update) and is shadowed by it - the bulk endpoint is probably unreachable.
3. Guest checkout shows free shipping >= 100 on the details page (`showGuestDetails`) but `guestCartCheckout` charges the full `cart->shipping_cost`.
4. `tax_amount` is 0 on every order: Stripe tax is not enabled and the line item is a lump sum. Category C must not rely on `orders.tax_amount` for the VAT line.
5. `payment_intent.succeeded` is effectively dead (see 5 above); `checkout.session.async_payment_succeeded|failed` and `checkout.session.expired` are not handled.
6. Legacy routes `checkout.session` / `checkout.guest.session` (and `Checkout/Index.tsx`, `GuestIndex.tsx`) are unreachable from the UI; they use USD, no shipping and are worth removing (routes belong to Category D).
7. Address parsing from `metadata.shipping_address` splits on `", "` and on single spaces (multi-word states or commas in street names are mis-parsed); addresses are always saved with `user_id = null`.
8. `Order` fillable lists `shipping_method` and `estimated_delivery_date`, which are not DB columns; `Order::size()` relation is bogus; `toFrontendArray()` always returns `size => null` although `order_items.size_id` is now populated (Category C may want it).
9. `routes/web.php:197`: replace `['completed', 'delivered']` with `'delivered'` (one line, left untouched).
10. No stock decrement on order creation.

## Run BEFORE deploying (unique-index migration)

The migration `2026_10_13_100000_add_unique_index_to_orders_stripe_checkout_session_id` is strict: it throws if any checkout session is linked to more than one order, and since migrations auto-run at container boot, a duplicate would block the deploy. Check first, against the production database:

```
php artisan orders:duplicate-sessions
```

The command is read-only. Exit code 0 = no duplicates, safe to deploy. Exit code 1 = it lists every duplicated session with its orders (id, number, status, payment, total, date); merge or remove the extra orders by hand (keep the one that has items and the right status), then re-run until it exits 0. Nothing is ever deleted automatically.

## Review round 1 changes

- Foreign sessions: `OrderCreationService::createFromStripeSession()` now returns `?Order`. A session is accepted for creation only if `metadata.source === 'rsp-ecom'` (`OrderCreationService::SESSION_SOURCE`, set on every session the shop creates) or it carries one of the legacy keys `cart_id` / `guest_session_id` / `user_id`. Otherwise a warning is logged and nothing is created (no addresses, no email); the webhook answers 200, the success page shows the generic fallback. An order that already exists for the session is still returned (the check applies to creation only).
- Cart mutated after checkout: sessions carry `metadata.cart_hash` (`Cart::contentHash()`: sha1 of the sorted variant/size/quantity/price lines). If the cart's current hash differs, the cart is ignored (logged at `error` with both hashes), left untouched, and the order items come from Stripe line items (named after `metadata.description` for the lump-sum line). No hash in metadata (older sessions) => previous behaviour.
- `toArray()` uses `StripeObject::toArray()` for Stripe objects; `SUPPORTED_LOCALES` stays public.

## Order integrity (checkout snapshot, stock, Stripe events)

- **Checkout snapshot** (`checkout_snapshots`, ULID): written and committed right after the totals are computed and before the Stripe call (no transaction around it); `metadata.snapshot_id` is sent to Stripe, `stripe_session_id` is bound after Stripe answers, the snapshot is deleted when Stripe throws. `OrderCreationService::createOrder` consumes it before `trustedCart` (items, charged unit prices and totals from the snapshot; VAT from the paid total); sessions without `snapshot_id` keep the old chain (cart, then Stripe line items). The cart is cleared only if its `contentHash()` still equals the snapshot's `cart_hash`. `model:prune` (daily): unconsumed > 7 days, consumed > 90 days.
- **Price rule**: a cart line is charged at the current catalogue price of ITS OWN variant (`CartItem::current_price`: variant price, else the product's default variant); `Cart::refreshPrices()` aligns the lines on the cart page, both details pages and both checkouts. Changed lines are flagged `price_changed`/`previous_price` in the snapshot. (Before, every line was overwritten with `products.price`, the default variant's price.)
- **Stock** (`StockService`): deducted inside the order transaction when an order is created paid or becomes paid (`orders.stock_deducted_at`, claimed with a conditional UPDATE, variants locked in id order); never rejects a paid order: the shortfall goes to `order_items.oversold_qty` / `orders.has_oversold_items`, stock stops at zero and an internal alert email (`admin_oversold_order`) is queued once. Restored by `OrderObserver` on status -> `cancelled` only if deducted and not yet restored (`stock_restored_at`), giving back only the units actually deducted. Moving a cancelled order back to an open status does NOT deduct again (adjust the stock by hand). Legacy paid orders (no `stock_deducted_at`) never restore anything. `products.stock_quantity` is a mirror of the default variant (`Product::syncStockMirror()`).
- **Stripe events**: `checkout.session.completed` (paid, or unpaid = pending order), `async_payment_succeeded` (pays the pending order), `async_payment_failed` / `expired` (close a still-pending order only, retire the snapshot). `payment_intent.succeeded` was removed.
