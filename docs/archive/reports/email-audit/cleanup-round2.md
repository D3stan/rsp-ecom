# Cleanup round 2 (item H)

What was removed or changed, the evidence that nothing used it, and what was left alone.

## Removed

| What | Evidence of no callers |
|---|---|
| `POST /checkout/session` (`checkout.session`), `CheckoutController::createSession`, `createGuestSession` | the only caller was the orphan page `Checkout/Index.tsx`; `createGuestSession` had no route since round 1 |
| `CheckoutService::createCheckoutSession`, `createStripeLineItems`, `createGuestCheckoutSession`, `createGuestCheckoutSessionFromCart`, `createGuestLineItems`, `calculateGuestTotals` | only referenced by the two controller methods above (grep over `app tests routes database`) |
| `GET /checkout/show` (`checkout.show`), `CheckoutController::show`, `Checkout/Show.tsx` | no link or `route('checkout.show')` anywhere; it returned a Stripe session object without an ownership check |
| `resources/js/pages/Checkout/Index.tsx`, `GuestIndex.tsx`, `resources/js/pages/dashboard.tsx` | no `Inertia::render('Checkout/Index' \| 'Checkout/GuestIndex' \| 'dashboard')`, no import; the `dashboard` route renders `Dashboard/Dashboard` |
| `Admin/CategoriesController`, `Admin/SizesController`, `Admin/ProductControllerV2` | not in `routes/admin.php` (categories and sizes are served by `CategoriesAndSizesController`); no reference in `app tests routes config resources docs`; their pages (`Admin/Categories/Index`, `Admin/Sizes/Index`) did not exist |
| 3 `CheckoutTest` cases on `/checkout/session`, 2 on `/checkout/show` | covered the removed code; route absence is asserted in `RouteHygieneTest` |
| `appendOutputTo(pending-order-emails.log)` | 10-minute command output, never rotated; failures and backlog warnings already go to `laravel.log` |
| Unused imports in `CheckoutController` / `CheckoutService` | left behind by the removals |

Kept on purpose: `CheckoutService::calculateTotals` (+ its `calculateShipping` fallback) and `retrieveCheckoutSession` (used by `CheckoutServiceTest`; a later wave rewrites
shipping/VAT there), the private `CheckoutController::calculateShippingCost` (dead, but part of the free-shipping removal owned by a later wave),
`Setting` and `Admin/SettingsController` (rewritten in the settings wave).

## Changed

