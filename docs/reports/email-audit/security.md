# Category D - Security & deliverability hardening: discovery log

Line numbers refer to the tree as it was when Category D started (after waves A and B), unless noted.

## Baseline

`vendor/bin/pest` before any Category D change: 229 passed, 1 skipped (`checkout requires authentication`), 868 assertions.
Final: see the end of this file.

## Discovery checklist results

### (a) `{!! !!}` in email views
- `resources/views/emails/**`: only `emails/partials/no-reply-notice-text.blade.php:3,5,6` (plain-text partial, created by B). The three values are a translation key, `config('mail.support_address')` and `route('contact')`; none is user-controlled, and `{{ }}` would HTML-escape quotes/ampersands in a text/plain part. Safe.
- `resources/views/vendor/mail/**` (Laravel markdown layout, `{!! $slot !!}` etc.): framework defaults, no mailable in the project uses the markdown components with user input. Not changed.
- `contact-message.blade.php` (visitor-controlled content): every value is `{{ }}`; the message sits in a `white-space: pre-wrap` div, so line breaks survive without raw HTML. A test posts `<script>` and asserts it is escaped in the sent HTML.

### (b) Stripe webhook signature
- Cashier route `POST stripe/webhook` (`cashier.webhook`) is registered by the package without the `web` group (no session, no CSRF), so the only protection is the controller middleware.
- `vendor/laravel/cashier/src/Http/Controllers/WebhookController.php:30-34`: the constructor adds `VerifyWebhookSignature` **only `if (config('cashier.webhook.secret'))`**. With an empty `STRIPE_WEBHOOK_SECRET` the endpoint is completely unauthenticated, and `StripeWebhookListener` trusts the session object contained in the payload (`payment_status = paid`, metadata with `source = rsp-ecom`), so a forged POST would have created a paid order and a confirmation email. FAIL-OPEN before this change.
- Fix: `StripeWebhookListener::handle()` throws `AccessDeniedHttpException` (403) and logs `critical` when `app()->isProduction()` and the secret is empty. Non-production behaviour is unchanged (local Stripe CLI without a secret still works). `.env.example` ships the placeholder `whsec_your_webhook_secret_here` (non-empty), so an un-edited copy fails closed through the signature middleware instead.
- Tests: forged event without secret in production -> 403 + no order; wrong/missing signature with a secret -> 403; correct signature -> order created.

### (c) ContactController
Before: no throttle on `POST /contact`; `firstName`/`lastName`/`subject` `max:255`; `ContactMessage` used the visitor as `From` (SPF/DMARC failure and spoofing) and put raw `subject`/names into headers (Symfony Mime encodes CR/LF, but the code relied on that implicitly).
After: `throttle:5,1`; limits 100/100/254/150/5000; control characters (CR, LF, tab, bidi/zero-width format chars) are collapsed to spaces in name/subject both in the controller (so `required` fails on a break-only subject) and in the mailable constructor (so any other caller is safe); `From` = `config('mail.from.address')` with display name "`<visitor> via <app name>`" (characters `< > " @ ;` dropped so the name cannot impersonate an address); `Reply-To` = visitor; recipient unchanged (`config('mail.contact_email')`).

### (d) PII in logs
Inventory of log statements that carried personal data or full payloads (before):
| Where | What was logged | Action |
|---|---|---|
| `CheckoutController::success` | `all_params` (whole query), full URL and `Referer` when `session_id` missing; `customer_email` + `amount_total` per success hit; `getTraceAsString()` on both error paths | removed / trimmed (class + message + session id kept) |
| `EmailService::sendCustomEmail`, `testEmailConfiguration`, `sendSimpleTestEmail` | full recipient address | masked via `App\Support\Privacy::maskEmail()` (`m***@example.com`) |
| `OrderCreationService`, `StripeWebhookListener`, `Jobs/*`, `SendPendingOrderEmails` | ids, session ids, hashes, exception class + message only | already clean, unchanged. `critical`/`error` keep session id, event id, payment intent id, cart id |
| NOT my files (report only) | `CheckoutController::cartCheckout/createSession/createGuestSession/Admin\OrdersController` log `getTraceAsString()`; `CheckoutService.php:260,297,327,365` log `customer_email`; `GoogleAuthController:147`, `Admin\ReviewsController:153`, `Admin\ProductController:580,678` traces | see report |
- Retention: `config/logging.php` default channel is `stack` -> `single` (`LOG_STACK=single` in `.env.example`): one file that never rotates, so personal data stays forever. `daily` with `LOG_DAILY_DAYS` (default 14) already exists. Not changed (env decision); documented in the deliverability checklist, section 8.

