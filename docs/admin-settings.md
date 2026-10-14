# Admin configuration system

One catalogue (`config/settings.php`) declares every setting the shop owner can see or change. The service, the
validation, the audit trail, the admin page and the props shared with the frontend are all generated from it.

```
config/settings.php            the catalogue: groups + definitions + legacy alias map
app/Settings/SettingType.php   type enum (coercion + shape check)
app/Settings/SettingDefinition one declared setting (immutable), builds its validation rules
app/Settings/SettingsRegistry  validated catalogue, lookups by key / group
app/Settings/Settings          the service: get / many / all / set / setMany / reset / source / public / group / history
app/Settings/ConfigBridge      overlays customised values onto config()
app/Settings/SystemInfo        read-only "System" panel data (no secrets)
app/Providers/SettingsServiceProvider   bindings, `manage-settings` gate, bridge + worker listeners
app/Http/Controllers/Admin/SettingsController + Requests/Admin/UpdateSettingsRequest
app/Models/Setting             storage row (+ deprecated legacy wrappers), app/Models/SettingChange (audit)
```

## Reading and writing

```php
settings('tax.rate');                       // 22.0 (float). Helper in app/Settings/helpers.php
app(\App\Settings\Settings::class)->get('orders.min_amount');
$settings->many(['tax.rate', 'tax.prices_include_tax']);
$settings->set('tax.rate', 10, $adminUser);  // validates, audits, bumps the cache version
$settings->setMany([...], $adminUser);       // one transaction, all or nothing
$settings->reset('tax.rate', $adminUser);    // drop the stored value: the default applies again
$settings->source('tax.rate');               // 'default' | 'customised'
$settings->public();                         // typed values of the public keys (shared Inertia prop)
```

- Values are typed (`float`, `int`, `bool`, `string`, `string[]`, rows): never numeric strings.
- Precedence: stored row > definition default (which may come from `.env`/config).
- Unknown key: `InvalidArgumentException`. `settings('x.y', $fallback)` returns the fallback for unknown keys.
- Invalid value: `Illuminate\Validation\ValidationException` (errors keyed by the full setting key).
- `access: readonly|env` settings (e.g. `store.currency`) cannot be written: `InvalidArgumentException`.
- Saving a value equal to the current one is a no-op (no row, no audit entry), so only real choices show as "customised".

## How to add a setting (about 5 lines)

1. In `config/settings.php`, `definitions`:

```php
['key' => 'orders.gift_wrap_fee', 'type' => 'float', 'default' => 0, 'required' => true, 'min' => 0, 'max' => 50, 'unit' => 'EUR', 'public' => true],
```

2. In all five `lang/*.json`: `"settings.orders.gift_wrap_fee.label"` and `"settings.orders.gift_wrap_fee.help"`.
3. Read it with `settings('orders.gift_wrap_fee')`. The admin page, validation, audit and (when `public`) the frontend
   prop pick it up. `tests/Unit/Settings/CatalogueTest.php` fails when a label is missing or a default breaks its rules.

Definition keys (all optional except `key`, `type`, `default`): `required`, `min`/`max` (value for numbers, length for
strings, item count for lists), `options` (enum), `unit`, `rules` (extra Laravel rule strings), `schema` (json rows),
`at_least` (min <= max against another key), `member_of` (value must be in another list setting), `public`,
`access` (`admin|readonly|env`), `config_bridge`, `bridge_format`, `risk` (`high` asks for confirmation), `depends_on`,
`sensitive` (audit never records the value), `seed`. A new group needs an entry in `groups` plus
`settings.group.<group>.label|description` in the five locales.

Types: `string`, `text`, `email`, `url`, `int`, `float`, `bool`, `enum`, `list` (strings), `countries` (ISO-2, upper-cased,
de-duplicated), `json` (table rows, validated by `schema`; used by `shipping.carriers`).

## Groups and ownership