- **Cashier routes:** `Cashier::ignoreRoutes()` in `AppServiceProvider::register`; `POST stripe/webhook` re-declared in `routes/web.php` as `cashier.webhook` with
  `Laravel\Cashier\Http\Controllers\WebhookController@handleWebhook` and `->withoutMiddleware('web')` (the package's own routes were registered without the web group too).
  Signature check unchanged: the controller constructor adds `VerifyWebhookSignature` when `STRIPE_WEBHOOK_SECRET` is set, and `StripeWebhookListener` fails closed in production
  without a secret. `stripe/webhook` added to the CSRF exceptions in `bootstrap/app.php`. `GET stripe/payment/{id}` no longer exists. The Stripe Dashboard URL is unchanged.
  Note: `CASHIER_PATH` is no longer honoured (the path is fixed at `/stripe/webhook`).
- **Mailables:** `tags:` and `metadata:` removed from every `app/Mail/*` envelope (they produced `X-Tag` / `X-Metadata-*` headers with order and user ids).
- **Logging:** no `getTraceAsString()` anywhere in `app/` (CheckoutController cart checkout, GoogleAuthController, ReviewsController, Admin ProductController x2, Admin OrdersController x2);
  `request_data => $request->all()` removed from `OrdersController::update` (now field names only) and `ReviewsController` bulk update (now action + count).
  Error logs carry exception class + message + ids. No raw `customer_email` was left in any log (the remaining occurrences are Stripe parameters, a page prop for the owner, and the admin alert mail).
- **`env()`:** `StripeService`, `CheckoutController`, `CheckStripeConfig` read `config('cashier.secret'|'key'|'currency')`; env still wins over the DB fallback in `StripeService`.
  Caveat: the guest checkout used `env('CASHIER_CURRENCY', 'eur')`; Cashier's own default is `usd`, so with `CASHIER_CURRENCY` unset the guest session would now be `usd`.
  `.env.example` sets `eur`; make sure production has it. The Dockerfile comment was reworded (config cache still off).
- **Locales:** `config('app.supported_locales')` = `['en','es','fr','de','it']` (this order, because Symfony's Accept-Language matching returns the first entry when the browser sends no preference, and
  `DetectLanguage` always used it), helper `App\Support\Locales`; used by `DetectLanguage`, the two routes in `routes/web.php`, `OrderCreationService`, `SendOrderEmail`, `SendWelcomeEmail`, `SendUserNotification`.
  `OrderCreationService::SUPPORTED_LOCALES` stays as a deprecated literal alias (a constant cannot read config); `LocalesTest` pins it to the config. `HandleInertiaRequests` shares `supportedLocales`;
  `TranslationProvider` receives it from `app.tsx` and `header.tsx` renders the language selects from it (static fallback `DEFAULT_SUPPORTED_LOCALES`, checked against the config by a test).
  `OrderCreationService::FALLBACK_LOCALE` (`it`) was not touched: it differs from `app.locale` (`en`) and tests rely on it.
- **Defaults:** `.env.example` `APP_DEBUG=false`, `LOG_STACK=daily`, `LOG_LEVEL=info` (comment: production must set `APP_ENV=production`); sitemap schedules use `sendOutputTo` (overwrite, bounded);
  comment in `deploy.php` about `config:cache`.
- **Dashboard:** `LatestOrderCard` and `Orders.tsx` handle `processing` (indigo, package icon) and `delivered` (green, check), `completed` dropped (also from `canReviewOrder`);
  `orders.status.completed` removed from the five `lang/*.json` (no other use; `orderStats.completed` is a different, server-side counter of delivered orders and stays).
- **Docs:** `email-logo-setup.md` and `checkout-fixes-implementation.md` rewritten; OPEN notes closed in `security.md` and `delivery-infra.md`; `email-operations.md` log note updated.
- **Test infrastructure:** the suite now exceeds 128 MB in one process (the queue-worker test runs `queue:work` in-process, whose default `--memory=128` stopped it): `--memory=1024` in
  `MailInfrastructureTest` and `memory_limit=512M` in `phpunit.xml`.

## Tests added or changed

`RouteHygieneTest` (removed routes, Cashier payment page, webhook declaration), `StripeWebhookSecurityTest` (bad signature 403 in any env, `WebhookReceived` dispatched, URL),
`TransactionalMailHeadersTest` (no `X-Tag` / `X-Metadata-*` on 10 mailables, no `tags:`/`metadata:` in `app/Mail`), `LogHygieneTest` (admin update, failure without trace, source scan),
`ConfigHygieneTest` (no `env()` in `app/`, Stripe keys from config, `.env.example` defaults), `LocalesTest` (new). Removed: 5 `CheckoutTest` cases.

## Skipped / follow-ups

- `docs/reports/email-testing-guide.md` still references `test:checkout-email` (not touched).
- Older reports (`phase2-completion-report.md`, `cashier-implementation-corrections.md`, `guest-checkout-fix.md`, `checkout-implementation-map.md`) still describe the removed routes and pages; they are historical.
- `Route::fallback` answers unknown URLs (e.g. `/stripe/payment/x`) with the Inertia not-found page and HTTP 200, not 404; that is existing behaviour for every unknown GET.
- `CheckoutController::calculateShippingCost` / `CheckoutService::calculateShipping` still contain the "free shipping" rule (removal belongs to the free-shipping/VAT wave).
