---
domain: orders-and-checkout
version: 1.0.0
budget_lines: 150
deep_reference: docs/reference/checkout-implementation-map.md
---

# Orders and Checkout Domain Specification

## 1. Scope & Responsibility

Governs the lifecycle of customer purchases from cart checkout initiation through payment processing, order instantiation, administrative updates, and fulfillment or termination. Manages the system boundary between Stripe Checkout Sessions, webhooks, and internal order state machines.

Key domain responsibilities include:
- Idempotent conversion of Stripe Checkout sessions to `Order` models.
- State preservation via `CheckoutSnapshot` (items, prices, addresses, coupons).
- Strict enforcement of financial locks on captured transactions.
- Order status lifecycle transitions and historical audit logging.
- Guest customer order linking and abandoned checkout recovery.
- Safe public access token generation for order tracking and receipts.

## 2. Core Invariants

- **Single Idempotent Order**: Exactly one `Order` exists per Stripe Checkout session (`orders.stripe_checkout_session_id` unique index). Webhook listeners and checkout redirect controllers synchronize inside DB transactions via `lockForUpdate()`. Race losers catch `UniqueConstraintViolationException` and return the existing order.
- **Snapshot Fallback Chain**: Line items and pricing reconstruct in strict order of precedence:
  1. `CheckoutSnapshot`: Primary source containing frozen items, variants, addresses, coupon code, and computed totals.
  2. Live `Cart`: Fallback if snapshot metadata is missing.
  3. Stripe Session Line Items: Fallback if cart and snapshot are inaccessible.
- **Financial Immutability**: Once an order captures payment (`Order::financialsLocked()`), the money fields (`subtotal`, `shipping_amount`, `tax_amount`, `discount_amount`, `total_amount`) and line items cannot be modified via administrative edits (`OrderFinancialEdit::plan`). Any change throws `OrderEditRefused::financialsLocked`. Financial corrections must route through `RefundService`.
- **Payment Method Authority**: Non-Stripe orders (cash, bank transfer) allow administrative updates to payment status. Stripe-backed orders reject manual updates to captured or refunded statuses; refunds are only recorded when processed by `RefundService`.
- **Side-Effect Gating**: Inventory deduction (`StockService::deductForOrder`) and cart clearance trigger exclusively upon confirmed payment (`isPaymentCompleted()`). Unpaid, cancelled, or expired sessions never deduct stock or delete shopping carts.
- **Address Immutability & Cloning**: Addresses entered at checkout are cloned into dedicated `Address` rows referenced by `orders.billing_address_id` and `orders.shipping_address_id`. Ticking "save address" updates the customer's default address book without mutating historical order foreign keys.
- **Order Number Sequence**: Order numbers are generated via `Order::generateOrderNumber()` using format `RSP-YYYYMMDD-XXXX` and protected against collision with retry loops.
- **Financial Edit Calculation**: On unpaid orders, line quantities and unit prices can be modified by admins. `OrderFinancialEdit::apply()` recalculates the subtotal, recalculates the VAT portion via `Vat::portion()`, applies any existing coupon discount, and derives the final `total_amount`.

## 3. State Machines & Transitions

### Order Status Lifecycle (`OrderTransitions::MAP`)
All status changes must pass through `Order::transitionTo()`. Invalid moves trigger `InvalidOrderTransition`.

| Current Status | Permitted Next Statuses | Invariants & Side Effects |
| :--- | :--- | :--- |
| `pending` | `processing`, `cancelled` | Initial state for asynchronous checkouts. Moving to `processing` confirms payment and triggers stock deduction. |
| `processing` | `shipped`, `cancelled` | Payment captured. Shippable state. Transition to `cancelled` restores deducted stock via `StockService`. |
| `shipped` | `delivered`, `processing` | Transition to `delivered` terminates fulfillment and dispatches customer notification. Reversion to `processing` ("undo ship") strictly requires an audit reason (`REASON_REQUIRED`) and voids active shipments. |
| `delivered` | *(none)* | Terminal fulfillment state. Post-delivery refunds do not modify order status. |
| `cancelled` | *(none)* | Terminal state. Stock restored once; cannot transition back to active status automatically. |

### Payment Status Lifecycle
- `pending` -> `processing` -> `succeeded` | `failed` | `cancelled`
- `succeeded` -> `partially_refunded` | `refunded` (Exclusively managed via `RefundService`)

### Order Shippability Preconditions
An order may only transition to `shipped` if `Order::canShip()` evaluates to true:
1. `status` is currently `processing`.
2. `payment_status` is `succeeded`, or payment is explicitly verified via `Order::hasShippablePayment()`.

## 4. Key Contracts & Entry Points

- `App\Services\OrderCreationService::createFromStripeSession(array|object $session): ?Order`
  Core idempotent entry point for Stripe `checkout.session.completed` webhooks and frontend return redirects.
- `App\Services\OrderCreationService::closeUnpaidSession(array|object $session, string $outcome): ?Order`
  Processes `checkout.session.expired` and `checkout.session.async_payment_failed`. Cancels pending orders without customer emails.
- `App\Models\Order::transitionTo(string $to, ?User $actor = null, ?string $reason = null, array $meta = []): void`
  State machine driver. Verifies legality via `OrderTransitions::allows()`, checks `requiresReason()`, and records transition log.
- `App\Domain\Orders\OrderFinancialEdit::plan(Order $order, array $input): ?array` & `apply(Order $order, array $plan): void`
  Calculates item line differences and derived totals for unpaid orders; enforces locked constraints on paid orders.
- `App\Http\Controllers\CheckoutController::cartCheckout` / `buyNowCheckout`
  Validates cart lines, creates `CheckoutSnapshot`, and generates the Stripe Checkout session.
- `App\Http\Controllers\CheckoutRecoveryController::recover`
  Rebuilds a shopping cart session from an uncompleted checkout snapshot.
- `App\Models\Order::publicUrl(): string`
  Produces a signed, temporary URL for guest tracking and receipt viewing without authentication.

## 5. External Boundaries & Failure Modes

- **Webhook / Return-URL Race Condition**: Handled by pessimistic row locking and unique constraints on `stripe_checkout_session_id`. The secondary request reloads the newly created order and updates missing payment identifiers.
- **Webhook Status Refusal Resiliency**: If a webhook triggers an illegal transition (e.g., Stripe late confirmation on an already cancelled order), `transitionOrSave` swallows `InvalidOrderTransition`, logs a warning, and saves payment fields without failing the webhook response.
- **Stock Depletion During Checkout**: If stock runs out while a customer is completing payment in Stripe, the order is accepted upon capture. The shortfall is recorded on `order_items.oversold_qty`, `orders.has_oversold_items` is set to `true`, and an urgent notification is dispatched to store administrators.
- **Stripe Outages & Session Expiration**: Unpaid Stripe sessions expire naturally. `closeUnpaidSession` marks snapshots as consumed, preventing stale checkouts while allowing customer cart recovery.
- **Email Notification Idempotency**: Order confirmation and alert emails are deduplicated via `order_emails` records, preventing duplicate customer communications across webhook replays.
