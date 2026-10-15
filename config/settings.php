<?php

/*
|--------------------------------------------------------------------------
| Admin settings catalogue (docs/admin-settings.md)
|--------------------------------------------------------------------------
|
| The only place that declares which settings exist. The service
| (App\Settings\Settings), the admin page, the validation, the audit trail and
| the shared frontend props are all generated from this file.
|
| To add a setting: add one array to `definitions` and two translation keys per
| locale (`settings.<key>.label` and `settings.<key>.help`).
|
| Definition keys
|   key          "<group>.<name>", the group must be listed in `groups`
|   type         string | text | email | url | int | float | bool | enum | list | countries | json
|   default      value used while no row exists; env()/config() are allowed here. This file is
|                loaded after app/mail/cashier/seotools (alphabetical) but BEFORE shipping, so
|                config() reads their values; shipping values come from the $shipping array below.
|   required     false (default): empty strings are accepted
|   min / max    numbers: value bounds; strings: length; list/countries/json: item count
|   options      enum: allowed values
|   unit         display hint ("%", "days", "EUR"...)
|   rules        extra Laravel validation rules (strings only, so the page can summarise them)
|   schema       json: validation rules of every column of a row, e.g. ['code' => ['required']]
|   at_least     another key whose value this one may not be lower than (min <= max)
|   member_of    another key (a list) this value has to be part of
|   public       true: shared with the frontend (`settings` Inertia prop). Never for anything private.
|   access       admin (editable) | readonly (shown, not editable) | env (shown, set in .env)
|   config_bridge  config path(s) overlaid with the stored value at boot, in every worker
|   bridge_format  keyed_by_code: list of rows => array keyed by `code` (shipping.carriers)
|   risk         normal | high (the page asks for confirmation)
|   depends_on   another bool key; the field is only relevant while that one is on
|   sensitive    true: the audit trail records that the value changed, never the value itself
|   seed         false: the seeder does not insert the default (env-derived or auto-following values)
|
| Secrets (API keys, passwords, tokens) are never settings. They live in .env.
|
*/

// config/shipping.php is loaded after this file (alphabetical order), so read its array directly.
$shipping = require __DIR__.'/shipping.php';

$carriers = [];
foreach ((array) ($shipping['carriers'] ?? []) as $code => $carrier) {
    $carriers[] = [
        'code' => (string) $code,
        'name' => (string) (is_array($carrier) ? ($carrier['name'] ?? $code) : $code),
        'tracking_url' => (string) (is_array($carrier) ? ($carrier['tracking_url'] ?? '') : ''),
    ];
}

