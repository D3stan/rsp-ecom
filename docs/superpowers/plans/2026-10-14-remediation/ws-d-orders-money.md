# WS-D: Orders & money (state machine, audit, edit guards, refunds)

> Branch `feat/orders-money` from `fixes`. Wave 1. Read `00-overview.md` first; follow its per-item protocol.
> Migration prefix: `2026_10_15_40xxxx`. **Size: L.** Items build on each other: do D1 → D5, then D7, in order. (D6, the self-service withdrawal portal, was **cancelled by the owner**: refunds are agreed by email with customer service and issued by the admin. Do not build any customer-facing return/withdrawal page.)
> WS-G (shipping, wave 2) builds on your `Order::transitionTo()`. Keep its signature stable once D1 is accepted.

**Goal:** make order changes safe and auditable, make refunds move real money (and reconcile Stripe-dashboard refunds),
and stop paid cancellations from silently owing money.

---

## Design overview (read before D1)

```
                    ┌──────────────────────────────┐
 Admin UI / bulk ──▶│ Order::transitionTo($to, …)  │──▶ order_events (audit)
 Tracking module ──▶│  allowed-transition map      │──▶ observers (stock restore, emails) unchanged
 (status writes) ──▶│  throws InvalidOrderTransition│
                    └──────────────────────────────┘
 RefundService::refund(order, cents, reason, actor, restockItems)
     ├─ refunds row (pending) ─▶ Stripe Refund::create(idempotency "refund:{id}")
     ├─ success ─▶ refunds.status=succeeded, orders.payment_status=refunded|partially_refunded,
     │             order_events(refund), restock (optional), EmailService::sendOrderRefunded
     └─ failure ─▶ refunds.status=failed + error, nothing emailed, admin sees the error
 Stripe webhook charge.refunded ─▶ reconcile (create rows for refunds made in the Stripe dashboard)
```

New/changed statuses:
- `orders.status`: unchanged values (`pending, processing, shipped, delivered, cancelled`).
- `orders.payment_status`: add `refunded`, `partially_refunded`. It's an enum column (`create_orders_table.php:33`):
  migrate it so it works on SQLite and MySQL.
- `orders.delivered_at` (nullable timestamp, new) is set when the order enters `delivered` (useful for the withdrawal window shown to customer service). Backfill it from tracking events or `updated_at` for orders already delivered, and say which in the migration comment.

---

### D1 (P1, F-07): order state machine. Size **M**

**Claim:** any status can be set from any status. `Admin\OrdersController` `update` (`:157`), `updateStatus`, `bulk`
(`:307,337,344-356`) write `status` directly. Cancel on `Show.tsx:351-357` uses the generic update, skips
`can_be_cancelled` (`Order.php:217-220`), and has no confirmation. Bulk cancel of shipped/delivered orders restocks goods
that have already left.

**Repro tests** `tests/Feature/Admin/OrderTransitionsTest.php`: as admin, PATCH a `delivered` order to `cancelled`
→ today it succeeds (the test asserts it is refused: 422/redirect with error, status unchanged, stock unchanged). Bulk
the same. `shipped → pending` refused.

**Spec:**
- `app/Domain/Orders/OrderTransitions.php` (or a constant on `Order`): allowed map
  - `pending → processing, cancelled`
  - `processing → shipped, cancelled`
  - `shipped → delivered, processing` (the latter = "undo ship", requires a reason)
  - `delivered → (none)` (refunds after delivery go through D4 without changing the status)
  - `cancelled → (none)`
- `Order::transitionTo(string $to, ?User $actor = null, ?string $reason = null, array $meta = []): void`. It throws
  `App\Exceptions\InvalidOrderTransition` when the move isn't allowed, sets `delivered_at` when entering `delivered`,
  saves, and writes an `order_events` row (D2). Existing observers (stock restore on cancel, status emails) keep
  working; don't duplicate their logic.
- Route every status write through it: `OrdersController::update/updateStatus/bulk/cancel`, `markAs*` helpers on
  `Order`, and the tracking module's auto-deliver (`ShipmentTrackingService`). Grep for `'status' =>` and
  `->status =` on orders to find all of them.
- Admin UI: Show/Index offer only allowed next statuses, using an `allowed_transitions` array the controller
  passes. Cancel and "undo ship" open an `AlertDialog` confirmation (the reason is required for undo).
- Bulk: per-order result. Refused orders are listed in the flash ("3 updated, 2 skipped: #… not allowed").

---

### D2 (P1, F-09): order audit trail and a real timeline. Size **S**

**Claim:** history is free text in `notes` (`OrdersController.php:396-399,480`). The Show timeline uses `updated_at` for every
step (`Show.tsx` ~385-415).

**Spec:** table `order_events` (id, order_id FK cascade, actor_id nullable FK users nullOnDelete, type string
[`status_changed`, `refund`, `refund_failed`, `note`], from_status nullable, to_status
nullable, payload json nullable, created_at). Written by `transitionTo` and `RefundService`.
`Admin/Orders/Show.tsx` timeline renders these events (actor name, time, reason), falling back to `created_at` for
orders with no events.

**Tests:** a transition writes one event with the actor. The Show page props include the events in order.

---

### D3 (P1, F-08): edit guards. Size **S-M**

**Claim:** `Admin/Orders/Edit.tsx:182-199,298-323` lets the admin change `payment_status`, totals and line qty/price.
`OrdersController::update` (`:156-201,255-284`) saves them unvalidated (`order_items.*` JSON), outside a transaction.
Removed lines are not deleted server-side, and the discount is ignored in the recompute.

