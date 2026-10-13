# Category E - Order lifecycle emails: discovery log

Date: 2026-10-13. Line numbers refer to the tree as it was when this category started (after Waves 1; A/B changes included).

## Baseline

`vendor/bin/pest` before any Category E change: 229 passed, 1 skipped (868 assertions).

## Discovery checklist results

### 1. Where order status changes (and what happens today)

| Site | What it does | Lifecycle mail |
|---|---|---|
| `Admin/OrdersController::cancel` (`:345`) | `Order::cancel()` (only pending/processing) | cancelled, via observer |
| `Admin/OrdersController::update` (`:135`) | edit form, `status` and/or tracking; adding the first tracking number forces `shipped` | shipped (B), delivered/cancelled via observer |
| `Admin/OrdersController::updateStatus` (`:280`) | single status change | idem |
| `Admin/OrdersController::bulkUpdateStatus` (`:308`) | per-model update (A), loop | idem; `with('user')` added to avoid one query per order |
| `Admin/OrdersController::processShip` (`:417`) | sets `shipped` | shipped (B) |
| `Order::cancel/markAsProcessing/markAsShipped/markAsDelivered` (`Order.php:176-197`) | `update(['status' => ...])` | via observer (also covers any future caller, e.g. the tracking sync of category G) |
| `Order::boot` `updating` hook (`Order.php:71`, A) | cancelling an order whose payment is pending also sets `payment_status = cancelled`; a captured (`succeeded`) payment is left alone | none by itself |
| `CheckoutController::cancel` (`:319`, A) | Stripe cancel_url: closes an unpaid order (`payment_status` and, when cancellable, `status = cancelled`) | observer sees `cancelled` for a never-paid order => **silent** (see rule below) |
| `OrderCreationService` (`:175`, `:217`) | creates `processing`, or `pending` for unpaid sessions; later `pending -> processing` | none (creation / payment sync) |
| `OrderSeeder`, `OrderFactory` | create orders directly in any status (70% `delivered`) | **never**: the observer listens to `updated` only |
| `SetupTestTracking` command | dev helper, sets test user's orders to `shipped`/`delivered` | observer applies the normal rule (mail only if the order was paid/confirmed) |

Customer-notification rule (observer): a status mail is requested only when `status` really changed (`wasChanged`) AND the customer knows about the order (`payment_status = succeeded` OR a confirmation was sent: `hasEmailBeenSent()`). Applied to **delivered and cancelled**: an abandoned, never-paid checkout that is cancelled (admin, bulk or Stripe cancel page) or even marked delivered by mistake never mails anybody. Idempotent through `order_emails` (type + fingerprint `default`): re-saving, delivered -> shipped -> delivered, bulk repeats never re-send. Deliberate consequence: an order that is cancelled, re-opened and cancelled again gets one cancellation mail in total.

### 2. Payment status / refunds

