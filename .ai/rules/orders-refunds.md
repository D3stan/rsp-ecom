---
paths:
  - app/Domain/Orders/*.php
  - app/Models/Order*.php
  - app/Services/RefundService.php
  - tests/Feature/Orders/*.php
---
# Orders, refunds and cancellations

Order lifecycle, payment status, refunds through Stripe, cancelling paid orders, net revenue and the customer-service refund procedure. It does not cover checkout or order creation.

## Entry points
- `app/Domain/Orders/OrderTransitions.php`: the allowed status moves.
- `app/Models/Order.php`: `transitionTo()`, `cancel()`, `canShip()`, `hasCapturedPayment()`, `financialsLocked()`, `isRefundDue()`.
- `app/Domain/Orders/OrderFinancialEdit.php`: what the admin edit form may change.
- `app/Services/RefundService.php`: issue, retry and settle refunds.
- `app/Listeners/StripeWebhookListener.php`: `charge.refunded`, `charge.refund.updated`, `refund.updated`.
- `app/Support/Revenue.php`: net revenue; `app/Services/StockService.php`: `restoreForOrder()`, `restoreItems()`.

## Rules
- MUST change status only through `Order::transitionTo($to, $actor, $reason, $meta)`; it row-locks, validates, writes one `order_events` row. `OrderTransitionsTest` (lifecycle map, stale copy).
- MUST reach `shipped` only through `App\Shipping\ShippingService::ship()`; edit form, status endpoint and bulk update refuse it. Unenforced beyond controller validation.
- MUST NOT ship unless `payment_status` is `succeeded` or `partially_refunded` (`Order::canShip()`). `tests/Feature/Shipping/ShipGuardTest.php`.
- MUST NOT edit lines, prices, shipping, discount or totals once financials are locked (`Order::financialsLocked()`: payment captured or `processing`, or order `shipped`/`delivered`); refund instead. `OrderEditGuardsTest`, `PaymentStatusEditTest`.
- NEVER set `refunded`/`partially_refunded` or undo a captured payment from the admin edit form on a Stripe order; only offline orders (no intent/session) may be edited freely. `PaymentStatusEditTest`.
- MUST keep refund money in integer cents (`refunds`); `stripe_refund_id` is unique. DB constraint.
- NEVER issue a new refund for an unconfirmed one; settle by webhook or Retry. `RefundTest` (pending reserves the amount, retry reuses the key).
- MUST send the Stripe refund with idempotency key `refund:{refund_id}`. `RefundTest`.
- NEVER let a refused transition fail a webhook: system moves in `OrderCreationService` catch `InvalidOrderTransition` and log it. `StripeAsyncEventsTest` (expired on a paid order answers 200).
- MUST NOT add an `Event::listen` for `StripeWebhookListener`; it is registered by event discovery. `RefundTest` (registered exactly once).

## How it works
Allowed moves (system moves record their source and run with no actor):

| From | To |
|---|---|
| `pending` | `processing`, `cancelled` |
| `processing` | `shipped`, `cancelled` |
| `shipped` | `delivered`, `processing` (undo ship, reason required) |
| `delivered`, `cancelled` | none (refunds after delivery keep the status) |

System callers: Stripe payment confirmed (`pending → processing`), expired/failed checkout (`→ cancelled`), carrier tracking auto-deliver. Cancel and undo-shipment ask for confirmation in the admin.

Payment status: `pending`, `processing`, `succeeded`, `failed`, `cancelled`, `partially_refunded`, `refunded`. Captured = `succeeded`, `partially_refunded`, `refunded`. Unpaid orders may edit lines (qty >= 1, price >= 0) and shipping; the server recomputes subtotal, total and VAT in one transaction.

Refundable = total (if captured) − succeeded refunds − pending refunds. `RefundService::refund`:
1. Under an order lock: check captured, a payment intent exists, 0 < amount <= refundable, restock lines valid. Write a `pending` row (it reserves the amount against concurrent refunds).
2. Call Stripe `refunds.create` with `payment_intent`, `amount`, `metadata[order_id, refund_id]`.
3. Outcome:

| Stripe answer | Result |
|---|---|
| Refund `succeeded` or `pending` | Row `succeeded`; `payment_status` → `refunded` or `partially_refunded`; `refund` event; restock if asked; refund email |
| 4xx, or refund `failed`/`canceled` | Row `failed` with the message; `refund_failed` event; no email; amount free again |
| Timeout, connection, rate limit, 409, 5xx, unknown | Row stays `pending`, amount stays reserved; `refund_unconfirmed` event; no email |

Retry (`POST admin/orders/{order}/refunds/{refund}/retry`) first looks up the intent's refunds at Stripe by `metadata.refund_id` (idempotency keys expire after 24h), else re-sends under the same key. Whichever path completes a row (refund, retry, webhook) restocks and emails exactly once: restock is idempotent per `refund:{id}` via `order_items.restocked_qty`; one email per refund id.

Webhooks (must be subscribed on the Stripe endpoint `/stripe/webhook`):
- `charge.refunded`: for each succeeded/pending refund: known id → nothing; our row via `metadata.refund_id` → complete it; else record as `stripe_dashboard` refund, update status, event, email. Lists refunds from Stripe when the event omits them.
- `charge.refund.updated` / `refund.updated`: refund turned `failed`/`canceled` after we recorded it → row `failed`, status recomputed, `refund_failed` (`after_the_fact`), `Log::critical` (customer may already have the email; support must follow up).

Cancelling a paid order offers: refund now (refunds all refundable, then cancels; a failed refund blocks the cancel; unconfirmed cancels and leaves the refund pending) or refund later (cancel only → "Refund due"). Cancel restocks once, without double-counting refunded units. Cancellation email: fully refunded → "refunded {amount}"; still owed → "will refund"; unpaid → no refund line.

Net revenue = Σ total of captured, non-cancelled orders − Σ succeeded refunds on them, attributed to the order's creation date.

## Traps
- "Refund due" is computed, never stored: `cancelled` AND `payment_status` in `succeeded`/`partially_refunded`. Filter `?refund_due=1` on the order list.
- Typing a tracking number in the edit form does not ship the order.
- A refund made today lowers the creation month's revenue, not today's.
- Unconfirmed is not failed: retrying with a new refund can double-pay.
- Stripe charges the amount it was given while payment is in flight, so editing money then makes the order disagree with it.

## Operate
- Support procedure: Admin > Orders > order > Refunds > Refund (amount prefilled; untick shipping to exclude it; optional reason; per-item "put back in stock") > Continue > Refund now. Shipping is refunded with a full return (EU consumer law); customer service decides.
- Result "not confirmed yet": do not refund again; wait or click Retry. Error: nothing refunded, message on the row.
- No self-service returns page; refunds are agreed by email. Dashboard refunds appear as source `stripe_dashboard` while the webhook events are enabled.

## Tests
- `tests/Feature/Orders/RefundTest.php`: refund outcomes, retry, idempotency, restock.
- `tests/Feature/Admin/OrderTransitionsTest.php`, `OrderEditGuardsTest.php`, `PaymentStatusEditTest.php`, `PaidCancellationTest.php`, `tests/Feature/Shipping/ShipGuardTest.php`: transitions, financial lock, payment-status edits, cancel and refund due, ship gate.
- `tests/Feature/Orders/OrderStatusConsistencyTest.php`: status/payment consistency.
- `tests/Feature/Orders/StripeAsyncEventsTest.php`, `StripeWebhookOrderTest.php`: webhook handling.
- `tests/Feature/Orders/OrderStockTest.php`: stock on cancel and refund.
