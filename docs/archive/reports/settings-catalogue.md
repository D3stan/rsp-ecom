# Settings catalogue (wired now vs deferred)

Source: the two exploration passes summarised in the follow-up plan (existing mechanisms + hard-coded tunables) and the code at the time of the settings
wave. "Wired" = declared in `config/settings.php` (admin page, validation, audit). "Consumer" = application code already reads it;
see `docs/admin-settings.md`, "Wired / not yet wired", for the current consumer of every key.

## A. Wired in the core catalogue (41 keys)

| Key | Type | Default | Public | Consumer today |
|---|---|---|---|---|
| store.name | string | APP_NAME | yes | footer, shared `name` prop, SEO titles, email layout/subjects, ship form |
| store.legal_name, store.vat_number, store.address | string/text | empty | yes | footer legal line, FAQ, ship form, page seeder |
| store.company_iban, store.account_holder | string | empty | no | ship-form prefill |
| store.currency | enum, **readonly** | EUR | yes | later (`formatMoney`) |
| contact.support_email | email | MAIL_SUPPORT_ADDRESS | yes | bridge -> `mail.support_address` (all emails) |
| contact.form_recipient | email | CONTACT_EMAIL | no | bridge -> `mail.contact_email` |
| contact.admin_notification_email | email | empty | no | `EmailService::adminAddress()` |
| contact.phone, contact.business_hours | string | empty | yes | contact page, FAQ, ship form, page seeder |
| social.instagram / tiktok / facebook | url | empty | yes | footer (`footer-social-links.tsx`) |
| tax.rate | float 0-100 | **22** | yes | `Setting::getTaxRate()` (checkout, service) |
| tax.prices_include_tax | bool | **true** | yes | `Setting::getPricesIncludeTax()` |
| shipping.dispatch_days_min/max, delivery_days_min/max | int (min <= max) | 1/2/3/5 (env) | yes | bridge -> `shipping.delivery_estimate.*` (emails) |
| shipping.destinations | countries | IT DE GB FR ES | yes | later (checkout list, validation, Stripe `allowed_countries`) |
| shipping.default_country | enum of destinations | IT | yes | later |
| shipping.carriers | json rows `{code,name,tracking_url}` | `config/shipping.php` | no | bridge -> `shipping.carriers` (tracking links, admin carrier select) |
| orders.min_amount | float, 0 = off | 0 | yes | later (cart + both checkouts) |
| orders.max_qty_per_line | int, 0 = unlimited | 0 | yes | `CartController`, product page |
| orders.guest_checkout_enabled | bool | true | yes | later (guest routes + button) |
| orders.public_link_days | int 1-365 | 30 | yes | `Order::publicUrlDays()`, emails, expired-link page |
| orders.order_notes_max | int 50-2000 | 500 | yes | later (backend 500 vs `Details.tsx` 200: align) |
| inventory.low_stock_threshold | int | 10 | yes | `Product::lowStock()`, admin lists/dashboard, wishlist cards |
| catalog.per_page | int 6-60 | 12 | yes | `CatalogPagination` (server clamp 6-60) |
| reviews.auto_approve | bool | true | no | `ReviewController::store` |
| email.enabled + email.order_confirmation/shipped/delivered/cancelled/refunded | bool | true | no | `EmailService` (direct) |
| tracking.auto_deliver | bool | env (true) | no | bridge -> `shipping.tracking.auto_deliver` |
| seo.default_description, seo.keywords | text, list | `config/seotools.php` | no | bridge -> `seotools.*` (works, tested) |

System panel (read-only, `SystemInfo`): withdrawal days, Stripe configured yes/no, mailer, tracking driver, queue connection,
queue health (last `ops:check-queue`), queue alert thresholds, job retries/timeouts/backoff, scheduled tasks, rate limits.

## B. Top 15 by value to the owner

1. tax.rate (the old UI wrote a key nothing read) 2. tax.prices_include_tax 3. contact.support_email 4. email.* switches
5. shipping delivery days (copy + emails) 6. shipping.destinations 7. shipping.carriers 8. orders.min_amount
9. orders.guest_checkout_enabled 10. tracking.auto_deliver 11. inventory.low_stock_threshold 12. catalog.per_page
13. contact.phone / business_hours / social 14. orders.public_link_days 15. reviews.auto_approve.

## C. Never editable in the admin (env, config or code only)