**Repro tests:** as admin, PATCH a paid order with `payment_status=pending` → persisted today; PATCH
`order_items[0][quantity] = -3` → persisted today; PATCH a paid order's totals → persisted today.

**Spec:**
- The UI never sends `payment_status`, and the server ignores or refuses it (payment state comes from Stripe and
  refunds only).
- Once `payment_status` ∈ {succeeded, partially_refunded, refunded}, the financial fields (items, prices, quantities,
  totals, discount) are locked: the server refuses changes with a clear message and the UI shows them read-only with
  the hint "Use a refund to return money".
- Unpaid orders: validate `order_items.*.quantity` (int ≥ 1) and `price` (numeric ≥ 0), recompute totals
  **including the discount**, all inside `DB::transaction`. Remove the "remove line" control unless the server
  really deletes the line (implement deletion for unpaid orders only).
- Editable on any order: shipping address fields, customer note/notes, tracking fields (WS-G changes the ship flow
  later; leave tracking edit as is).

---

### D4 (P0-2, F-01): real refunds. Size **M**

**Claim:** `OrdersController::refund` (`:383-402`) only appends `"REFUND: $…"` to notes and flashes success. There is
no Stripe call, no refunded state, no `charge.refunded` handling (`StripeWebhookListener.php:34-51`) and no UI. A test
locks the placeholder in (`tests/Feature/.../OrderStatusEmailsTest.php:165-175`).

**Repro tests** `tests/Feature/Orders/RefundTest.php`: POST the refund route for a paid order → assert a Stripe
refund request was sent (via the fake Stripe HTTP client) **and** the payment status changed. Both fail today.
Replace the placeholder test as part of the fix, and say so in the report.

**Spec:**
- Table `refunds`: id, order_id FK, amount_cents int, currency char(3), reason string nullable, status enum
  (`pending, succeeded, failed`), stripe_refund_id nullable unique, failure_message nullable, source enum
  (`admin, stripe_dashboard`), created_by nullable FK users,
  timestamps.
- `App\Services\RefundService`:
  - `refundableCents(Order): int` = captured total − Σ succeeded/pending refunds.
  - `refund(Order $order, int $amountCents, ?string $reason, ?User $actor, string $source = 'admin', array $restock = []): Refund`.
    It validates `0 < amount ≤ refundable`, requires a `stripe_payment_intent_id`, and locks the order row
    (`lockForUpdate`) to prevent concurrent double refunds. It creates the `pending` row, then calls Stripe
    `refunds.create` with `payment_intent` and `amount`, `metadata[order_id, refund_id]` and **idempotency key
    `refund:{refund_id}`**.
  - On success: mark it succeeded, set `payment_status` to `refunded` (refundable now 0) or `partially_refunded`, write
    `order_events`, restock the given order items if `$restock` (`[order_item_id => qty]`) through a new **idempotent**
    `StockService::restoreItems(Order, array $qtyByItem, string $idempotencyKey)` (key `refund:{refund_id}`), then call
    `EmailService::sendOrderRefunded($order, $amount)`.
  - On a Stripe error: mark it failed with the message, write a `refund_failed` event, send no email, and rethrow a domain exception the
    controller turns into an error flash.
- Webhook: handle `charge.refunded` in `StripeWebhookListener`. For each refund on the charge without a matching
  `stripe_refund_id`, create a `succeeded` row with source `stripe_dashboard`, update `payment_status`, write an event,
  send the refund email (same dedupe). It must be idempotent on webhook retries.
- The refund dialog has an **"Include shipping cost"** hint: customer service decides; when all items are refunded the default amount includes shipping (CRD Art. 13 standard delivery cost).
- Admin UI (`Admin/Orders/Show.tsx`): a "Refund" button when refundable > 0 opens a dialog with the amount (prefilled with
  the refundable amount, EUR input converted to cents), reason, an optional per-line restock qty, and an `AlertDialog`
  confirmation. A refunds list on the order shows amount, status, source, actor and date. Remove the `$` text.
- `EmailService::sendOrderRefunded` already exists (`EmailService.php:104`). Check that its amount guard works with
  partial refunds.

**Tests:** full refund, partial refund, two partials up to the total, over-refund refused, Stripe error → failed row and no
email, idempotency (the same refund isn't sent twice when retried), webhook reconcile creates the row once across two
deliveries, and a customer gets 403.

---

### D5 (P0-2, F-04): cancelling a paid order. Size **S**

**Claim:** cancelling a paid order restores stock (`OrderObserver.php:27-29`) and emails "we will refund you"
(`OrderCancelled.php:38-39`), but records no refund, and revenue KPIs still count the order
(`Admin/DashboardController.php:29-47`).

**Repro tests:** cancel a paid order → assert something records that a refund is owed (fails today). Dashboard revenue
→ assert the cancelled paid order is excluded (fails today).

**Spec:**
- The cancel dialog for paid orders has two options: **"Cancel and refund in full now"**, which calls the
  `RefundService` for the refundable amount and then transitions, or **"Cancel, refund later"**, which transitions only.
- "Refund due" is computed, not stored: `status = cancelled AND payment_status IN (succeeded, partially_refunded)`.
  Add an Orders list filter "Refund due" and a badge on Show.
- Revenue KPIs and order-list revenue: exclude `cancelled` orders and subtract succeeded refunds (net revenue). Keep the
  queries readable and add a test.
- The cancellation email wording: if the order was refunded before the email is sent, say "refunded"; otherwise keep the
  current wording.

---

### D7: wrap-up

Update `docs/` with a short `docs/orders-refunds.md` (state map, refund flow, webhook, how customer service issues a refund).
Run the full suite and report a summary of all D items.
