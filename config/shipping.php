<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Delivery estimate (single source of truth for customer emails)
    |--------------------------------------------------------------------------
    |
    | Business days. Mirrors the shipping page: orders are dispatched within
    | 1-2 business days and standard delivery takes 3-5 business days.
    |
    */

    'delivery_estimate' => [
        'dispatch_min' => (int) env('SHIPPING_DISPATCH_DAYS_MIN', 1),
        'dispatch_max' => (int) env('SHIPPING_DISPATCH_DAYS_MAX', 2),
        'delivery_min' => (int) env('SHIPPING_DELIVERY_DAYS_MIN', 3),
        'delivery_max' => (int) env('SHIPPING_DELIVERY_DAYS_MAX', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Right of withdrawal
    |--------------------------------------------------------------------------
    |
    | Days stated in the order emails. 14 is the EU statutory minimum for
    | distance sales (Directive 2011/83/EU).
    |
    */

    'withdrawal_days' => 14,

    /*
    |--------------------------------------------------------------------------
    | Carriers
    |--------------------------------------------------------------------------
    |
    | Carrier code (stored in orders.tracking_carrier) => display name and the
    | public tracking page, where {number} is replaced by the tracking number.
    | Order::trackingUrl() returns null for carriers that are not listed here,
    | and the emails then show the tracking number without a link.
    |
    | Example (use the carrier's real tracking page):
    |
    |   'example' => [
    |       'name' => 'Example Courier',
    |       'tracking_url' => 'https://tracking.example.com/track?number={number}',
    |   ],
    |
    */

    'carriers' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Carrier tracking module (app/Shipping/Tracking, docs/shipping-tracking-module.md)
    |--------------------------------------------------------------------------
    |
    | driver  manual (default: tracking links from the templates above, no API),
    |         null (tracking disabled) or the name of a registered adapter (spediamo).
    |         fake exists for tests/demos and is refused in production.
    |
    | auto_deliver  When a carrier reports Delivered, move the shipped order to `delivered`
    |         (which sends the delivered email). When false, events and the tracking status are
    |         still stored and shown, but the order status is left to the admin.
    |
    | poll    Used only by drivers that contact a carrier. Minutes between polls of
    |         a shipped order; the slower interval applies after slow_after_days;
    |         polling stops max_age_days after the first poll.
    |
    | drivers Per-driver settings, read by the adapter (never put real values here,
    |         use env). Every webhook driver needs a webhook_secret: in production
    |         the webhook route answers 403 while it is empty.
    |
    |   'example' => [
    |       'base_url' => env('SHIPPING_TRACKING_EXAMPLE_URL'),
    |       'api_key' => env('SHIPPING_TRACKING_EXAMPLE_KEY'),
    |       'webhook_secret' => env('SHIPPING_TRACKING_EXAMPLE_WEBHOOK_SECRET'),
    |       'timeout' => 10,
    |       'retries' => 1,
    |       // our neutral carrier code => the provider's courier string (empty = accept every carrier)
    |       'carrier_map' => ['sda' => 'SDA'],
    |   ],
    |
    | Carrier codes in `carriers` above are provider-neutral and stay the same whatever driver is
    | active; each driver translates them with its own carrier_map (see Support\CarrierCodeMap).
    |
    */

    'tracking' => [
        'driver' => env('SHIPPING_TRACKING_DRIVER', 'manual'),

        'auto_deliver' => (bool) env('SHIPPING_TRACKING_AUTO_DELIVER', true),

        'poll' => [
            'interval_minutes' => (int) env('SHIPPING_TRACKING_POLL_MINUTES', 120),
            'slow_after_days' => (int) env('SHIPPING_TRACKING_SLOW_AFTER_DAYS', 7),
            'slow_interval_minutes' => (int) env('SHIPPING_TRACKING_SLOW_POLL_MINUTES', 720),
            'max_age_days' => (int) env('SHIPPING_TRACKING_MAX_AGE_DAYS', 45),
        ],

        // Larger carrier payloads are not stored with the event (only a marker).
        'max_raw_bytes' => 8192,

        'drivers' => [
            'fake' => [
                'webhook_secret' => env('SHIPPING_TRACKING_FAKE_WEBHOOK_SECRET'),
            ],

            // Polling adapter for a shipping platform's API (tracking only). Needs a bearer token.
            'spediamo' => [
                'base_url' => env('SHIPPING_TRACKING_SPEDIAMO_URL', 'https://api.spediamo.it'),
                'token' => env('SHIPPING_TRACKING_SPEDIAMO_TOKEN'),
                'timeout' => (int) env('SHIPPING_TRACKING_SPEDIAMO_TIMEOUT', 10),
                'retries' => 1,
                // Timezone of the event dates the API returns (converted to UTC when stored).
                'timezone' => 'Europe/Rome',
                // Our neutral carrier code => the API's `courier` string. Empty = every carrier is accepted.
                // Example: ['sda' => 'SDA']. Fill it once the contracted couriers are known.
                'carrier_map' => [],
                // Overrides for the built-in status map: API status => TrackingStatus value
                // (info_received, in_transit, out_for_delivery, delivered, exception, returned, unknown).
                'status_map' => [],
            ],
        ],
    ],

];