Stripe/PayPal keys and webhook secrets (`.env`; legacy DB copies removed by `settings:purge-legacy-secrets`), mail transport and
from address, `APP_KEY`/`APP_URL`/`APP_ENV`/`APP_DEBUG`, database and cache/queue connections, tracking provider token, tracking
webhook secrets, `store.currency` (existing orders), withdrawal days (legal), rate limits, queue retry/backoff/timeouts and alert
thresholds, supported locales, scheduler definitions, session/cookie names (`app.name` is not bridged).

## D. Values that exist in more than one place (to collapse)

| Value | Where it lives today | Target |
|---|---|---|
| Support email / phone / hours | 5 lang keys x 5 locales, TSX, old seeders, `config/mail.php` | `contact.*` |
| Supported locales | 7 lists in PHP/TSX | `config('app.supported_locales')` (done in wave 1) |
| Low-stock threshold | 4 definitions (admin lists, dashboard, wishlist badge, products) | `inventory.low_stock_threshold` |
| Products per page | 9 / 24 / 12 / 12 / 15 / 15 / 20 and unbounded `per_page` | `catalog.per_page` (clamp 6-60) |
| Delivery wording | 3-5 vs 5-7 vs config in lang + emails | `shipping.*_days_*` |
| Currency | EUR in 12+ places, `$` hard-coded in ~20 | `store.currency` + formatter |
| Signed order link duration | `Order::PUBLIC_URL_DAYS` + copy "30 days" | `orders.public_link_days` + `{days}` |
| VAT rate | `tax_rate` vs `default_tax_rate`, code defaults 0/false | `tax.rate` (one key) |
| Order note limit | backend 500, `Details.tsx` 200 | `orders.order_notes_max` |
| Checkout country list | `Details.tsx` options, Stripe `allowed_countries`, validation | `shipping.destinations` |

## E. Deferred candidates (not in the core set)

Key = proposed key; "Current location" = where the value lives now. Each is a few lines in `config/settings.php` once a consumer exists.

| Proposed key | Current location | Type |
|---|---|---|
| store.logo | `public/` asset, header + email layout | file (needs upload capability) |
| store.opening_year / copyright text | footer TSX (hard-coded year) | int |
| contact.business_hours_rows | lang strings per locale | json |
| seo.title_suffix | controllers (`' - '.$appName`) | string |
| seo.per_locale_copy | `HomeController` `$seoContent` | json per locale |
| seo.robots_default | `config/seotools.php` | enum |
| catalog.price_buckets | product filters (hard-coded ranges) | json |
| catalog.badges.new_days | product card ("new" window) | int |
| catalog.badges.low_stock_label | product card | bool |
| catalog.sort_default | `ProductsController` | enum |
| catalog.related_count | product page | int |
| catalog.featured_count | `HomeController` | int |
| cart.max_items | old `max_cart_items` (dead) | int |
| cart.guest_cart_days | session / cart cleanup | int |
| orders.number_prefix | order number generation | string |
| orders.cancel_window_hours | admin cancel flow | int |
| orders.pending_expiry_minutes | Stripe pending order cleanup | int |
| orders.stock_reserve_minutes | (stock wave) | int |
| shipping.cutoff_time | shipping page copy | time |
| shipping.holidays | none (delivery estimate ignores them) | list of dates |
| shipping.package_default_size | `sizes` table / checkout | int (FK) |
| reviews.min_length / max_length | review form validation | int |
| reviews.verified_only | `ReviewController` | bool |
| reviews.per_page | product page | int |
| wishlist.max_items | wishlist controller | int |
| email.from_name | `config/mail.php` `MAIL_FROM_NAME` (env, deliberately not bridged) | string |
| email.footer_text | email layout / lang | text per locale |
| email.retry_policy | jobs (`tries`, `backoff`) | never editable (see C) |
| tracking.poll_interval_minutes | `config/shipping.php` `tracking.poll.*` | int |
| tracking.poll_max_age_days | `config/shipping.php` | int |
| auth.password_min_length / lockout | auth config | never editable |
| analytics.ids (GA / pixel) | none yet | string |
| cookies.consent_text | `config/cookieconsent.php` + lang | text |
| maintenance.enabled | old `maintenance_mode` (dead) | use `artisan down` instead |

The exploration lists were not available verbatim to this wave: this table holds the candidates traceable to code reviewed
during the settings work and to the plan's findings. Remove or extend it when the explorers' full ~80-item list is
attached.
