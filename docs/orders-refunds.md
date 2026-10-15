# Orders, refunds and cancellations

How an order moves through its lifecycle, how money goes back to the customer, and what customer service does.

```
app/Domain/Orders/OrderTransitions.php     allowed status moves (the lifecycle map)
app/Models/Order.php                       transitionTo(), cancel(), hasCapturedPayment(), financialsLocked(), isRefundDue()
app/Models/OrderEvent.php                  audit trail rows (order_events)
app/Domain/Orders/OrderFinancialEdit.php   what the admin edit form may change (money only while unpaid)
app/Services/RefundService.php             refunds through Stripe + webhook reconciliation
app/Models/Refund.php                      refunds table (integer cents)
app/Services/StockService.php              restoreForOrder() on cancel, restoreItems() for returned items
app/Support/Revenue.php                    net revenue for the dashboard and the order list
app/Listeners/StripeWebhookListener.php    charge.refunded, charge.refund.updated, refund.updated
```

## Order status

Every status change goes through `Order::transitionTo($to, $actor, $reason, $meta)`. It checks the move against the
stored (row-locked) status, throws `InvalidOrderTransition` when the move is not allowed, sets `delivered_at` when the
order enters `delivered`, saves, and writes one `order_events` row. The observers react as for any save: stock goes back
on cancel, and status emails are sent.

| From         | Allowed to                                        |
|--------------|---------------------------------------------------|
| `pending`    | `processing`, `cancelled`                         |
| `processing` | `shipped`, `cancelled`                            |
| `shipped`    | `delivered`, `processing` (undo ship: reason required) |
| `delivered`  | none (refunds after delivery keep the status)     |
| `cancelled`  | none                                              |

**Shipping also needs the money:** `processing → shipped` is refused unless `payment_status` is `succeeded` or
`partially_refunded` (`Order::canShip()`; `transitionTo()` checks the stored payment). An unpaid order moved to
`processing` by hand, or one refunded in full, cannot ship. `allowedTransitions()` leaves `shipped` out for such an
order, and the admin pages receive `can_ship`, which shows the "Ship" action.

Who calls it:
- **The admin:** the order page, the edit form, the status endpoint, bulk updates, the ship form. A refused move
  writes nothing and shows an error. In bulk, refused orders are listed as skipped.
- **The system:** Stripe confirming a payment (`pending → processing`), a checkout that expired or failed
  (`→ cancelled`), and the carrier tracking auto-deliver (`shipped → delivered`). These run with no actor and record
  their source in the event. A refused move never fails a webhook: it is logged and the other fields are saved.

The admin order page offers only the allowed moves. Cancel and Undo shipment open a confirmation (`AlertDialog`).
The timeline shows the `order_events` rows with their real time, the actor (or "System (source)") and the reason.

## Payment status

`orders.payment_status`: `pending`, `processing` (payment in flight), `succeeded`, `failed`, `cancelled`,
`partially_refunded`, `refunded`.

It follows Stripe and refunds only; the admin edit form never changes it.

- **Captured** (`Order::hasCapturedPayment()`): `succeeded`, `partially_refunded`, `refunded`. Money was taken, so it
  can be refunded.
- **Financials locked** (`Order::financialsLocked()`): captured, or `processing`. Lines, prices, shipping, discount and
  totals can no longer be edited. To return money, use a refund. While a payment is in flight, Stripe charges the
  amount it was given, so an edit could only make the order disagree with it.
- **Unpaid orders:** the edit form may change lines (quantity ≥ 1, price ≥ 0, a removed line is deleted) and shipping.
  The server then recomputes the subtotal, total (discount included) and VAT portion in one transaction.

## Refunds

Money is kept in integer cents in the `refunds` table:
- `amount_cents`, `currency`, `reason`;
- `status`: `pending`, `succeeded` or `failed`;
- `stripe_refund_id` (unique), `failure_message`;
- `source`: `admin` or `stripe_dashboard`;
- `created_by`;
- `restock`: units to put back in stock, as `{order_item_id: qty}`.

**Refundable** = order total (when captured) − succeeded refunds − pending refunds.

### Issuing a refund (`RefundService::refund`)

1. Under a lock on the order, the service checks the payment is captured, there is a Stripe payment intent, and
   0 < amount ≤ refundable, and validates the restock lines. It then writes a `pending` row. That row reserves the
   amount, so a second, concurrent refund sees less refundable.
2. It calls Stripe `refunds.create` with:
   - `payment_intent` and `amount`;
   - `metadata[order_id, refund_id]`;
   - the idempotency key `refund:{refund_id}`.
3. Then it acts on the outcome:

| Stripe answer | Outcome |
|---|---|
| Refund with status `succeeded` or `pending` | **Done:** row `succeeded`; `payment_status` → `refunded` (nothing left) or `partially_refunded`; `refund` event; restock (if asked); refund email to the customer |
| 4xx error (invalid request, card, authentication, permission), or a refund with status `failed` / `canceled` | **Failed:** row `failed` with Stripe's message; `refund_failed` event; no email; the amount is free again |
| Timeout, connection error, rate limit, idempotency conflict (409), 5xx, anything unknown | **Unconfirmed:** Stripe may or may not have refunded, so the row **stays `pending`** and the amount stays reserved. A `refund_unconfirmed` event is written and no email is sent. The admin sees "Stripe did not confirm the refund yet … Do not retry with a new refund". |

