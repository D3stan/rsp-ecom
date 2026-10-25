# Category C - Customer UX & email content: discovery log

Line numbers refer to the templates/code as they were before this category (HEAD `b7ac81b` plus Waves 1-2), unless a path says otherwise.

## Baseline

`vendor/bin/pest` before any Category C change: 319 passed, 1 skipped (1512 assertions).
Final: 383 passed, 1 skipped (4546 assertions), default 128M memory limit (the matrix tests are written as one test per locale on purpose: one test per mailable x scenario x locale exhausted the memory limit).

## Discovery results

### 1. VAT: what is really charged

- Prices are VAT-inclusive and no tax is added on top. `CheckoutController::guestCartCheckout` (`:931` "prices are tax-inclusive") and `cartCheckout` charge ONE Stripe line item = items + shipping - discount (`unit_amount => $totalAmountCents`); Stripe tax is not enabled, so `total_details.amount_tax` is 0 and `orders.tax_amount` is always 0 (A, order-flow.md).
- The only place a rate is applied is the private `calculateTaxAmount()` (`CheckoutController.php:730`) used for display data; it reads `Setting::getTaxRate()` = key **`tax_rate`** (`Setting.php:126`, seeded 22 by `SettingSeeder.php:33`).
- The admin Tax settings screen (`SettingsController.php:118-129`, `Admin/Settings/Index.tsx`) writes a different key, **`default_tax_rate`** (seeded 22 by `SettingsSeeder.php:46`). Nothing reads it. Changing the rate in the admin therefore changes nothing.
- The rate is not snapshotted on the order, the rule is not applied to shipping or to the discounted base, and `tax_enabled` / `prices_include_tax` are ignored by the totals.
- **Decision:** a VAT amount cannot be derived reliably. The emails show the totals (items, discount, shipping, total) and the note "IVA inclusa" (localised) with no amount and no rate. Nothing reads `orders.tax_amount`. To show "of which VAT" later, the order must store the rate (and the base it applied to) at creation time (Category A territory).

### 2. Site copy: contradictions (NOT edited, for the owner)

| Topic | Source | Says |
|---|---|---|
| Return window | `lang/*.json` `faq_items.returns.answer_1` | returns accepted within **30 days** |
| Return window | `shipping_returns.withdrawal_text`, `step_1`, `refund_text` | **14 days** from delivery |
| Who may return | `shipping_returns.no_free_returns`, `no_change_mind` | only defective / wrong / damaged items; no change-of-mind returns |
| Who may return | FAQ `returns.answer_2` / `answer_4` | free return labels for all returns; exchanges for size/colour |
| Legal | `shipping_returns.withdrawal_text` | restricts the EU withdrawal right to three defect cases. For distance sales the statutory 14-day right applies without giving a reason (Directive 2011/83/EU); the page should be reviewed by the owner/legal advisor. The emails state the statutory right, as decided. |
| Standard delivery | `shipping_returns.standard_delivery`, `dispatch_text` | dispatch 1-2 business days, delivery **3-5** |
| Standard delivery | FAQ `shipping.answer_1` | **5-7** business days (EUR 5.99), express 2-3, next day available |
| Express | `shipping_returns.express_delivery` | 1-2 business days (FAQ says 2-3) |
| Free shipping | `shipping_returns.shipping_costs_text`, FAQ | free over EUR **50** |
| Free shipping | `CheckoutController.php:503,544` (`>= 100`), `SettingSeeder` `free_shipping_threshold = 100` | free over EUR **100** (and `guestCartCheckout` charges full shipping regardless, see A) |
| Contact | FAQ / `shipping_returns.contact_email` | `support@rsp-industries.com`, `+1 (555) 123-4567`, EST hours, a US-style placeholder address; emails use `config('mail.support_address')` |
| Placeholders | `terms.governing_law_text` | contains `[La Tua Giurisdizione]` |

### 3. Delivery-estimate source

One source: `config('shipping.delivery_estimate')` (`config/shipping.php`, env `SHIPPING_{DISPATCH,DELIVERY}_DAYS_{MIN,MAX}`), default 1-2 days dispatch + 3-5 days delivery = the shipping page (`shipping_returns`). The confirmation says "prepared and shipped within 1-2 business days, delivery usually 3-5 business days after dispatch"; the shipped email says "estimated delivery 3-5 business days from dispatch". The old values (confirmation 3-5, shipped 2-5) are gone. `Checkout/Success` step 2 ("1-2 working days") already agrees.

### 4. Locale handling