| Group | Owns | Notes |
|---|---|---|
| store | name, legal name, VAT number, address, IBAN, account holder, currency | currency is read-only (EUR) |
| contact | support email, form recipient, owner notification email, phone, business hours | support/recipient are bridged to `mail.*` |
| social | instagram, tiktok, facebook | empty = hidden in the footer |
| tax | `rate`, `prices_include_tax` | the only VAT source |
| shipping | dispatch/delivery days, destinations, default country, carriers | days + carriers bridged to `shipping.*`; no free-shipping/flat-rate/zones |
| legal | `withdrawal_days` | read-only (14, from `config('shipping.withdrawal_days')`), public: the policy pages quote it as `{days}`, the order emails read the same config value |
| orders | min amount, max qty per line, guest checkout, public link days, order note length | |
| inventory | low stock threshold | |
| catalog | products per page (6-60) | |
| reviews | auto approve | |
| email | master switch + five order emails | |
| tracking | auto deliver | bridged to `shipping.tracking.auto_deliver` |
| seo | default description, keywords | bridged to `seotools.*` (see below) |
| system | read-only panel (`SystemInfo`) | not settings: values come from env/config/code |

## Storage and audit

`settings(key unique, value text JSON, type string, updated_by, timestamps)`. `type` is informational. `setting_changes`
(`key, old_value, new_value, user_id, ip, created_at`) gets one row per real change, written by the single write path in
`Settings`; values of `sensitive` definitions are recorded as `***` (none today). Every write also logs
`Settings changed` with the keys and user id, no values.

## Secrets policy

No secret is a setting, ever (a catalogue test rejects keys that look like one). Stripe/mail/tracking credentials live in
`.env`. Legacy credential rows (`stripe_secret_key`, `stripe_public_key`, `stripe_webhook_secret`, `paypal_*`) are not
touched by the migration (production may still use the Stripe key through `StripeService`'s fallback, and the owner may need
to copy the other values to `.env` first); they are read raw and never cached (the settings cache only holds catalogue keys),
never sent to the frontend. Once the keys are in the environment run:

```
php artisan settings:purge-legacy-secrets          # asks for confirmation; --force skips it
```

It refuses unless `config('cashier.secret')` and `config('cashier.key')` are both set, deletes `stripe_secret_key`,
`stripe_public_key`, `stripe_webhook_secret` and `paypal_*` rows, and prints only the key names.

## Cache and workers

- The registry-key rows are cached as one array under `settings:v{version}`; the version lives in `settings:version` in the
  shared cache store (`SETTINGS_CACHE_STORE`, default store; TTL 1 day). Every write, reset, `Settings::flush()`, model
  save/delete and the data migration bump it, which invalidates every process at once. Each `get()` costs one cache read
  (the version); the array is re-read only when the version changed (per-process memo).
- A missing row is never cached as its default. If the cache fails, reads go to the database; if the database fails
  (table missing during install, outage) reads return defaults. Nothing throws.
- **Config bridge** (`ConfigBridge`): definitions with `config_bridge` overlay `config()` with the *customised* value at
  boot, and again on `JobProcessing`, queue `Looping`, `ScheduledTaskStarting` and Octane's `RequestReceived` (a cheap
  version check when nothing changed), so workers follow the admin page without a restart. When a setting is reset the
  config value from before the first overlay is restored. Bridges: `contact.support_email -> mail.support_address`,
  `contact.form_recipient -> mail.contact_email`, `shipping.dispatch_days_*`/`delivery_days_* -> shipping.delivery_estimate.*`,
  `shipping.carriers -> shipping.carriers` (rows `{code,name,tracking_url}` become the array keyed by code that
  `TrackingManager`/`Order::trackingUrl()` read), `tracking.auto_deliver -> shipping.tracking.auto_deliver`,
  `seo.default_description -> seotools.meta.defaults.description` + `seotools.opengraph.defaults.description`,
  `seo.keywords -> seotools.meta.defaults.keywords`. `app.name` is deliberately not bridged (cache prefix, cookie names).
- **SEO bridge result:** it works. SEOTools registers its singletons lazily (deferred provider), after our provider booted, so
  the overlay is seen (`ConfigBridgeTest`, last case). A long-lived process that already resolved the singleton keeps the old
  text until restart.
- Defaults that come from `config/shipping.php` are read from that file directly, because `config/settings.php` loads before
  `shipping` (alphabetical order).

## Legacy keys

`config/settings.php` -> `legacy_aliases`. `Setting::get/set/getTaxRate/getPricesIncludeTax` are deprecated wrappers that map
these keys and delegate to the service; other keys are raw rows (uncached).

