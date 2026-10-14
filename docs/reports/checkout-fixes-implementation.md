# Checkout and email flow: what to check when something is off

Short, current notes. The earlier "fixes" write-up referenced commands and events that no longer exist; the authoritative documents are
`docs/reports/email-operations.md` (mail pipeline and runbook) and `docs/reports/email-audit/order-flow.md` (how an order is created).

## How a paid order appears

1. The customer pays on Stripe Checkout (`POST /checkout/cart` or `POST /guest/checkout/cart` create the session).
2. Two things race, and either one is enough: the **webhook** (`POST /stripe/webhook`, event `checkout.session.completed`)
   and the **success page** (`GET /checkout/success?session_id=...`). Both call `OrderCreationService`, which creates exactly one order per Stripe session.
3. The confirmation email is queued by `EmailService` once the order is committed; a queue worker sends it
   (`php artisan queue:work`; in Docker the `queue` service). The scheduler's `orders:send-pending-emails --force` (every 10 minutes) is the safety net
   for confirmations whose job was lost.

## Stripe webhook

- URL: `https://<your domain>/stripe/webhook` (route name `cashier.webhook`, declared in `routes/web.php`; Cashier's own routes are disabled
  with `Cashier::ignoreRoutes()` so the package's public `/stripe/payment/{id}` page does not exist).
- Events to enable (handled by `App\Listeners\StripeWebhookListener`; every other event is ignored):
  - `checkout.session.completed` (required): creates the order (paid, or pending for delayed payment methods).
  - `checkout.session.async_payment_succeeded`: pays the pending order (needed when delayed payment methods such as SEPA debit are enabled).
  - `checkout.session.async_payment_failed` and `checkout.session.expired`: close a still-pending order and retire its checkout snapshot.
  - `payment_intent.succeeded` is no longer used (removed); unsubscribe it if it was selected earlier.
- `STRIPE_WEBHOOK_SECRET` must be set. The signature is checked by Cashier's `VerifyWebhookSignature`; in production a missing secret makes the
  endpoint answer 403 (fail closed). The route is excluded from CSRF in `bootstrap/app.php`.
- Troubleshooting: `docs/reports/stripe-webhook-troubleshooting.md`; Stripe Dashboard -> Developers -> Webhooks shows each delivery and its response code.

## Useful commands

```bash
php artisan stripe:check-config                       # which Stripe keys are visible (masked); reads config('cashier.*')
php artisan orders:send-pending-emails --dry-run      # list paid orders that never got a confirmation
php artisan orders:send-pending-emails --force        # queue them (what the scheduler does)
php artisan email:test-order-confirmation <email>     # send a sample confirmation
php artisan ops:check-queue                           # worker/backlog health check (also scheduled every 5 minutes)
```

## When something goes wrong

- **No confirmation email:** check `order_emails` (see the runbook), that the worker is running, and `storage/logs/laravel.log` for `Order email queued/sent/permanently failed`.
- **Paid but no order:** the admin gets a "paid order missing" alert; search the log for `Stripe payment succeeded but the order could not be created` (it has the session id).
- **Success page redirects home:** invalid or expired `session_id`, or a Stripe API error; the log has the exception class and message (never a stack trace or the customer's address).
- **Webhook answers 403:** wrong or missing `STRIPE_WEBHOOK_SECRET`, or the secret belongs to another endpoint/mode (test vs live).