- `DetectLanguage` (session, `?locale=`, Accept-Language; `it|en|de|es|fr`; default `config('app.locale')` = `APP_LOCALE`, `en` in `.env.example`) sets the request locale; `ShareTranslations` shares the current-locale JSON file to Inertia. The frontend `t()` resolves NESTED keys; the emails use FLAT dotted keys (`emails.*`), which only `__()` can read. Both live in the same JSON files.
- JSON translations do not fall back to `fallback_locale` (a missing key renders raw). Hence the parity test.
- Order mails: `SendOrderEmail` applies `orders.locale` (`Mail::to()->locale()`); Mailable rendering (subject, `envelope()`, `content()`, view) all run inside that locale.
- Account mails: users have no locale column. `PendingUserEmailVerification` (queued) pins `app()->getLocale()` in its constructor (`Notification::locale()`); `SendWelcomeEmail` captures it in a `locale` property when constructed (inside the request, by `EmailService::sendWelcomeEmail`) and applies it with `Mail::to()->locale()`; password-reset and email-verification are sent synchronously in the request, so the request locale applies. Verified with tests that dispatch under one locale and run the job under another (mutation-checked).
- `SendOrderEmail::LOCALE_PROBE_KEYS` / `Lang::hasForLocale` guard removed (all five locales now have every key).

### 5. Account emails today (before)

- Password reset: framework `ResetPassword` (English default, no plain-text branding, "Laravel" footer) - now branded via `ResetPassword::toMailUsing()` registered in `User::booted()`; the notification class is still the framework's (existing tests assert it).
- Email verification for existing accounts (`MustVerifyEmail`, `EmailVerificationNotificationController`): framework `VerifyEmail`, English - now branded the same way.
- Pending-user verification: English literals, `<style>`-based layout (`pending-user-verification.blade.php:7`), subject `'Verify Your Email Address - '` (`PendingUserEmailVerification.php:50`).

### 6. Hard-coded strings and routes in the old templates

- Italian-only: `order-confirmation.blade.php` (title `:23`, "Ciao ... Cliente" `:38`, table headers, "Gratuita" `:98`, info block `:158-170`, footer `:184-191`), `order-shipped.blade.php` (`:38`, "Usa questo link per tracciare il tuo pacco" `:50` with no link, "2-5 giorni" `:57`), `welcome.blade.php` (whole file, `:53` "Inizia a fare shopping" -> `route('products')`), subjects in `OrderConfirmation.php:36`, `OrderShipped.php:37`, `WelcomeEmail.php:35`. All three templates said `lang="it"`.
- English-only: `pending-user-verification.blade.php` (whole file), `TestEmail` subject/view, framework reset/verify mails.
- Hard-coded money/VAT: `€{{ number_format() }}` (`order-confirmation:77,78,88,93,102`), `IVA (22%)` (`:92`), subtotal = `subtotal - tax_amount` (`:88`), `d/m/Y H:i` date (`:30`). Discount and coupon never shown.
- Wrong/dead links: `route('dashboard')` for guests (`order-confirmation:135`, `order-shipped:83`; guests cannot open it), `route('products')` "Continua Shopping" (`:143`, marketing), `route('about')`; contact address printed as `config('mail.from.address')` in the welcome mail (the no-reply address).
- Policy text: "30 giorni per resi gratuiti" (`order-confirmation:169`), delivery 3-5 (`:165`) vs 2-5 (`order-shipped:57`).
- Routes used by the new templates: `terms`, `shipping-returns`, `privacy`, `contact` (footer + withdrawal block), `orders.guest.show` through `Order::publicUrl()` (guests), `orders.index` (members), `dashboard` (welcome), `admin.orders.show` (admin alert). `route('products')`, `route('about')` are no longer used.

### 7. Gmail/Outlook-unsafe CSS in the old templates

- `display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 30px` (`order-confirmation:158`) - unsupported in Gmail/Outlook.
- Layout in a `<style>` block (`partials/styles.blade.php`, 422 lines; `pending-user-verification:7`): Gmail strips/limits it, Outlook ignores part of it; `display:flex`/`gap` in `.order-details`, `.product-info`, `.price-row`, `.feature-item` etc. (`styles.blade.php:57-64,129,152,208-211,347`); `* { margin:0 }` reset; `<h1>`-in-`div` cards relying on classes.
- No explicit colour on many cells (dark-mode clients can invert text onto a dark background); 11px grey notice text; emoji in headings.
- The logo `public/images/rlogo.png` is near-black on transparent: on the old black header it was invisible. The new header keeps the black band and puts the logo on a white tile.
- The Outlook "ghost table" button pattern was only on two buttons.

### 8. intl