| Old key | New key |
|---|---|
| `tax_rate`, `default_tax_rate` | `tax.rate` |
| `prices_include_tax` | `tax.prices_include_tax` |
| `site_name` | `store.name` |
| `contact_email` | `contact.support_email` |
| `contact_phone` | `contact.phone` |
| `company_address` | `store.address` |
| `company_iban`, `company_account_holder` | `store.company_iban`, `store.account_holder` |
| `admin_notification_email` | `contact.admin_notification_email` |
| `email_notifications_enabled` | `email.enabled` |
| `order_{confirmation,shipped,delivered,cancelled,refunded}_enabled` | `email.order_*` |
| `minimum_order_amount` | `orders.min_amount` |
| `tax_number` (migration only) | `store.vat_number` |
| `social_media` JSON (migration only) | `social.instagram|tiktok|facebook` |

Migrations: `2026_10_14_100000_prepare_settings_for_registry` (type -> string, `updated_by`, `setting_changes`) and
`2026_10_14_100100_merge_legacy_settings` (data). The data migration parses mixed `''/'0'/'1'/'true'`/numeric strings by the
target type, prefers `tax_rate` over `default_tax_rate`, skips empty or invalid legacy values (an invalid one stays in place,
logged), never overwrites an existing new-key row, deletes the dead keys (`site_description`, `default_currency`,
`timezone`, `stripe_enabled`, `stripe_webhook_secret`, `paypal_*`, `shipping_enabled`, `free_shipping_threshold`,
`default_shipping_cost`, `shipping_calculation_method`, `allow_international_shipping`, `processing_time_days`,
`shipping_zones`, `tax_enabled`, `tax_calculation_method`, `collect_tax_for_digital_products`, `newsletter_enabled`,
`from_email`, `from_name`, `stripe_settings`, `email_settings`, `maintenance_mode`, `products_per_page`, `max_cart_items`) and
leaves `stripe_secret_key`/`stripe_public_key`. It is idempotent. `down()` rewrites legacy rows from the new ones (first alias
per key) and deletes all catalogue rows; deleted dead keys and values changed since only exist in the new keys, so they
cannot be restored.

## Defaults that differ from the old behaviour

- `tax.rate` defaults to **22** and `tax.prices_include_tax` to **true**. The old code defaulted to 0 / false when no row
  existed (`Setting::getTaxRate()`); production rows (if any) are kept as explicit rows by the data migration. A database
  with no tax row now computes 22% VAT included instead of 0.
- `orders.order_notes_max` is **500** (the backend limit); `Checkout/Details.tsx` takes its `maxLength` from the setting.
- `contact.support_email` / `contact.form_recipient` / `store.name` default to `MAIL_SUPPORT_ADDRESS`, `CONTACT_EMAIL`,
  `APP_NAME`, as before; the seeder does not freeze them.
- A placeholder `admin_notification_email` (`admin@rspecommerce.com`) written by the old seeder becomes an explicit
  `contact.admin_notification_email` row: check it in the admin page after deploying.
- Saving an invalid value through `Setting::set()` now throws a `ValidationException` (it used to store anything).

## Wired / not yet wired

Every catalogue key has at least one consumer; the old hard-codes and duplicates were removed with them.