### (e) Unauthenticated routes exposing order data
| Route | Exposure | Status |
|---|---|---|
| `GET /checkout/success?session_id=` | whole order incl. `guest_email`, `guest_phone`, `guest_session_id`, (addresses when relations were loaded) to anybody holding the Stripe session id (history, Referer, forwarded links) | gated, see below |
| `GET /checkout/cancel?session_id=` | no order data rendered; mutates only unpaid orders (A) | OK |
| `GET /orders/{order}` (new) | signed link only | new, tested |
| `POST stripe/webhook` | see (b) | fixed |
| `GET stripe/payment/{id}` (Cashier `PaymentController`) | public by design for SCA flows; shows the payment intent `client_secret` for an unguessable `pi_` id. The shop never creates payment intents of its own, so it is unused. Cannot be disabled without also removing the webhook route (`Cashier::ignoreRoutes()`). | report only |
| `GET /checkout/show` (auth) | any logged-in user with a Stripe session id gets the Stripe session object + order (no owner check) | report only (not `success()`) |
| `POST /guest/checkout/session` (legacy) | trusted a client-supplied `cart_session_id` (another guest's cart/session id) | route removed (unreachable `Checkout/GuestIndex.tsx` was its only caller) |

### (f) Guest session / cookie situation on return from Stripe
- Guest carts are keyed by `Session::getId()` (`CartController` line ~403, `CheckoutController::showGuestDetails/guestCartCheckout`): the Laravel session id (cookie `laravel_session`, `SESSION_DRIVER=database`, `same_site=lax`, `http_only`, lifetime 120 min).
- `guestCartCheckout` puts that id into the Stripe session metadata as `guest_session_id`; `OrderCreationService` stores it in `orders.guest_session_id` (and deletes the cart). Registered checkout stores `user_id` and no `guest_session_id`.
- On return, `SameSite=Lax` cookies ARE sent on the top-level GET redirect from `checkout.stripe.com`, so a normal browser still carries the same session id: `session()->getId() === orders.guest_session_id` is the proof of ownership. It breaks when: the cookie is dropped (in-app browsers such as Instagram/Facebook/Gmail webviews opening the Stripe link in another browser, ITP, private tabs), the session expires (120 min idle at Stripe), or the guest logs in meanwhile (login regenerates the id).
- Therefore the ownership check can only ever **reduce** what is shown, never block: a non-owner gets the thank-you page with a non-personal summary, the order is still created, and the confirmation email (with `Order::publicUrl()` once Category C adds it) is the reliable path back to the order.

### (g) Secrets in `.env.example` / docs / repo
- `.gitignore` contains `.env`, `.env.backup`, `.env.production`; `git ls-files` tracks only `.env.example`.
- Pattern scan for `sk_live|pk_live|rk_live|whsec_<long>|AKIA...|BEGIN PRIVATE` over the repo (excluding vendor/node_modules/build): no hits. `.env.example` holds placeholders only (`pk_test_your_...`, `whsec_your_webhook_secret_here`).
- Docs mention `sk_test_...` / `your-smtp-password` placeholders only.
- Concern: `.env.example` has `APP_DEBUG=true` and `APP_ENV=local`; a copy-pasted production deploy would expose stack traces. Documented in the checklist (step 5), not changed (B's file).

## Changes made

- Deleted `app/Providers/MailServiceProvider.php` and unregistered it (`bootstrap/providers.php`): it added `List-Unsubscribe: <APP_URL/unsubscribe>` (the URL is a 404) and `X-Brand-Logo|Name`, `X-Sender-Image` to every message; nothing else lived in it.
- `app/Mail/ContactMessage.php`, `app/Http/Controllers/ContactController.php`, `routes/web.php` (throttle) - see (c).
- `routes/web.php`: new route `GET /orders/{order}` -> `GuestOrderController@show` (`orders.guest.show`, `throttle:60,1`); removed `POST /guest/checkout/session`; dashboard counter `whereIn('status', ['completed','delivered'])` -> `where('status','delivered')`.
- `app/Models/Order.php`: `publicUrl()` (30-day `URL::temporarySignedRoute('orders.guest.show', ..., ['order' => order_number])`) and `isOwnedBy(?Authenticatable, ?string $sessionId)`.
- `app/Http/Controllers/GuestOrderController.php` + `resources/js/pages/Orders/GuestShow.tsx` + `Orders/GuestInvalid.tsx` + `guest_order.*` strings in `lang/{it,en,de,es,fr}.json`.
  - The signature is checked in the controller (`hasValidSignature()`), not with the `signed` middleware, so that a bad/expired signature renders the friendly Inertia page (HTTP 403, no raw error) instead of Laravel's bare `InvalidSignatureException`. Unknown order numbers answer exactly like a bad signature. Order numbers (`ORD-YYYY-` + 6 random digits, `Order::generateOrderNumber()`) are guessable (1M space), the signature (HMAC with `APP_KEY`) is what protects the page; nothing else is exposed (no email, phone, session id, Stripe ids).
  - Page headers: `Cache-Control: no-store, private`, `Referrer-Policy: no-referrer`, `robots noindex`.
- `CheckoutController::success()`: ownership gating, log hygiene (see next section).
- `StripeWebhookListener`, `EmailService`: webhook fail-closed guard, masked recipients; new `app/Support/Privacy.php`.
- `docs/reports/email-deliverability-checklist.md`.

## Success page contract (for Category C)

Props added/changed on `Checkout/Success`:
- `restricted: bool` - true when the viewer is not proven to be the buyer. Owner = logged-in user whose id equals `orders.user_id`, OR `orders.guest_session_id === session()->getId()` (guest orders only), OR a valid signature on the success URL itself.
- Owner: unchanged payload (`order` = `toFrontendArray()` minus `guest_session_id`, `session.customer_email` = real address).
- Restricted: `order` is whitelisted to `id, order_number, status, payment_status, created_at, currency, subtotal, tax_amount, shipping_amount, discount_amount, total_amount, orderItems`; `session.customer_email = ''`.
- `resources/js/pages/Checkout/Success.tsx` was NOT touched (Category C). It currently renders an empty "email" row for guests when `customer_email` is empty; C should hide that row (or show a generic "check your inbox") when `restricted`/empty.

## Out-of-scope findings (for the orchestrator)

1. `routes/web.php`: `POST /checkout/session` (authenticated legacy) was **kept**: `tests/Feature/Checkout/CheckoutTest.php` lines 95, 113, 135 exercise it (3 tests) and `Checkout/Index.tsx` (orphan page) posts to it. It uses `CheckoutService::createCheckoutSession` (USD-era code, lump-sum differences with the live flow). Recommended: remove route + `createSession()` + those 3 tests + `Checkout/Index.tsx`/`GuestIndex.tsx` together. `CheckoutController::createGuestSession()` is now dead code (route removed).
2. Dashboard frontend still has `completed` (not a DB status) in status switches: `Dashboard/components/LatestOrderCard.tsx:33,46`, `pages/dashboard.tsx:62,75` don't handle `delivered` at all (delivered orders render with the neutral colour/icon); `Dashboard/Orders.tsx` handles both. Cosmetic.
3. `docs/reports/email-logo-setup.md:14-16` documents the removed `X-Brand-*` headers; `.env.example` has `APP_DEBUG=true`.
4. `app/Console/Commands/SendTestEmail.php` (`email:send-test`) sends the text "EMERGENZA CATANZARO" to whichever address it is given - not safe for deliverability testing.
5. Log PII outside the files I may touch: `CheckoutService.php:260,297,327,365` (`customer_email`), `getTraceAsString()` in `CheckoutController` (cartCheckout, createSession, createGuestSession), `Admin\OrdersController:271,479`, `GoogleAuthController:147`, `Admin\ReviewsController:153`, `Admin\ProductController:580,678`.
6. `CheckoutController::show()` (`/checkout/show`, auth) renders any order/Stripe session by id without an ownership check.
7. `public/build` is gitignored; `npm run build` succeeded, `npm run types` only reports pre-existing errors in `Admin/Products/Create.tsx`.
8. Composer's optimized classmap still lists the deleted `MailServiceProvider` until `composer dump-autoload` runs (harmless: no provider references it; Docker image build regenerates it).

## Final verification

`vendor/bin/pest`: 319 passed, 1 skipped (same skip), 1512 assertions - includes 36 new tests in `tests/Feature/Security/` (GuestOrderPageTest, CheckoutSuccessPrivacyTest, ContactFormTest, TransactionalMailHeadersTest, StripeWebhookSecurityTest, LogHygieneTest, RouteHygieneTest). The ownership tests were mutation-checked (forcing `$isOwner = true` fails 5 of 7). `pint --test` passes on new files; `php -l` clean; `npx eslint`/`prettier --check` clean on `pages/Orders`; `npm run build` OK; `npm run types` only shows the pre-existing `Admin/Products/Create.tsx` errors.