`php -m` has `intl` locally; the Dockerfile (`serversideup/php:8.3-fpm-nginx-v4.5.1` + `install-php-extensions bcmath exif`) does not install it and the base image does not document it, so it is not guaranteed in production. The mails do not use `Number::currency`: `DescribesOrder::money()` and `formatDate()` (Carbon `isoFormat('LL')`, Carbon's own locale files) need no extension. E's `Number::currency` branch was removed.

## Changes made

- `config/shipping.php` (new): delivery estimate, withdrawal days (14), `carriers` (empty, documented example, no invented URLs).
- `Order`: `trackingUrl(?string $number)` (config template, forward-compatible with the future `tracking_carrier` column, http(s) templates only, number URL-encoded), `trackingCarrierName()`, `PUBLIC_URL_DAYS` (single constant for `publicUrl()` and the "valid for N days" copy).
- Layout `emails/layouts/base.blade.php` (+ `base-text`): 600px max-width tables, inline styles, preheader, Outlook ghost table, explicit colours everywhere, `color-scheme: light`, `<style>` only for mobile padding/stacking. Partials: `paragraph`, `section-title`, `facts`, `items`, `totals`, `address`, `button` (bulletproof table button), `withdrawal`, `no-reply-notice` (+ text twins). The contact-form notification is untouched.
- Rebuilt on it, each with a plain-text twin: confirmation, shipped, cancelled, delivered, refunded, welcome, pending-user verification, password reset (new), account verification (new), admin new-order, admin paid-order-missing (internal variant: no footer links, no notice), test email (`test-logo` removed).
- `DescribesOrder` rewritten: relations, greeting name (account name; guests: billing, then shipping first name; localised generic fallback), item lines (image with fallback, name, variant, size, SKU, qty x unit, total), totals that always sum to the stored total (a legacy row that does not add up is reconciled from the charged total), no-intl money/date formatting, delivery estimate.
- `Success.tsx`: `restricted`, order number as the single reference, hidden empty email row, check-your-inbox notice for guests/restricted viewers, discount row, locale-aware dates and currency, "VAT included" note, `shipping_amount` (the page read `shipping_cost`, which the backend never sends, so shipping always showed as free).
- `HandleInertiaRequests`: shares `flash {success,error,warning}`; `admin-layout.tsx` turns them into toasts (existing `ToastContext`; titles from `flash.*`).
- Translations: 122 flat `emails.*` keys in all five files (it/en kept, de/es/fr written), plus `flash.*` and 5 `checkout.success.*` keys.
- `SendWelcomeEmail` (locale), `SendOrderEmail` (probe removed), `User::booted()` (reset/verify branding), `AccountMailMessages`.

## Tests (`tests/Feature/Mail/Content/`)

`EmailTranslationsTest` (key parity across 5 locales, no empty values, identical `:placeholders`, every used key exists and no dead key, no literal sentences in the views), `EmailRenderingMatrixTest` (8 mailables x 7 order scenarios x 5 locales, plus account/internal mails; no raw keys, no unreplaced placeholders, escaping, Gmail-safe CSS, notice present/absent, links absolute), `OrderEmailContentTest` (money per locale, totals sum, VAT never an amount, coupon/free shipping, item options, signed guest link vs member link, `trackingUrl()`, tracking button, one delivery source, withdrawal block, notice design), `AccountEmailsTest` (locale captured at dispatch, run under another locale; reset/verify branded), `SuccessPageAndFlashTest` (success props the page reads, flash shared prop). Existing tests adjusted: `LifecycleMailTest` (first-name greeting; the "locale fallback" test now asserts de/es/fr are translated), `PendingUserVerificationTest` (translated subject).

## Out of scope / for the owner

1. Site pages: the contradictions in section 2 (not edited).
2. `tax_rate` vs `default_tax_rate` settings mismatch (section 1); a VAT line needs a stored rate.
3. `Order::toFrontendArray()` always returns `size => null` (`Order.php`, "no size relation") although `order_items.size_id` is populated: the success page cannot show sizes. `CheckoutController::success` does not eager-load `orderItems.size`.
4. Flash toasts: admin pages that already toast after their own `router` call will now also show the controller's flash message (two toasts for the same action). Going back in the browser re-shows the flash of the page restored from history.
5. Hard-coded English flash messages in controllers (`PendingUserVerificationController`, admin controllers) are shown as they are.
6. `PaidOrderMissingAlert`/admin alerts use the app locale (`APP_LOCALE`), not the owner's language setting.
7. de/es/fr (and the en wording) deserve a native-speaker skim, especially the withdrawal paragraph and the refund timing sentence.