| Key(s) | Consumers |
|---|---|
| `store.name` | footer + home footer (`useSetting`), shared `name` prop, SEO titles/JSON-LD in the controllers, email layout/subjects, ship-form prefill. **Display only**: `app.name` still names caches and cookies |
| `store.legal_name`, `store.vat_number`, `store.address` | footer legal line (`footer-legal-line.tsx`, each part hidden when empty), FAQ "physical store" answer, `PageSeeder`/`PageFactory`, ship form (address) |
| `store.company_iban`, `store.account_holder` | ship-form prefill (admin only, not public) |
| `store.currency` | checkout (read-only) |
| `contact.support_email` | bridge to `mail.support_address` (all emails), contact page, FAQ, `{email}` in privacy/terms/shipping-returns copy, ship form |
| `contact.form_recipient` | bridge to `mail.contact_email` (contact form, owner-address fallback) |
| `contact.admin_notification_email` | `EmailService::adminAddress()` |
| `contact.phone`, `contact.business_hours` | contact page cards and FAQ answers (hidden when empty), ship form (phone), page seeder |
| `social.instagram|tiktok|facebook` | `footer-social-links.tsx` in both footers (a link is rendered only when set; the whole column hides when none is) |
| `inventory.low_stock_threshold` | `Product::lowStock()` / `wellStocked()` scopes (admin list stats + filter, dashboard alert), `useSetting` in admin product list/detail and the wishlist cards. "Low" = `0 < stock <= threshold` |
| `catalog.per_page` | `App\Support\CatalogPagination` (products + category lists); a `per_page` query value is honoured but clamped to 6-60 |
| `orders.max_qty_per_line` | `CartController::add|update` (0 = unlimited, shown on the product page quantity selector) |
| `orders.public_link_days` | `Order::publicUrlDays()` (`publicUrl()`, "valid for N days" in emails), `{days}` in the expired-link page |
| `orders.min_amount`, `orders.guest_checkout_enabled`, `orders.order_notes_max`, `shipping.destinations`, `shipping.default_country`, `tax.*`, `store.currency`, `legal.withdrawal_days` | checkout / VAT / policy copy |
| `shipping.dispatch_days_*`, `shipping.delivery_days_*`, `shipping.carriers`, `tracking.auto_deliver`, `seo.*` | config bridge (consumers read `config('shipping.*')` / `config('seotools.*')`) |
| `reviews.auto_approve` | `ReviewController::store` (off: stored unapproved, translated "awaiting moderation" flash) |
| `email.enabled`, `email.order_*` | `EmailService::isEnabled()` (read directly through `settings()`) |

Not wired: nothing in the core set. Still hard-coded by design (outside the catalogue): the page `<title>` suffix in
`app.tsx`/`ssr.tsx` (`VITE_APP_NAME`, build-time), and `CheckoutController` SEO titles (`config('app.name')`).

## Admin endpoints

| Route name | Method + URL | Purpose |
|---|---|---|
| `admin.settings` | `GET /admin/settings` | Inertia `Admin/Settings/Index`: `groups`, `system`, `historyLimit` |
| `admin.settings.update` | `PATCH /admin/settings/{group}` | save fields of one group (body: short field names) |
| `admin.settings.reset` | `DELETE /admin/settings/{key}` | reset one key (`tax.rate`) to its default |
| `admin.settings.history` | `GET /admin/settings/{group}/history` | JSON `{data: [...]}`, last 20 changes |

All behind `auth`, `verified`, `admin` and the `manage-settings` gate. Success -> `flash.success`; nothing changed ->
`flash.warning`; failure -> `flash.error`; validation errors keyed by short field name (`rate`, `destinations.0`,
`carriers.1.code`).

## Using settings in React

The `public` settings are shared with every Inertia page as the `settings` prop (typed as `PublicSettings` in
`resources/js/types/settings.d.ts`). Read them with the hooks in `resources/js/hooks/use-setting.ts`:

```tsx
import { useSetting, useSettings } from '@/hooks/use-setting';

const days = useSetting('orders.public_link_days', 30);   // number (the fallback applies only if the prop is missing)
const phone = useSetting('contact.phone');                 // string | undefined
const { 'tax.rate': rate, 'store.currency': currency } = useSettings();   // Partial<PublicSettings>
```

- Keys are typed (`SettingKey = keyof PublicSettings`): a typo or a non-public key is a compile error. A new public
  setting needs one line in `PublicSettings`.
- Without a fallback the value is `T | undefined`; with one it is `T`. `null`/`undefined` props use the fallback.
- Texts that quote a setting use `{placeholders}` in `lang/*.json`: `t('key', { days })`.

The admin page itself (`resources/js/pages/Admin/Settings/Index.tsx`, parts in `resources/js/components/admin/settings/`)
is generated from the `groups[].fields[]` payload: one `SettingFieldRow` dispatching on `type` to a small input component
(`fields/`), one save per group (only changed fields are sent), per-field reset, a confirmation for `risk: high`, a
History tab (lazy `GET admin.settings.history`) and the read-only System panel. The active group is kept in the URL hash
(`/admin/settings#tax`). Adding a setting to the catalogue needs no change in the page.

## Commands

`php artisan settings:list [--group=tax]` (key, value, source; no secrets), `php artisan settings:purge-legacy-secrets`,
`php artisan db:seed --class=SettingsSeeder` (inserts missing rows only, skips env-derived defaults).
