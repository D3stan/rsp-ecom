---
paths:
  - app/Http/Controllers/Checkout*.php
  - app/Services/OrderCreationService.php
  - app/Services/PromotionService.php
  - app/Listeners/StripeWebhookListener.php
  - app/Support/CheckoutTotals.php
  - tests/Feature/Checkout/*.php
---
# Checkout and payments

Cart to paid order: totals, Stripe Checkout Session, webhook, order creation, snapshots, recovery link, guest claiming.
Order lifecycle, refunds and cancellations: `.ai/rules/orders-refunds.md`. Email content: `.ai/rules/email-newsletter.md`.

## Entry points
- `app/Http/Controllers/CheckoutController.php`: details pages, both Stripe session creations (user and guest), success/cancel.
- `app/Services/OrderCreationService.php`: the only place a Stripe session becomes an Order (idempotent).
- `app/Listeners/StripeWebhookListener.php`: event switch (sessions, refunds); rethrows so Stripe retries.
- `app/Support/CheckoutTotals.php`: the one totals computation (items, coupon, shipping, VAT); `Vat.php`, `Shipping.php` feed it.
- `app/Models/CheckoutSnapshot.php`: frozen charge (lines, totals, cents, address) taken before the Stripe call.
- `app/Services/Ops/StripeReconciler.php`: the daily net that compares paid Stripe sessions with orders.

## Rules
- MUST compute amounts only through `CheckoutTotals::for()`; never re-derive items/shipping/VAT in a controller, email or page (unenforced; `ShippingAndVatTest`, `CheckoutDetailsSummaryTest` catch drift).
- MUST create orders only via `OrderCreationService::createFromStripeSession()`; the webhook and the success page both call it (`StripeWebhookOrderTest`, `CheckoutSuccessOrderTest`).
- MUST keep order creation idempotent per Stripe session: DB unique index on `orders.stripe_checkout_session_id` plus a row lock; no event-id dedupe table exists, none is needed (`OrderCreationServiceTest` same session twice, lost race).
- NEVER swallow an exception in webhook handling: log critical and rethrow (non-2xx makes Stripe retry); a move the order lifecycle refuses is the exception, it is logged and must not fail the webhook (`StripeAsyncEventsTest`).
- NEVER trust the success-page `session_id` as proof of ownership: show the owner-only data to the owner or a valid signed URL only (`tests/Feature/Security/CheckoutSuccessPrivacyTest.php`).
- NEVER add Cashier routes or call `Cashier::routes()`; `Cashier::ignoreRoutes()` stays so `/stripe/payment/{id}` does not exist (unenforced).
- NEVER accept webhooks unverified in production: no `STRIPE_WEBHOOK_SECRET` makes the listener refuse every event (`tests/Feature/Security/StripeWebhookSecurityTest.php`).
- MUST commit the snapshot before the Stripe call and delete it if session creation throws; the order is built from the snapshot, not the live cart (`CheckoutSnapshotTest`).
- MUST put `source`, `snapshot_id` and `cart_id`/`guest_session_id` in session metadata; `belongsToShop()` ignores sessions without them (`OrderCreationServiceTest`).
- MUST NOT change what the customer is charged after the session exists; prices are refreshed with `Cart::refreshPrices()` before the snapshot (unenforced).
- MUST keep money as VAT-inclusive 2-decimal floats in the app and integer cents only at the Stripe boundary (`round($total * 100)`, `/ 100` back); snapshot stores both `total` and `total_cents` (`CheckoutSnapshotTest` asserts `total_cents`).
- MUST read VAT rate from the order (`orders.tax_rate`) for old orders, never from the current setting (`Vat::portion`; `OrderCreationServiceTest` rate snapshot).

## How it works
1. Cart page applies a coupon: `PromotionService` validates the code against Stripe, the result is stored in session `applied_coupon`.
2. `cartCheckout` / `guestCartCheckout` validate the form, refresh prices, check min amount (`orders.min_amount`) and stock, compute `CheckoutTotals`, capture a `CheckoutSnapshot`, then create a Stripe Checkout Session for ONE charge of the cart total (no per-item Stripe line items; guest uses the raw client, user uses Cashier `checkoutCharge`).
3. The snapshot is bound to the session id afterwards; a bind failure is tolerated because metadata `snapshot_id` lets order creation bind it later.
4. Stripe calls `POST stripe/webhook` (no web middleware, CSRF-exempt): `checkout.session.completed` (paid or unpaid) and `async_payment_succeeded` create or upgrade the order; `async_payment_failed` and `expired` close an unpaid one.
5. Delayed methods (SEPA) create a pending order on `unpaid`; the later event flips payment to succeeded, moves it to processing and deducts stock.
6. The success page calls the same service with the retrieved session, so whichever of webhook or redirect arrives first wins and the other finds the order.
7. The cart is cleared after payment only if its content hash still equals the snapshot's.
8. Abandoned checkouts: `expired` fires the reminder; its signed link (`checkout.recover`, 7 days) rebuilds the cart at today's prices and stock via `CheckoutRecoveryController`; unavailable lines are skipped.
9. Guest orders carry `guest_email`; on `Login`/`Verified` of a verified user with that email (case-insensitive), `User::claimGuestOrders()` sets `user_id` (`ClaimGuestOrders`).

## Traps
- Order is created on `unpaid` sessions on purpose (async payment methods); check `payment_status`, not existence, before treating an order as paid.
- Free shipping is judged on items AFTER the coupon discount (`Shipping::costFor`); setting `shipping.free_threshold` of 0 means off, the default is 1000.
- A percentage coupon is recomputed from the current items total; fixed ones are capped at it. The charged amount comes from the snapshot, not from Stripe's coupon.
- `tax.prices_include_tax` off means no VAT line and nothing added on top; VAT is never added to the charge.
- Paid amount differing from `snapshot.total_cents` is logged as an error, not rejected (the order keeps the snapshot totals).
- Stripe metadata values are capped at 500 chars (`metadataValue()`); long descriptions and addresses are truncated there.
- `stripe_secret_key` in the `settings` table is a legacy fallback; the env key wins (`settings:purge-legacy-secrets`).
- Success page falls back to a temporary summary built from Stripe line items if order creation fails; it is not an order.
- The coupon is validated against Stripe twice: when applied in the cart and again right before charging (`assertCouponStillValid` in `CheckoutController`); a code that is no longer valid is dropped and the customer sent back. Enforced by `tests/Feature/Checkout/CouponRecheckAtPaymentTest.php`. The recorded `coupon_code` comes from the session, never the form field.
- Coupons are created in the Stripe Dashboard (no admin page). The shop never passes the code to Stripe, so `PromotionService` counts usage itself: paid orders carrying the code plus Stripe's own `times_redeemed`. Honoured: coupon and code expiry, coupon-wide and per-code limits, minimum amount (items total, before discount), first order only (account or email with a paid order). Refused: codes limited to Stripe customers or products. Enforced by `tests/Feature/Checkout/PromotionCodeRulesTest.php`.
- Usage is counted only once an order is paid, so two checkouts paying at the same moment can both take the last use (`shortcut:` in `PromotionService`).

## Operate
- `php artisan ops:reconcile-stripe --hours=48`: scheduled daily 07:00 (`routes/console.php`); alerts on paid sessions with no order or mismatches. Needs the Stripe API.
- `php artisan stripe:check-config`: verifies keys; `ops:check-invariants` (every 15 min) checks order/payment consistency.
- Env: `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_WEBHOOK_TOLERANCE`. Dashboard webhook URL: `/stripe/webhook`.
- Settings keys: `tax.rate`, `tax.prices_include_tax`, `shipping.free_threshold`, `orders.min_amount`, `orders.max_qty_per_line` (`config/settings.php`).
- Snapshots are pruned (`model:prune`): unconsumed after 7 days, consumed after 90.
- Local: `stripe listen --forward-to localhost/stripe/webhook`, put its `whsec_` in `.env`, pay with 4242 4242 4242 4242.

## Tests
- `tests/Feature/Checkout/`: totals, VAT, free-shipping threshold, details summary, guest/auth checkout, saved addresses, pending success page.
- `tests/Feature/Orders/StripeWebhookOrderTest.php`, `StripeAsyncEventsTest.php`, `OrderCreationServiceTest.php`: webhook, idempotency, async payments.
- `tests/Feature/Orders/CheckoutSnapshotTest.php`, `CheckoutSuccessOrderTest.php`: snapshot lifecycle, success-page order creation.
- `tests/Feature/Security/CheckoutSuccessPrivacyTest.php` (success-page ownership), `StripeWebhookSecurityTest.php` (signature, secret required).
- `tests/Feature/Ops/StripeReconciliationTest.php`: reconciliation alerts. Fixtures: `tests/Feature/Orders/Support/BuildsStripeOrderFixtures.php`.
- `tests/Feature/Newsletter/AbandonedCheckoutTest.php`: reminder and recovery link.