return [

    'cache' => [
        // null = the default cache store
        'store' => env('SETTINGS_CACHE_STORE'),
        'ttl' => 86400,
        // Seconds a process trusts its in-memory copy before looking at the shared version again
        // (queue workers and the scheduler always re-check at the start of each job/tick).
        'recheck_seconds' => (int) env('SETTINGS_RECHECK_SECONDS', 5),
    ],

    'groups' => [
        'store' => ['icon' => 'Store'],
        'contact' => ['icon' => 'Phone'],
        'social' => ['icon' => 'Share2'],
        'tax' => ['icon' => 'Percent'],
        'shipping' => ['icon' => 'Truck'],
        'legal' => ['icon' => 'Scale'],
        'compliance' => ['icon' => 'ShieldCheck'],
        'orders' => ['icon' => 'ShoppingCart'],
        'inventory' => ['icon' => 'Boxes'],
        'catalog' => ['icon' => 'LayoutGrid'],
        'reviews' => ['icon' => 'Star'],
        'email' => ['icon' => 'Mail'],
        'tracking' => ['icon' => 'MapPin'],
        'seo' => ['icon' => 'Search'],
        'newsletter' => ['icon' => 'Newspaper'],
        'system' => ['icon' => 'Server', 'readonly_panel' => true],
    ],

    'definitions' => [

        // ------------------------------------------------------------------ store
        ['key' => 'store.name', 'type' => 'string', 'default' => config('app.name'), 'required' => true, 'max' => 120, 'public' => true, 'seed' => false],
        ['key' => 'store.legal_name', 'type' => 'string', 'default' => '', 'max' => 160, 'public' => true],
        ['key' => 'store.vat_number', 'type' => 'string', 'default' => '', 'max' => 40, 'public' => true],
        ['key' => 'store.address', 'type' => 'text', 'default' => '', 'max' => 500, 'public' => true],
        ['key' => 'store.company_iban', 'type' => 'string', 'default' => '', 'max' => 40, 'rules' => ['regex:/^[A-Za-z0-9 ]*$/']],
        ['key' => 'store.account_holder', 'type' => 'string', 'default' => '', 'max' => 160],
        ['key' => 'store.currency', 'type' => 'enum', 'default' => 'EUR', 'options' => ['EUR'], 'required' => true, 'access' => 'readonly', 'public' => true],

        // ---------------------------------------------------------------- contact
        ['key' => 'contact.support_email', 'type' => 'email', 'default' => (string) config('mail.support_address'), 'required' => true, 'public' => true, 'config_bridge' => 'mail.support_address', 'seed' => false],
        ['key' => 'contact.form_recipient', 'type' => 'email', 'default' => (string) config('mail.contact_email'), 'required' => true, 'config_bridge' => 'mail.contact_email', 'seed' => false],
        ['key' => 'contact.admin_notification_email', 'type' => 'email', 'default' => ''],
        ['key' => 'contact.phone', 'type' => 'string', 'default' => '', 'max' => 50, 'public' => true],
        ['key' => 'contact.business_hours', 'type' => 'string', 'default' => '', 'max' => 255, 'public' => true],

        // ----------------------------------------------------------------- social
        ['key' => 'social.instagram', 'type' => 'url', 'default' => '', 'public' => true],
        ['key' => 'social.tiktok', 'type' => 'url', 'default' => '', 'public' => true],
        ['key' => 'social.facebook', 'type' => 'url', 'default' => '', 'public' => true],

        // -------------------------------------------------------------------- tax
        ['key' => 'tax.rate', 'type' => 'float', 'default' => 22, 'required' => true, 'min' => 0, 'max' => 100, 'unit' => '%', 'public' => true, 'risk' => 'high'],
        ['key' => 'tax.prices_include_tax', 'type' => 'bool', 'default' => true, 'public' => true, 'risk' => 'high'],

        // --------------------------------------------------------------- shipping
        ['key' => 'shipping.dispatch_days_min', 'type' => 'int', 'default' => (int) $shipping['delivery_estimate']['dispatch_min'], 'required' => true, 'min' => 0, 'max' => 30, 'unit' => 'days', 'public' => true, 'config_bridge' => 'shipping.delivery_estimate.dispatch_min', 'seed' => false],
        ['key' => 'shipping.dispatch_days_max', 'type' => 'int', 'default' => (int) $shipping['delivery_estimate']['dispatch_max'], 'required' => true, 'min' => 0, 'max' => 30, 'unit' => 'days', 'public' => true, 'at_least' => 'shipping.dispatch_days_min', 'config_bridge' => 'shipping.delivery_estimate.dispatch_max', 'seed' => false],
        ['key' => 'shipping.delivery_days_min', 'type' => 'int', 'default' => (int) $shipping['delivery_estimate']['delivery_min'], 'required' => true, 'min' => 0, 'max' => 60, 'unit' => 'days', 'public' => true, 'config_bridge' => 'shipping.delivery_estimate.delivery_min', 'seed' => false],
        ['key' => 'shipping.delivery_days_max', 'type' => 'int', 'default' => (int) $shipping['delivery_estimate']['delivery_max'], 'required' => true, 'min' => 0, 'max' => 60, 'unit' => 'days', 'public' => true, 'at_least' => 'shipping.delivery_days_min', 'config_bridge' => 'shipping.delivery_estimate.delivery_max', 'seed' => false],
        ['key' => 'shipping.destinations', 'type' => 'countries', 'default' => ['IT', 'DE', 'GB', 'FR', 'ES'], 'required' => true, 'min' => 1, 'max' => 60, 'public' => true, 'risk' => 'high'],
        ['key' => 'shipping.default_country', 'type' => 'enum', 'default' => 'IT', 'required' => true, 'member_of' => 'shipping.destinations', 'public' => true],
        [
            'key' => 'shipping.carriers',
            'type' => 'json',
            'default' => $carriers,
            'max' => 30,
            'schema' => [
                'code' => ['required', 'string', 'regex:/^[a-z0-9_-]{1,32}$/', 'distinct'],
                'name' => ['required', 'string', 'max:80'],
                'tracking_url' => ['nullable', 'string', 'max:500', 'regex:/^https?:\/\/\S*\{number\}\S*$/i'],
            ],
            'config_bridge' => 'shipping.carriers',
            'bridge_format' => 'keyed_by_code',
            'seed' => false,
        ],
        // Items total after the coupon discount from which shipping is free (App\Support\Shipping). 0 = off.
        ['key' => 'shipping.free_threshold', 'type' => 'float', 'default' => 1000, 'min' => 0, 'max' => 100000, 'unit' => 'EUR', 'public' => true],
        // Optional label creation (App\Shipping\Labels). OFF by default: the admin then ships by typing carrier + tracking.
        ['key' => 'shipping.labels_enabled', 'type' => 'bool', 'default' => (bool) $shipping['labels']['enabled'], 'risk' => 'high', 'config_bridge' => 'shipping.labels.enabled', 'seed' => false],
        // Name of the registered label adapter to use (validated at runtime against the registered drivers).
        ['key' => 'shipping.label_driver', 'type' => 'string', 'default' => (string) $shipping['labels']['driver'], 'max' => 50, 'depends_on' => 'shipping.labels_enabled', 'config_bridge' => 'shipping.labels.driver', 'seed' => false],
        // Default parcel weight (kg) prefilled on the ship form when labels are on.
        ['key' => 'shipping.default_parcel_weight_kg', 'type' => 'float', 'default' => (float) $shipping['labels']['default_parcel_weight_kg'], 'min' => 0.01, 'max' => 1000, 'unit' => 'kg', 'depends_on' => 'shipping.labels_enabled', 'config_bridge' => 'shipping.labels.default_parcel_weight_kg', 'seed' => false],
        // Sender address printed on the label (falls back to the store identity where empty).
        ['key' => 'shipping.sender_name', 'type' => 'string', 'default' => (string) $shipping['labels']['sender']['name'], 'max' => 120, 'depends_on' => 'shipping.labels_enabled', 'config_bridge' => 'shipping.labels.sender.name', 'seed' => false],
        ['key' => 'shipping.sender_street', 'type' => 'string', 'default' => (string) $shipping['labels']['sender']['street'], 'max' => 255, 'depends_on' => 'shipping.labels_enabled', 'config_bridge' => 'shipping.labels.sender.street', 'seed' => false],
        ['key' => 'shipping.sender_city', 'type' => 'string', 'default' => (string) $shipping['labels']['sender']['city'], 'max' => 120, 'depends_on' => 'shipping.labels_enabled', 'config_bridge' => 'shipping.labels.sender.city', 'seed' => false],
        ['key' => 'shipping.sender_postal_code', 'type' => 'string', 'default' => (string) $shipping['labels']['sender']['postal_code'], 'max' => 20, 'depends_on' => 'shipping.labels_enabled', 'config_bridge' => 'shipping.labels.sender.postal_code', 'seed' => false],
        ['key' => 'shipping.sender_province', 'type' => 'string', 'default' => (string) $shipping['labels']['sender']['province'], 'max' => 60, 'depends_on' => 'shipping.labels_enabled', 'config_bridge' => 'shipping.labels.sender.province', 'seed' => false],
        ['key' => 'shipping.sender_country', 'type' => 'string', 'default' => (string) $shipping['labels']['sender']['country'], 'max' => 2, 'depends_on' => 'shipping.labels_enabled', 'config_bridge' => 'shipping.labels.sender.country', 'seed' => false],
        ['key' => 'shipping.sender_phone', 'type' => 'string', 'default' => (string) $shipping['labels']['sender']['phone'], 'max' => 50, 'depends_on' => 'shipping.labels_enabled', 'config_bridge' => 'shipping.labels.sender.phone', 'seed' => false],
        ['key' => 'shipping.sender_email', 'type' => 'string', 'default' => (string) $shipping['labels']['sender']['email'], 'max' => 255, 'depends_on' => 'shipping.labels_enabled', 'config_bridge' => 'shipping.labels.sender.email', 'seed' => false],

        // ------------------------------------------------------------------ legal
        // EU statutory minimum for distance sales (Directive 2011/83/EU), set in config/shipping.php. Read-only: shown in
        // the admin, quoted by the policy pages (React: useSetting) and the order emails.
        ['key' => 'legal.withdrawal_days', 'type' => 'int', 'default' => (int) $shipping['withdrawal_days'], 'required' => true, 'min' => 14, 'max' => 365, 'unit' => 'days', 'access' => 'readonly', 'public' => true],

        // ------------------------------------------------------------- compliance
        // GPSR (Regulation (EU) 2023/988) block of the product page. Empty = the store's own identity
        // (store.legal_name / store.address / contact.support_email), see ProductsController::compliance().
        ['key' => 'compliance.manufacturer_name', 'type' => 'string', 'default' => '', 'max' => 160, 'public' => true],
        ['key' => 'compliance.manufacturer_address', 'type' => 'text', 'default' => '', 'max' => 500, 'public' => true],
        ['key' => 'compliance.manufacturer_email', 'type' => 'email', 'default' => '', 'public' => true],
        ['key' => 'compliance.eu_responsible_person', 'type' => 'text', 'default' => '', 'max' => 500, 'public' => true],

        // ----------------------------------------------------------------- orders
        ['key' => 'orders.min_amount', 'type' => 'float', 'default' => 0, 'required' => true, 'min' => 0, 'max' => 100000, 'unit' => 'EUR', 'public' => true],
        ['key' => 'orders.max_qty_per_line', 'type' => 'int', 'default' => 0, 'required' => true, 'min' => 0, 'max' => 1000, 'public' => true],
        ['key' => 'orders.guest_checkout_enabled', 'type' => 'bool', 'default' => true, 'public' => true, 'risk' => 'high'],
        ['key' => 'orders.public_link_days', 'type' => 'int', 'default' => 30, 'required' => true, 'min' => 1, 'max' => 365, 'unit' => 'days', 'public' => true],
        ['key' => 'orders.order_notes_max', 'type' => 'int', 'default' => 500, 'required' => true, 'min' => 50, 'max' => 500, 'unit' => 'chars', 'public' => true],
        // Lifetime of a Stripe checkout session (Stripe accepts 30 minutes to 24 hours). The abandoned-checkout reminder
        // is only queued once the session has expired, so a customer never holds two live payment pages for one cart.
        ['key' => 'orders.checkout_expiry_minutes', 'type' => 'int', 'default' => 60, 'required' => true, 'min' => 30, 'max' => 1440, 'unit' => 'minutes'],

        // -------------------------------------------------------------- inventory
        ['key' => 'inventory.low_stock_threshold', 'type' => 'int', 'default' => 10, 'required' => true, 'min' => 0, 'max' => 1000, 'public' => true],

        // ---------------------------------------------------------------- catalog
        ['key' => 'catalog.per_page', 'type' => 'int', 'default' => 12, 'required' => true, 'min' => 6, 'max' => 60, 'public' => true],

        // ---------------------------------------------------------------- reviews
        ['key' => 'reviews.auto_approve', 'type' => 'bool', 'default' => true],
        // Consented review-request email after delivery (gated on marketing consent). Off by default.
        ['key' => 'reviews.request_enabled', 'type' => 'bool', 'default' => false],
        ['key' => 'reviews.request_delay_days', 'type' => 'int', 'default' => 10, 'required' => true, 'min' => 1, 'max' => 60, 'unit' => 'days', 'depends_on' => 'reviews.request_enabled'],

        // ------------------------------------------------------------------ email
        ['key' => 'email.enabled', 'type' => 'bool', 'default' => true, 'risk' => 'high'],
        ['key' => 'email.order_confirmation', 'type' => 'bool', 'default' => true, 'depends_on' => 'email.enabled'],
        ['key' => 'email.order_shipped', 'type' => 'bool', 'default' => true, 'depends_on' => 'email.enabled'],
        ['key' => 'email.order_delivered', 'type' => 'bool', 'default' => true, 'depends_on' => 'email.enabled'],
        ['key' => 'email.order_cancelled', 'type' => 'bool', 'default' => true, 'depends_on' => 'email.enabled'],
        ['key' => 'email.order_refunded', 'type' => 'bool', 'default' => true, 'depends_on' => 'email.enabled'],
        // Back-in-stock emails for items in carts and wishlists (customers can also opt out themselves)
        ['key' => 'email.back_in_stock', 'type' => 'bool', 'default' => true, 'depends_on' => 'email.enabled'],

        // --------------------------------------------------------------- tracking
        ['key' => 'tracking.auto_deliver', 'type' => 'bool', 'default' => (bool) $shipping['tracking']['auto_deliver'], 'config_bridge' => 'shipping.tracking.auto_deliver', 'seed' => false],

        // -------------------------------------------------------------------- seo
        // Overlaid on seotools.* at boot (the package builds its singletons lazily, after our provider has booted).
        ['key' => 'seo.default_description', 'type' => 'text', 'default' => (string) config('seotools.meta.defaults.description', ''), 'max' => 320, 'public' => false, 'config_bridge' => ['seotools.meta.defaults.description', 'seotools.opengraph.defaults.description'], 'seed' => false],
        ['key' => 'seo.keywords', 'type' => 'list', 'default' => array_values((array) config('seotools.meta.defaults.keywords', [])), 'max' => 30, 'item_max' => 60, 'config_bridge' => 'seotools.meta.defaults.keywords', 'seed' => false],

        // ------------------------------------------------------------- newsletter
        // Off hides every sign-up form (footer, registration, account, checkout). Empty sender name = the store name.
        ['key' => 'newsletter.enabled', 'type' => 'bool', 'default' => false, 'public' => true],
        ['key' => 'newsletter.sender_name', 'type' => 'string', 'default' => '', 'max' => 120],
        ['key' => 'newsletter.send_rate_per_minute', 'type' => 'int', 'default' => 60, 'required' => true, 'min' => 1, 'max' => 600, 'unit' => '/min'],
        // One reminder after an abandoned checkout, only to customers who ticked the marketing consent at checkout
        ['key' => 'newsletter.abandoned_checkout_enabled', 'type' => 'bool', 'default' => false],
        ['key' => 'newsletter.abandoned_checkout_delay_minutes', 'type' => 'int', 'default' => 60, 'required' => true, 'min' => 0, 'max' => 2880, 'unit' => 'minutes', 'depends_on' => 'newsletter.abandoned_checkout_enabled'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy keys (old settings table rows and Setting::get() callers) => new key
    |--------------------------------------------------------------------------
    */
    'legacy_aliases' => [
        'tax_rate' => 'tax.rate',
        'default_tax_rate' => 'tax.rate',
        'prices_include_tax' => 'tax.prices_include_tax',
        'site_name' => 'store.name',
        'contact_email' => 'contact.support_email',
        'contact_phone' => 'contact.phone',
        'company_address' => 'store.address',
        'company_iban' => 'store.company_iban',
        'company_account_holder' => 'store.account_holder',
        'admin_notification_email' => 'contact.admin_notification_email',
        'email_notifications_enabled' => 'email.enabled',
        'order_confirmation_enabled' => 'email.order_confirmation',
        'order_shipped_enabled' => 'email.order_shipped',
        'order_delivered_enabled' => 'email.order_delivered',
        'order_cancelled_enabled' => 'email.order_cancelled',
        'order_refunded_enabled' => 'email.order_refunded',
        'minimum_order_amount' => 'orders.min_amount',
    ],
];
