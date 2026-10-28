---
paths:
  - app/Shipping/**/*.php
  - config/shipping.php
  - tests/Feature/Shipping/*.php
  - tests/Unit/Shipping/*.php
---
# Shipping

Carrier plugins (tracking, labels, rates), their config and the tracking sync. Does not cover checkout shipping cost or free threshold (`app/Support/Shipping.php`) or order status emails.

## Entry points
- `app/Shipping/Providers/<Name>/`: one self-contained folder per carrier (Spediamo, Dvaexpress), manifest is `<Name>Manifest.php`.
- `config/shipping.php`: `manifests` list, per-provider creds/carrier maps, and the `labels`, `tracking`, `rates` driver sections.
- `app/Shipping/Tracking/Drivers/`: built-in drivers only (Fake, Manual, Null, AbstractHttpProvider).
- `app/Shipping/ShippingService.php`: ship flow (manual vs label purchase); core, must stay driver-agnostic.
- `routes/console.php`: `tracking:sync` schedule.
- `docs/vendor/spediamo/`, `docs/vendor/dvaexpress/`: third-party API references (raw source in `docs/vendor/_source/`).

## Rules
- MUST keep core (ShippingService, Labels/Tracking/Rates contracts, DTOs, Order, commands) free of imports from `Drivers` or `Providers` namespaces. Enforced by `tests/Unit/Shipping/ArchitectureTest.php`.
- NEVER import Labels from Tracking or the reverse; share via `App\Shipping\{Auth,Support,Contracts}`. Enforced by ArchitectureTest.
- NEVER import one provider folder from another, or mention a provider name outside its folder and `config/shipping.php`. Enforced by ArchitectureTest.
- NEVER put real credentials in config; read them from env. Unenforced.
- MUST keep labels off unless `shipping.labels_enabled` is on; no label provider is resolved otherwise. Enforced by `tests/Feature/Shipping/LabelShippingTest.php`.
- NEVER use the `fake` tracking driver in production (manager throws). Enforced by `TrackingManagerTest.php`.
- Carrier codes in `shipping.carriers` are provider-neutral; translate per provider with `carrier_map` (`app/Shipping/Support/CarrierCodeMap.php`). Enforced by `tests/Unit/Shipping/CarrierCodeMapTest.php`.
- Webhook drivers MUST set `webhook_secret`; production answers 403 without it. Enforced by `TrackingWebhookTest.php`.

## How it works
1. A provider declares capabilities (tracking, labels, rates) in its manifest; each `create*Driver` returns null if unsupported.
2. `config('shipping.manifests')` registers manifests; several providers coexist, each order stores its `tracking_driver`.
3. Managers (`TrackingManager`, `LabelManager`, `RateManager`) resolve drivers by name; `TrackingServiceProvider` wires them.
4. Push: `POST /webhooks/tracking/{driver}`, only drivers implementing `SupportsWebhooks` answer (404 otherwise), the driver verifies the request.
5. Pull: `tracking:sync` runs every 15 min, only when `TrackingManager::canPollAny()` (default driver pollable, or a shipped order uses a pollable driver). Manual/null-only installs never run it.
6. Per-order poll cadence comes from `shipping.tracking.poll` (interval, slower after N days, stops after max age).
7. A Delivered event moves the order to `delivered` only if `tracking.auto_deliver` is on.

## Traps
- Spediamo labels reuse the tracking credentials (`SHIPPING_TRACKING_SPEDIAMO_TOKEN`/`_URL`); no separate label env var, on purpose.
- Spediamo has no pay operation: labels are created as drafts and the owner pays at `purchase_url`.
- Rate fixtures in `tests/Fixtures/spediamo/probes` come from the live API; the label adapter was built from docs. Unverified: confirm with one real test shipment before enabling labels in production.
- `config/shipping.php` loads after `config/settings.php`, which therefore `require`s it directly; do not rely on `config('shipping...')` inside the settings catalogue.
- The default `rates.default` is `fake`; real quotes need `SHIPPING_RATES_DRIVER`.

## Operate
- `php artisan tracking:sync`, `php artisan setup:test-tracking` (local test data), `php artisan shipping:test-rates` (inspect rate payloads without the UI).
- Env: `SHIPPING_TRACKING_DRIVER`, `SHIPPING_TRACKING_AUTO_DELIVER`, `SHIPPING_TRACKING_POLL_MINUTES`, `SHIPPING_RATES_DRIVER`, `SHIPPING_LABELS_ENABLED`, `SHIPPING_LABELS_DRIVER`, `SHIPPING_SENDER_*`, `SPEDIAMO_PURCHASE_URL`, `DVAEXPRESS_*`.
- Admin settings (DB, override env via ConfigBridge): `shipping.labels_enabled`, `shipping.label_driver`, `shipping.sender_*`, `tracking.auto_deliver`. See `.ai/rules/settings.md`.

## Change recipes
Add a carrier:
1. Add API references under `docs/vendor/<name>/`.
2. Create `app/Shipping/Providers/<Name>/<Name>Manifest.php` implementing `ShippingProviderManifest`, plus the provider classes (tracking, label, rate) it needs; follow Dvaexpress.
3. Reuse `app/Shipping/Auth/*` authenticators and `AbstractHttpProvider` for HTTP tracking.
4. Add the manifest to `manifests` and a `providers.<name>` block (creds from env, `carrier_map`) in `config/shipping.php`; add driver blocks under `labels.drivers`, `tracking.drivers`, `rates.drivers` as needed.
5. Add env keys to `.env.example`.
6. Write tests with `Http::fake` (see `DvaexpressProviderTest.php`, `TrackingProviderContractTest.php`, `LabelProviderContractTest.php`).
7. Run `php artisan test --compact tests/Unit/Shipping/ArchitectureTest.php tests/Feature/Shipping`.

## Tests
- `tests/Unit/Shipping/ArchitectureTest.php`: isolation rules.
- `tests/Feature/Shipping/*ProviderTest.php`, `*ContractTest.php`: per-carrier adapters and driver contracts.
- `TrackingWebhookTest.php`, `SyncTrackingTest.php`, `TrackingManagerTest.php`: push, pull, driver resolution.
- `LabelShippingTest.php`, `LabelPurchaseGuardTest.php`, `ShipGuardTest.php`: label flow and guards.
- `CoexistingProvidersTest.php`, `SwapRehearsalTest.php`: swapping or running several carriers.