An unconfirmed refund is settled by either:
- **The webhook** (below), which matches it through `metadata.refund_id`.
- **Retry** on the pending refund in the admin panel (`POST admin/orders/{order}/refunds/{refund}/retry`). It first
  looks up the payment intent's refunds at Stripe by `metadata.refund_id`, because Stripe idempotency keys expire
  after 24 hours. If none is found, it re-sends under the **same** key `refund:{id}`. A refund can therefore never be
  paid twice. Never issue a *new* refund for an unconfirmed one.

Whichever path completes a row (refund, retry or webhook) does the restock and sends the email, exactly once.

- **Restock:** `StockService::restoreItems(order, [item_id => qty], 'refund:{id}')`. It is idempotent per key and
  tracked in `order_items.restocked_qty`, so a later cancellation does not give the same units back twice.
- **Refund email:** one per refund (deduplicated per refund id). Two partial refunds of the same amount each get their
  email.

### Stripe webhooks

The Stripe webhook endpoint (`/stripe/webhook`) **must be subscribed** to these events, besides the checkout ones:
- `charge.refunded`
- `charge.refund.updated`
- `refund.updated`

| Event | What happens |
|---|---|
| `charge.refunded` | For every refund on the charge with status `succeeded` or `pending`: known `stripe_refund_id` → nothing; matching our row by `metadata.refund_id` → complete it (an unconfirmed or crashed refund); otherwise → record it as a `stripe_dashboard` refund, update `payment_status`, write the event and email the customer. When the event does not carry the refunds (recent API versions), they are listed from Stripe. |
| `charge.refund.updated` / `refund.updated` | The refund became `failed` / `canceled` after we recorded it → row `failed`, `payment_status` recomputed from the remaining succeeded refunds, `refund_failed` event (`after_the_fact`), **`Log::critical`** (the customer may already have the refund email: customer service must follow up). Succeeded / pending and unknown → recorded as for `charge.refunded`. |

All handlers are idempotent: Stripe retries deliveries, and a failure answers non-2xx so Stripe tries again. The
listener is registered once (Laravel event discovery); do not add an `Event::listen` for it.

## Cancelling a paid order

The cancel confirmation on the order page offers two options when money can still be refunded:

- **Cancel and refund {amount} now**:
  - refunds everything still refundable through `RefundService`, then cancels;
  - if the refund fails, the order is **not** cancelled;
  - if it is unconfirmed, the order is cancelled and the refund stays pending until it is settled.
- **Cancel, refund later**: cancels only. The order is then **Refund due**.

Cancelling always gives the stock back once (the units already restocked by refunds are not counted twice).

**Refund due** is computed, never stored: `status = cancelled` AND `payment_status ∈ {succeeded, partially_refunded}`.
It shows up in two places:
- the order list filter "Refund due" (`/admin/orders?refund_due=1`);
- a red badge on the order page.

**The cancellation email** to the customer:
- order already fully refunded when the mail is sent → "We have refunded {amount}…";
- money still owed → "we will refund {amount still owed}…";
- unpaid order → no refund line.

## Net revenue

The dashboard (today, yesterday, month, year, 7-day chart) and the order list KPIs show **net** revenue
(`App\Support\Revenue::net`):

    Σ total of orders with a captured payment that are not cancelled
    − Σ succeeded refunds on those orders

A cancelled order counts for nothing, refunded or not. Revenue is attributed to the order's creation date: a refund
made today on last month's order lowers last month's figure.

## How customer service issues a refund

Refunds are agreed with the customer by email. There is no self-service return page.

1. Open the order in the admin (**Orders → the order**). The **Refunds** panel shows what is still refundable and
   every refund so far: amount, status, who issued it (or "Stripe dashboard"), date and reason.
2. Click **Refund**:
   - **Amount (EUR):** prefilled with everything still refundable.
   - **Include shipping cost:** customer service decides. When all items are returned, the standard shipping cost is
     refunded too (EU consumer law). Untick it to take the shipping out of the prefilled amount.
   - **Reason:** optional, kept with the refund.
   - **Put back in stock:** units per item that came back sellable.
3. Click **Continue**, check the amount in the confirmation, then click **Refund now**.
4. Read the result:
   - **Success:** the refund is listed as *Refunded*, the payment status changes and the customer gets the refund email.
   - **Error:** Stripe declined it. Nothing was refunded, and the message is on the refund row.
   - **Warning, "not confirmed yet":** do **not** issue another refund. The row says *Waiting for Stripe* and settles
     by itself. If it stays pending, click **Retry** on it: it sends the same request and cannot pay twice.

To cancel an order that was paid, use **Cancel order** on the order page and pick *refund now* or *refund later*.
Orders cancelled with *refund later* stay in the **Refund due** filter until they are refunded.

Refunds made directly in the Stripe dashboard also appear on the order (source *Stripe dashboard*), with the email to
the customer, as long as the webhook events above are enabled.