- `orders.payment_status` enum is `pending|processing|succeeded|failed|cancelled`; **no `refunded`** value (the factory helper and `Admin/Orders/Edit.tsx` mention `paid`/`refunded`, which the DB would reject; A already reported it).
- **No Stripe refund is issued anywhere** (`grep -ri refund app` => only the admin placeholder and this category's code). `OrdersController::refund` (`:359`) only appends a `REFUND: $x` line to `orders.notes` and flashes "Refund processed successfully." - misleading, pre-existing, not changed except for the TODO below.
- Consequence: `OrderRefunded` + `EmailService::sendOrderRefunded(Order, float $amount)` are built and tested but **deliberately not called from `refund()`**: a customer must never be told "you have been refunded" for money that did not move. `refund()` carries a one-line TODO pointing at `sendOrderRefunded` for when the real Stripe refund exists.
- Suggested trigger when refunds are implemented: the code path that gets Stripe's successful refund, and/or the `charge.refunded` webhook (which would also cover refunds done by hand in the Stripe dashboard - the only way a refund can happen today).

### 3. `admin_notification_email`

- Seeded in `SettingsSeeder.php:61` as `admin@rspecommerce.com` (a placeholder domain - check what the production DB really holds), editable and already validated (`nullable|email`) in Admin -> Settings -> Email, previously read by nothing.
- Now effective: `EmailService::adminAddress()` = that setting if it is a valid address, else `config('mail.contact_email')` (`CONTACT_EMAIL` -> `MAIL_SUPPORT_ADDRESS`), else no alert (logged). Used by the new-order alert and the paid-order-missing alert only.

### 4. Cancel and stock / Stripe

Cancelling restores no stock (there is no stock decrement on order creation at all, see order-flow.md) and voids/refunds nothing on Stripe. A cancelled **paid** order therefore keeps `payment_status = succeeded` and the money stays captured until someone refunds it in the Stripe dashboard. The cancellation email therefore only says "If you already paid, we will refund the amount to your original payment method" - no timeline and no claim that the refund has started. **Cancellation does not refund: the refund remains manual (Stripe dashboard) until the Stripe refund is implemented.** Report-only; not changed.

### 5. Stripe events

Handled: `checkout.session.completed`, `payment_intent.succeeded` (effectively dead, A). Not handled (report-only): `charge.refunded`, `checkout.session.expired`, `checkout.session.async_payment_succeeded|failed`, `payment_intent.payment_failed`. Abandoned-checkout / expiry mail is marketing-adjacent and excluded.

## Changes made

- `OrderEmail`: `TYPE_DELIVERED|CANCELLED|REFUNDED|ADMIN_NEW_ORDER`, `isInternal()`, `refundedFingerprint()` (hash of the amount), `formatAmount()`.
- `EmailService`: `sendOrderCancelled`, `sendOrderDelivered`, `sendOrderRefunded(Order, float)`, `sendNewOrderAlert`, `sendPaidOrderMissingAlert`, `adminAddress()`, `recipientFor()`; `order_refunded_enabled` added to the type switches; the existing private `dispatchOrderEmail` is reused (new optional `amount`).
- `SendOrderEmail`: new `amount` argument (refund only), mailable per new type, recipient through `EmailService::recipientFor()` (admin address for internal types), app locale for internal alerts, `orderItems.size` eager loaded, and a locale guard (see discrepancies).
- Mailables `OrderCancelled`, `OrderDelivered`, `OrderRefunded`, `NewOrderAlert`, `PaidOrderMissingAlert` (the last one is a queued mailable: it has no order); trait `DescribesOrder` (greeting name, item lines, currency formatting in the rendered locale).
- Views `emails/order-{cancelled,delivered,refunded}`, `admin-new-order`, `admin-paid-order-missing`, each with a `-text` twin; customer ones include the no-reply notice, internal ones do not. Plain table markup only; Category C owns the visual rebuild.
- Strings: `emails.order_cancelled.*`, `emails.order_delivered.*`, `emails.order_refunded.*`, `emails.admin.*` in `lang/it.json` and `lang/en.json` (flat keys). de/es/fr intentionally left to Category C.
- `OrderObserver` registered in `AppServiceProvider::boot`.
- `OrderCreationService`: new-order alert after the first payment confirmation (new paid order, or an unpaid one that just became paid), never fails order creation.
- `StripeWebhookListener`: the `critical` catch now also queues the paid-order-missing alert (deduped 6 h per session with `Cache::add`), never masks the original exception, which is still rethrown.
- `routes/admin.php`: `PATCH orders/bulk-status` moved above the `orders` resource (it was swallowed by `orders/{order}` and answered 404). Real-HTTP tests added.
- Admin settings: `order_refunded_enabled` (controller + toggle in `Admin/Settings/Index.tsx`), admin address help text.

## Other discrepancies found

1. **JSON translations do not fall back to `fallback_locale`.** `orders.locale` can be `de/es/fr` (A), and `SendOrderEmail` applied it, so any `emails.*` key missing in that language rendered as a raw key (already true for B's no-reply notice in confirmation/shipped mails). Mitigation in `SendOrderEmail::locale()`, for the new lifecycle mail types only (B's confirmation/shipped behaviour and tests are untouched, so those still render the raw no-reply keys in de/es/fr until Category C): a supported order locale is used only if its file has a probe key for the mail type (`Lang::hasForLocale`), otherwise the app locale. Once Category C adds the keys to de/es/fr the guard lets those languages through automatically.
2. `admin.orders.refund` flashes "Refund processed successfully." and writes `REFUND: $...` into the notes though nothing happens (see section 2).
3. A cancelled order that is later set to `delivered` through the edit form is allowed by the controller (no transition rules) and mails "delivered" if the customer was notified. Admin-error territory; not guarded.
4. `HandleInertiaRequests` does not share flash messages, so the admin never sees warnings about lifecycle mails that could not be queued (they are logged: `Order lifecycle email could not be requested`, `Failed to queue order email`). Frontend, outside E.
5. B's `AdminShippedEmailTest` had two assertions of the form "nothing is pushed" after marking an order delivered; they now assert "no *shipped* email is pushed" because delivered correctly queues its own mail.
6. `admin_notification_email` seed value (`admin@rspecommerce.com`) and `from_email` seed (`noreply@rspecommerce.com`) are placeholders; the `from_email`/`from_name` admin fields are still unused (B).
