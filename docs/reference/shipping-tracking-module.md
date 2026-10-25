# Shipping and Tracking Module

A modular, swappable shipping architecture under `app/Shipping/` comprising three integrated subsystems:
1. **Carrier Tracking (`app/Shipping/Tracking`)**: Automated tracking synchronization (polling + webhooks) and customer-facing shipment timeline.
2. **Label Creation (`app/Shipping/Labels`)**: Modular shipping label generation and PDF storage with carrier API dispatch.
3. **Rate Quoting (`app/Shipping/Rates`)**: Live rate comparison, balance inspection, standalone simulator, and order margin delta calculation.

External integrations live as self-contained plugins under `app/Shipping/Providers/<Name>/` (`Spediamo`, `Dvaexpress`), each exposing a manifest implementing `App\Shipping\Contracts\ShippingProviderManifest`.

## Core versus Provider Plugins

* **Shared Core Infrastructure (`App\Shipping\*`, provider neutral, never mentions a carrier):**
  * `App\Shipping\Contracts\ShippingProviderManifest`: Capability detection (`hasTracking()`, `hasLabels()`, `hasRates()`) and driver factories (`createTrackingDriver()`, `createLabelDriver()`, `createRateDriver()`).
  * `App\Shipping\Contracts\Authenticator` (+ optional `RefreshesCredentials`): Authenticator contracts used across HTTP clients.
  * `App\Shipping\Auth\*`: `BearerTokenAuthenticator`, `ApiKeyHeaderAuthenticator`, `CachedClientCredentialsAuthenticator`.
  * `App\Shipping\Support\*`: `CarrierCodeMap` (maps internal provider-neutral codes like `sda`, `brt` to provider-specific carrier codes), `PostalCodeSanitizer`.
  * `App\Shipping\Exceptions\*`: `ShippingCredentialsException` and subsystem base exceptions.
  * `TrackingManager`, `LabelManager`, `RateManager`: Subsystem managers extending `Illuminate\Support\Manager`.
* **Self-Contained Provider Modules (`app/Shipping/Providers/<Name>/`):**
  * One directory per provider (e.g. `app/Shipping/Providers/Spediamo/`, `app/Shipping/Providers/Dvaexpress/`).
  * Holds provider-specific client, manifest, tracking adapter, label adapter, and rate adapter.
  * **Unified Registration via `config('shipping.manifests')`**:
    Registered by declaring the manifest class in `config('shipping.manifests')` (e.g. `SpediamoManifest::class`, `DvaexpressManifest::class`).
    `TrackingServiceProvider`, `LabelServiceProvider`, and `RateServiceProvider` inspect the manifests and register driver creators on the respective managers.
  * **Shared Credentials via `config('shipping.providers.<name>')`**:
    Shared provider settings (e.g. `base_url`, `api_key`/`token`, `carrier_map`) live in `config('shipping.providers.<name>')` without duplicate env variables across subsystems. Each manager's `driverConfig(name)` automatically merges `shipping.providers.<name>` with subsystem driver overrides (`shipping.<subsystem>.drivers.<name>`).
* The decision "mark the order delivered" lives in the core and depends only on `TrackingStatus::Delivered` plus
  `shipping.tracking.auto_deliver`; *when* an API's answer means Delivered is the adapter's business (the Spediamo adapter also
  accepts a non-empty `deliveryDate`).

## Architecture

```
 Admin ships order ──► orders.status = shipped, tracking_number, tracking_carrier
                                   │
        ┌──────────────────────────┴───────────────────────────┐
        │ POLL (every 15 min, only if the driver can poll)      │ PUSH (webhook, only if the driver supports it)
        │ routes/console.php → tracking:sync                    │ POST /webhooks/tracking/{driver}
        │   └─ one SyncOrderTracking job per due order          │   └─ TrackingWebhookController
        │        (queue, retries, backoff, no overlap)          │        verify signature → parse → find orders
        └──────────────┬────────────────────────────────────────┴──────────────┐
                       ▼                                                       ▼
   TrackingManager ──► TrackingProvider (adapter: track() / verifySignature() + parse())
                       │   returns TrackingResult / TrackingUpdate[]  (normalised TrackingStatus + TrackingEvent[])
                       ▼
   ShipmentTrackingService::apply(order, provider, result)   — transactional, idempotent
     • shipment_tracking_events   (unique order+provider+event hash)
     • orders.tracking_status / tracking_last_synced_at / tracking_next_sync_at
     • Delivered + order is `shipped` ──► order.status = delivered ──► OrderObserver ──► delivered email (once, order_emails)
```

| Piece | Where |
|---|---|
| Contracts | `Contracts\TrackingProvider` (required), `SupportsWebhooks`, `SupportsShipmentCreation` (reserved, empty), `PassiveProvider` (marker: never contacts a carrier) |
| DTOs / enum | `DTO\TrackingRequest`, `TrackingResult`, `TrackingEvent`, `TrackingUpdate`, `Enums\TrackingStatus` |
| Exceptions | `TrackingException` ← `TrackingProviderUnavailable` (retry), `TrackingRejected` (don't retry), `UnknownTrackingDriver` |
| Drivers | `manual` (default), `null` (disabled), `fake` (tests/demos, refused in production), `spediamo` (`Drivers\Spediamo\`); `Drivers\AbstractHttpProvider` helper base class for HTTP adapters |
| Authentication | `Contracts\Authenticator` (+ optional `RefreshesCredentials`), implemented by `Auth\BearerTokenAuthenticator`, `ApiKeyHeaderAuthenticator`, `CachedClientCredentialsAuthenticator` |
| Reusable helpers | `Drivers\Concerns\CachesConditionalRequests` (ETag), `Drivers\Concerns\VerifiesHmacSignature`, `Support\CarrierCodeMap` |
| Manager | `TrackingManager extends Illuminate\Support\Manager`, singleton, `TrackingProvider` resolves to the active driver |
| Service / jobs | `ShipmentTrackingService`, `Jobs\SyncOrderTracking`, command `tracking:sync`, `Http\TrackingWebhookController` |
| Data | `orders.tracking_carrier / tracking_status / tracking_last_synced_at / tracking_next_sync_at / tracking_started_at`, table `shipment_tracking_events` |

## Status vocabulary and mapping

Every adapter maps its carrier's codes into `TrackingStatus` (`Unknown, InfoReceived, InTransit, OutForDelivery, Delivered,
Exception, Returned`). Nothing outside the adapter sees carrier codes. In an adapter extending `AbstractHttpProvider` this is the
`statusMap()` array (carrier code, upper-cased → enum case; unlisted codes become `Unknown`).

Rules enforced centrally by `ShipmentTrackingService` (adapters cannot get them wrong):

* **Idempotent**: events are deduplicated on `(order_id, provider, event_hash)`; the hash is the carrier's event id when there is
  one, otherwise status + UTC time + description + location. Applying the same answer twice (poll + webhook, carrier retries,
  overlapping windows) changes nothing.
* **Only `shipped` orders are touched.** Cancelled, delivered (e.g. set by the admin), pending/processing orders are never modified;
  admin changes always win.
* **Never backwards**: the tracking status only moves forward by rank (`InfoReceived < InTransit < OutForDelivery < Delivered`).
  `Exception` and `Returned` may interrupt at any point; `Exception` can be resolved; `Delivered` and `Returned` are terminal.
  `Unknown` never overwrites anything. Old scans that arrive late are still stored in the history.
* **Delivered** moves the order to `delivered` through an ordinary model save, so the `OrderObserver` queues the delivered email
  exactly once (`order_emails` idempotency), same as an admin action. Customers who were never notified (unpaid) get no mail.
  This is controlled by `shipping.tracking.auto_deliver` (env `SHIPPING_TRACKING_AUTO_DELIVER`, default **true**). With it off,
  events and `tracking_status` are still stored and shown (polling stops, the status is terminal), but the order stays `shipped`
  and no email is sent: the admin marks it delivered, which sends the email as usual. A later wave bridges the flag to the admin
  setting `tracking.auto_deliver`.
* **Exception / Returned**: stored and logged as a warning; no order status change, no email.
* Times are stored in the application time zone (UTC); events dated more than 24 h in the future are dropped; the stored `raw`
  payload is capped (`shipping.tracking.max_raw_bytes`, a marker is stored instead) and descriptions/locations are length-limited.
* Editing the order's tracking number (other than case/spaces) or carrier starts a new cycle: status, schedule and "polling
  disabled" are reset. `estimatedDelivery` of a result is informational and not persisted yet.

## Flow in operation

1. The admin ships the order on the ship form (`App\Shipping\ShippingService`): carrier (`tracking_carrier`, a key of
   `config('shipping.carriers')`, required) and tracking number (required unless "ship without tracking number" is confirmed; it can
   then be added later on the order page). One shipped email goes out, with the tracking when there is a number. The shipped email's link comes from `Order::trackingUrl()` → `TrackingManager::trackingUrl()` (active driver,
   then the manual template; never a network call).
2. **Polling** (`tracking:sync`, scheduled every 15 minutes **only when the active driver is not passive**): selects `shipped` orders
   with a tracking number, a non-terminal tracking status, inside the polling window and with `tracking_next_sync_at` empty or due.
   Each one is claimed (next sync = now + 90 min, so a slow queue never collects duplicates) and a `SyncOrderTracking` job is queued.
   The job calls `track()`, applies the answer and plans the next poll.
   * Interval: `poll.interval_minutes` (default 120); after `poll.slow_after_days` (7) the `poll.slow_interval_minutes` (720) applies;
     polling stops `poll.max_age_days` (45) after the first poll. Terminal tracking statuses are never polled again.
   * `TrackingProviderUnavailable` (timeouts, 5xx, 429, bad credentials): the job is retried (5 tries, backoff 1 m / 5 m / 15 m / 1 h);
     when all attempts fail the next poll is postponed.
   * `TrackingRejected` (carrier refuses the shipment, unknown driver for the carrier code): polling for that order is switched off with a
     warning log; correcting the tracking number or carrier switches it back on.
3. **Webhook** `POST /webhooks/tracking/{driver}`: 404 unless the driver implements `SupportsWebhooks`; 413 above 1 MB; 403 if the
   driver's signature check fails, **or in production while `shipping.tracking.drivers.<name>.webhook_secret` is empty** (fail closed);
   422 for an unparsable payload, 503 when the adapter reports a temporary problem (carrier should retry), 500 when persisting fails
   (carrier should retry; applying is idempotent), otherwise 200 after the data is stored. Unknown tracking numbers are not an error.
   One parcel can serve several orders: all matching shipped orders are updated (case/space-insensitive, carrier must agree when both
   sides have one). Route is `throttle:120,1`, CSRF-exempt (`bootstrap/app.php`), payloads/headers/tracking numbers are never logged.
4. `tracking:sync --order=ID` queues one order immediately (ignores schedule/window); `--limit=N` caps a run (default 100).

## Configuration

| Env | Config | Meaning |
|---|---|---|
| `SHIPPING_TRACKING_DRIVER` | `shipping.tracking.driver` | `manual` (default), `null`, or a registered adapter name |
| `SHIPPING_TRACKING_AUTO_DELIVER` | `shipping.tracking.auto_deliver` | `true`: a carrier-reported delivery marks the order delivered (and emails); `false`: record only |
| `SHIPPING_TRACKING_POLL_MINUTES` | `shipping.tracking.poll.interval_minutes` | 120 |
| `SHIPPING_TRACKING_SLOW_AFTER_DAYS` / `_SLOW_POLL_MINUTES` | `poll.slow_after_days` / `poll.slow_interval_minutes` | 7 / 720 |
| `SHIPPING_TRACKING_MAX_AGE_DAYS` | `poll.max_age_days` | 45 |
| – | `shipping.tracking.max_raw_bytes` | 8192 |
| adapter specific | `shipping.tracking.drivers.<name>` | `base_url`, `api_key`/`token`, `timeout`, `retries`, `retry_sleep_ms`, `webhook_secret`, `carrier_map`, `etag_ttl_minutes` ... secrets always from env |
| `SHIPPING_TRACKING_SPEDIAMO_URL` / `_TOKEN` / `_TIMEOUT` | `shipping.tracking.drivers.spediamo.*` | see "Spediamo adapter" |
| – | `shipping.carriers` | provider-neutral `code => ['name' => ..., 'tracking_url' => 'https://.../{number}']`; feeds the admin carrier select and the manual driver |

Polling needs a **queue worker and the scheduler** (see `docs/reports/email-operations.md`). With `manual`/`null` nothing is scheduled.
`fake` exists for tests/demos only and refuses to start in production.

## Building blocks for HTTP adapters

**`AbstractHttpProvider`** gives an adapter the Http client (base URL, timeout, retry on transient errors), authentication, failure
translation and helpers. `send($call, $accept = [])` turns HTTP failures into the module's exceptions: 401/403/408/425/429/5xx become
`TrackingProviderUnavailable` (retried by the job), other 4xx `TrackingRejected` (polling for the order stops). **Statuses listed in
`$accept` (e.g. `[304, 404]`) are returned as a `Response` instead**, so an adapter can give them its own meaning ("unchanged since the
ETag", "not scanned yet"). Nothing in the base class logs requests, headers or bodies.

**Authenticators** (`Contracts\Authenticator`, passed as the second constructor argument of `AbstractHttpProvider`):

| Class | Scheme |
|---|---|
| `Auth\BearerTokenAuthenticator($token, $provider)` | `Authorization: Bearer <token>`; a blank token throws `TrackingProviderUnavailable` when a request is made (never at resolve time) |
| `Auth\ApiKeyHeaderAuthenticator($key, $header, $provider)` | custom header, e.g. `X-Api-Key` |
| `Auth\CachedClientCredentialsAuthenticator($provider, $tokenUrl, $clientId, $clientSecret, $scope)` | OAuth2 client credentials skeleton: token cached (encrypted, shared cache store) until a minute before `expires_in`; implements `RefreshesCredentials`, so `send()` discards the token on a 401 and repeats the call **once** |

Adapters that predate Authenticators may still override `authenticate(PendingRequest)`.

**`Concerns\CachesConditionalRequests`** (ETag / `If-None-Match`): `conditionalHeaders($code)`, `rememberResult($code, $response, $result)`,
`cachedResult($code)`. Entries (ETag + the last normalised `TrackingResult`, not the raw payload) live in the shared cache store, keyed
by driver name + tracking number, for `etag_ttl_minutes` (default 10080 = one week, at most 20160). A 304 without a cache entry should be
answered with `TrackingResult::unknown()` ("nothing new").

**Carrier codes.** `shipping.carriers` keeps neutral internal codes (`sda`, `brt`, ...; they are stored on orders and never change when the
provider changes). A driver translates them with `shipping.tracking.drivers.<name>.carrier_map` (`our code => provider's courier string`)
through `Support\CarrierCodeMap`: `toProvider()` / `fromProvider()`, case-insensitive, unknown => `null`. `TrackingProvider::supports()`:
with an **empty** map an aggregator driver may accept every carrier; with a map, only mapped codes (the job disables polling for an
order whose carrier is not supported).

## Spediamo adapter

`Drivers\Spediamo\SpediamoProvider` (+ `SpediamoResponseMapper`, `SpediamoStatusMap`). Tracking only, polling only (the API documents no
webhooks, so it is neither a `PassiveProvider` nor `SupportsWebhooks`): no shipment creation, labels, quotes or pickups. API reference:
`docs/spediamo/` and `docs/spediamo-doc.html` (owner-supplied, read only).

| Env | Config (`shipping.tracking.drivers.spediamo`) | Default |
|---|---|---|
| `SHIPPING_TRACKING_DRIVER=spediamo` | `shipping.tracking.driver` | `manual` (nothing changes until this is set) |
| `SHIPPING_TRACKING_SPEDIAMO_URL` | `base_url` | `https://api.spediamo.it` |
| `SHIPPING_TRACKING_SPEDIAMO_TOKEN` | `token` | empty |
| `SHIPPING_TRACKING_SPEDIAMO_TIMEOUT` | `timeout` (seconds) | 10 |
| – | `retries`, `retry_sleep_ms`, `etag_ttl_minutes` | 1, 200, 10080 |
| – | `timezone` of the event dates | `Europe/Rome` |
| – | `carrier_map` (our code => `courier`) | empty = every carrier accepted; e.g. `['sda' => 'SDA']` |
| – | `status_map` overrides (API status => `TrackingStatus` value) | empty |

Request: `GET {base_url}/v1/shipments/tracking?code={trackingNumber}` with `Authorization: Bearer {token}` and `If-None-Match` when an ETag is cached.

| Answer | Result |
|---|---|
| 200 | mapped (below); the ETag and the parsed result are cached |
| 304 | the cached last result (`unknown()` if the cache entry is gone) |
| 404 `Shipment not found` | `TrackingResult::unknown()`: the parcel is not scanned yet, polling continues on the normal schedule |
| 401 / 403 | `TrackingProviderUnavailable` (job retries/backs off) **and one `Log::critical` per hour** ("token is missing, expired or wrong"; deduped with `Cache::add`; the token is never logged) |
| missing token in config | `TrackingProviderUnavailable` without any HTTP call, same critical log |
| 429, 5xx, timeouts, unreadable body | `TrackingProviderUnavailable` (one transient retry inside the call, then the job's backoff) |
| other 4xx | `TrackingRejected`: polling for that order stops |

Mapping (`GET /v1/shipments/tracking`): top level `status`, `expectedDeliveryDate`, `deliveryDate` (`YYYY-MM-DD`), `courier`,
`trackingNumber`, `code`; each `tracking[]` item `{date, time{hour,minute,second}, description, longDescription, site, status}`:

* `occurredAt` = `date` + `time` read as wall-clock time in `timezone` (default `Europe/Rome`) and converted to UTC. A time skipped by the
  spring DST jump moves forward by the missing hour; an ambiguous autumn time takes its second (winter-time) occurrence. Items without a
  valid `date` are ignored.
* `description` = `description`, plus ` - longDescription` when it differs; `location` = `site`; `externalId` = null (the module dedupes on content);
  the original item is kept as the event's `raw` (capped), which preserves the API's own status string.
* `expectedDeliveryDate` => `estimatedDelivery` (informational). `courier` is available for `carrier_map` (`fromProvider()`); nothing is persisted from it.
* **Result status:** `Delivered` when the mapped top-level status, or the latest event's status, is Delivered **or** `deliveryDate` is
  non-empty; otherwise the most advanced mapped status (top level wins ties, then newer events). Events dated more than 24 h in the future
  are dropped by the service.

Default status map (case-insensitive, trimmed; extend via `status_map`):

| API status | TrackingStatus |
|---|---|
| `DRAFT`, `OPEN`, `BOOKED`, `READY` | `InfoReceived` |
| `CURRENT` | `InTransit` |
| `DELIVERED` | `Delivered` |
| `ON_HOLD`, `FAILED`, `UNDERPAID` | `Exception` |
| anything else | `Unknown` + one `Log::warning` per distinct value (7-day `Cache::add` dedupe) so a wrong guess is visible |

Switch on: set `SHIPPING_TRACKING_DRIVER=spediamo`, `SHIPPING_TRACKING_SPEDIAMO_TOKEN=...`, fill `carrier_map` if only some carriers are
contracted, make sure the queue worker and the scheduler run. Switch off: set the driver back to `manual`; orders and history stay.

### Open questions to the owner

1. **Token:** how is it issued and how long does it live? A static bearer token from env is assumed (rotate = change the env). If it
   expires, `CachedClientCredentialsAuthenticator` or a similar authenticator can be plugged in without touching anything else.
2. **Sandbox:** is there a test environment/base URL and test tracking codes?
3. **Real tracking status enum and event vocabulary:** the docs only show `DRAFT`/`OPEN` in tracking examples; the map above is derived from
   the shipment statuses (draft, booked, ready, current, delivered, on_hold, underpaid, failed). Is `deliveryDate` only set once delivered? Are
   there out-for-delivery or returned statuses?
4. **Is `code` equal to our `tracking_number`?** The adapter sends the order's tracking number as `code`; this only works for shipments
   bought through that platform.
5. **Which couriers are contracted?** Needed for `carrier_map` (our neutral code <=> the API's `courier` string, e.g. `SDA`).
6. Rate limits, ETag support in production, and whether `404` can also mean a wrong code (it is treated as "not scanned yet", so a typo
   polls quietly until the 45-day window ends).

## Add another API (or swap the current one)

1. **Adapter folder** `app/Shipping/Tracking/Drivers/<Name>/`: `<Name>Provider extends AbstractHttpProvider` (pass an `Authenticator`; use
   `CachesConditionalRequests` if the API has ETags; call `send($call, [304, 404])` if those statuses mean something), a response mapper and a
   status map. Implement `SupportsWebhooks` only if the carrier pushes; never implement `PassiveProvider` for a pollable API.
2. **Config** `shipping.tracking.drivers.<name>` in `config/shipping.php` (secrets from env) incl. `carrier_map`, and the env names in `.env.example`.
3. **One registration line** in `App\Providers\TrackingServiceProvider`: `$manager->extend('<name>', fn () => new <Name>Provider($manager->driverConfig('<name>')))`.
4. **Certify:** add a row to the `tracking providers` dataset in `tests/Feature/Shipping/TrackingProviderContractTest.php` (`Http::fake`, no network)
   plus adapter tests (mapping, 304/404/401/429/5xx, timeouts, no secrets in logs, end to end with `tracking:sync`).
5. **Switch:** set `SHIPPING_TRACKING_DRIVER=<name>` and its env; keep the old adapter folder or delete it (its config key, env lines and
   registration line go with it). Orders, neutral carrier codes, history and UI are untouched; events are stored per provider, so a switch in the middle of
   a shipment simply continues with the new driver's history.

### Guard rails (`tests/Unit/Shipping/ArchitectureTest.php`)

* No file outside `Drivers/Spediamo/`, `TrackingServiceProvider` and `config/shipping.php` may contain the adapter's name (app, routes, resources, database, lang).
* The core (`ShipmentTrackingService`, `Jobs`, `Http`, `Contracts`, `DTO`, `Enums`, `Exceptions`, `Support`, `Auth`, `Order`, `SyncShipmentTracking`) does not use `Drivers\*`;
  only `TrackingManager` (built-in manual/null/fake creators) and `TrackingServiceProvider` may.
* Every class in `Drivers` implements `TrackingProvider` (helpers in `Concerns` are traits; adapter helpers live in their own folder); `Contracts` holds only interfaces.
* `tests/Feature/Shipping/SwapRehearsalTest.php` runs a second, deliberately different adapter (`tests/Support/Shipping/RehearsalProvider.php`: API-key header, path
  parameter, other statuses, `d/m/Y` dates) through the same sync job, and switches drivers by config only, even in the middle of a shipment.

### Skeleton adapter (not part of the codebase, "ExampleCarrier" is fictitious)

```php
<?php

namespace App\Shipping\Tracking\Drivers;

use App\Shipping\Tracking\Auth\BearerTokenAuthenticator;
use App\Shipping\Tracking\Contracts\SupportsWebhooks;
use App\Shipping\Tracking\DTO\TrackingEvent;
use App\Shipping\Tracking\DTO\TrackingRequest;
use App\Shipping\Tracking\DTO\TrackingResult;
use App\Shipping\Tracking\DTO\TrackingUpdate;
use App\Shipping\Tracking\Drivers\Concerns\VerifiesHmacSignature;
use App\Shipping\Tracking\Enums\TrackingStatus;
use App\Shipping\Tracking\Exceptions\TrackingRejected;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;

class ExampleCarrierProvider extends AbstractHttpProvider implements SupportsWebhooks
{
    use VerifiesHmacSignature;

    public function __construct(array $config = [])
    {
        parent::__construct($config, new BearerTokenAuthenticator((string) ($config['api_key'] ?? ''), 'example'));
    }

    public function name(): string
    {
        return 'example';
    }

    public function supports(string $carrierCode): bool
    {
        return in_array($carrierCode, ['example'], true); // aggregators: usually return true
    }

    public function trackingUrl(string $trackingNumber, ?string $carrierCode): ?string
    {
        // Public page for the customer; no network. null => the manual template (config) is used.
        return 'https://tracking.example.com/'.rawurlencode(trim($trackingNumber));
    }

    protected function statusMap(): array
    {
        return [
            'CREATED' => TrackingStatus::InfoReceived,
            'IN_TRANSIT' => TrackingStatus::InTransit,
            'OUT_FOR_DELIVERY' => TrackingStatus::OutForDelivery,
            'DELIVERED' => TrackingStatus::Delivered,
            'FAILED_ATTEMPT' => TrackingStatus::Exception,
            'RETURNED' => TrackingStatus::Returned,
        ];
    }

    public function track(TrackingRequest $request): TrackingResult
    {
        // send() already turns HTTP failures into TrackingProviderUnavailable / TrackingRejected
        $data = $this->send(fn (PendingRequest $http) => $http->get('/v1/tracking', ['code' => $request->trackingNumber]))->json();

        return $this->toResult((array) $data);
    }

    public function verifySignature(Request $request): bool
    {
        return $this->hmacMatches($request, (string) ($this->config['webhook_secret'] ?? ''), 'X-Example-Signature');
    }

    public function parse(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload) || ! isset($payload['tracking_number'])) {
            throw new TrackingRejected('Malformed webhook payload');
        }

        return [new TrackingUpdate((string) $payload['tracking_number'], $payload['carrier'] ?? null, $this->toResult($payload))];
    }

    /** @param array<string, mixed> $data */
    private function toResult(array $data): TrackingResult
    {
        $events = [];

        foreach ((array) ($data['events'] ?? []) as $e) {
            $events[] = new TrackingEvent(
                status: $this->mapStatus($e['status'] ?? null),
                description: (string) ($e['description'] ?? ''),
                location: $e['location'] ?? null,
                occurredAt: $this->toUtc((string) $e['timestamp'], 'Europe/Rome'), // carrier-local time => UTC
                externalId: isset($e['id']) ? (string) $e['id'] : null,              // best dedupe key when available
                raw: null,                                                          // keep payloads (PII) out unless needed
            );
        }

        return new TrackingResult($this->mapStatus($data['status'] ?? null), $events);
    }
}
```

## Security notes

* Secrets (API keys, webhook secrets) live in env and `config/shipping.php` only; adapters must never log requests, headers or bodies
  (`AbstractHttpProvider` exceptions carry only `"<driver>: HTTP <status>"`).
* Webhooks fail closed: no secret in production ⇒ 403; signature compared in constant time (`VerifiesHmacSignature`; if the carrier
  sends a timestamp, also reject stale ones in the adapter). The route is rate limited and size limited.
* Webhook requests pass through the `web` middleware group (session cookie) because the route lives in `routes/web.php`; the carrier
  simply ignores the cookie.
* Event rows hold only what the carrier supplies as description/location; do not pass recipient names/phones into `raw`.

## Operations

* `php artisan tracking:sync` (manually, same rules as the scheduler) and `--order=ID`; failed jobs appear in `failed_jobs`
  (`SyncOrderTracking`); logs: "Tracking update applied", "Carrier rejected the tracking request, polling disabled for the order",
  "Shipment reported a problem by the carrier" (warning, check the parcel), "Tracking sync failed".
* An order whose polling was switched off has `tracking_next_sync_at` about 5 years ahead; fix its tracking number/carrier to re-enable.
* Timestamps in `tracking_*_at` are UTC.
* Spediamo: a **critical** log "cannot authenticate" (max one per hour) means the token is missing/expired/wrong: fix `SHIPPING_TRACKING_SPEDIAMO_TOKEN`
  (jobs keep retrying and back off, nothing is lost). A **warning** "returned a tracking status that is not mapped" names a new API status:
  add it to `status_map` (or ask the provider what it means).

---

# Label creation layer (`app/Shipping/Labels`)

A second, **optional** modular layer, parallel to the tracking module: it creates the shipment and the shipping-label PDF through a carrier
API. It is **off by default** (`shipping.labels_enabled`, admin setting, risk high). With it off **no label provider is ever resolved**: the
admin ships by typing a carrier and a tracking number (G2). With it on, the ship form shows parcel fields and a **"Create label and ship"**
button; the carrier returns the tracking number and the PDF.

`App\Shipping\ShippingService` is the only entry point controllers use. `ship(Order, ShipInput, User)` decides, from
`shipping.labels_enabled` and whether the admin asked for a label, between the manual path and the label path. Nothing in the controller,
the React pages or the emails is carrier specific.

## Pieces

| Piece | Where |
|---|---|
| Contracts | `Labels\Contracts\LabelProvider` (required: `name()`, `supports()`, `createShipment()`, `label()`), `CancelsShipments` (optional) |
| DTOs | `Labels\DTO\Address`, `Parcel`, `ShipmentRequest`, `ShipmentResult`, `LabelDocument` (provider neutral) |
| Exceptions | `Labels\Exceptions\LabelException` ← `LabelProviderUnavailable` (retry), `LabelRejected` (show the message, don't retry) |
| Manager | `Labels\LabelManager extends Illuminate\Support\Manager`, singleton; **no default/manual provider** — resolved only when labels are on |
| Drivers | `Labels\Drivers\FakeLabelProvider` (tests/demos, refused in production), `Labels\Drivers\Spediamo\SpediamoLabelProvider` |
| Builder | `Labels\ShipmentBuilder`: Order → `ShipmentRequest` (sender from `shipping.sender_*`, recipient from the order's shipping address, parcels from the order items' box sizes + default weight) |
| Service | `App\Shipping\ShippingService` (the only entry point: labels on/off logic, the transaction, the shipped email) |
| Data | `shipments` table (`order_id`, `carrier_code`, `driver`, `external_id`, `tracking_number`, `label_path` on the **private** disk, `status`, `request_payload`, `created_by`) |

## How it ships with a label

1. `ShippingService::shipWithLabel()` resolves the active `LabelProvider` (`shipping.label_driver`) and checks `supports($carrierCode)`.
2. `ShipmentBuilder` builds a `ShipmentRequest`. The provider **creates the shipment and fetches the PDF first** (network, outside any
   transaction), so a `LabelRejected` or `LabelProviderUnavailable` never leaves a half-shipped order.
3. The PDF is stored on the private disk (`storage/app/private/labels/{order}/{uuid}.pdf`).
4. In one transaction: a `shipments` row (driver, `external_id`, `tracking_number`, `label_path`) is written, the order's
   `tracking_carrier`/`tracking_number` are set from the carrier's result, and `transitionTo('shipped')` runs. One shipped email with the
   tracking goes out.
5. If the DB part fails after the label was created, the shipment row is recorded anyway (so the label is not lost) and the failure is
   surfaced with a reference id (`Log::critical`).

Failures on the ship form: `LabelRejected` → the carrier's message on the form, no state change; `LabelProviderUnavailable` → an error
flash, no state change, logged with a reference id.

On the order page the admin can **Download label** (streamed from the private disk, admin-authorised route, 403 for customers) and, when the
driver implements `CancelsShipments`, **Cancel shipment** (cancels at the carrier; does **not** change the order status — the admin then uses
D1's "undo ship").

## Settings (`shipping` group, labels/help in 5 locales)

| Setting | Type | Default |
|---|---|---|
| `shipping.labels_enabled` | bool, risk high | **false** |
| `shipping.label_driver` | string (validated at runtime against the registered drivers) | `spediamo` |
| `shipping.default_parcel_weight_kg` | float | 1.0 |
| `shipping.sender_name / _street / _city / _postal_code / _province / _country / _phone / _email` | string, depends on `shipping.labels_enabled` | from `store.*`/`contact.*` where empty |

## Spediamo label adapter

`Labels\Drivers\Spediamo\SpediamoLabelProvider` (+ `SpediamoLabelRequestMapper`). Built from `docs/spediamo/shipments.md` and
`waybills.md`. It **reuses the tracking adapter's bearer authenticator and the same credentials** (`config('shipping.labels.drivers.spediamo')`
= `SHIPPING_TRACKING_SPEDIAMO_TOKEN` / `_URL`): no duplicate env var.

* `createShipment()` → `POST /v1/shipments` with the mapped sender/recipient/parcels; reads `id`, `trackingNumber`, `courier`, `status`.
* `label()` → `GET /v1/waybills/{id}/pdf?download=true`; the PDF bytes.
* `cancelShipment()` → `DELETE /v1/shipments/{id}`.
* 400/404/422 → `LabelRejected` (the carrier's `message`/`error` when present); 401/403/408/429/5xx, timeouts and a missing token →
  `LabelProviderUnavailable`. Exception messages carry the HTTP status only; the token, headers and bodies are never logged.

**Open questions to the owner (Spediamo labels):** the export lists the `POST /v1/shipments` parameters as *query* parameters with no request
body and documents no explicit "buy/pay" step; this adapter sends them as the JSON body and treats the created shipment's `trackingNumber` as
final. The waybill endpoint's success shape in the export is an object (`filename`, `url`, `inputStream`, ...); this adapter expects the PDF
bytes with `download=true`. Confirm the request encoding, whether a shipment must be *bought* before it has a tracking number and a waybill,
and the waybill response format. All adapter tests use `Http::fake`; the real API is never called.

## DVA Express (EasyParcel) Provider

`app/Shipping/Providers/Dvaexpress/` provides an all-in-one integration with the DVA Express / EasyParcel Italian logistics API, exposing tracking, label creation, two-step quote booking, cancellation, and prepaid wallet balance inspection.

* **Manifest**: `App\Shipping\Providers\Dvaexpress\DvaexpressManifest`
* **Configuration**: `config('shipping.providers.dvaexpress')`
  * `base_url`: `env('DVAEXPRESS_BASE_URL', 'https://api.easyparcel.it')`
  * `api_key`: `env('DVAEXPRESS_API_KEY')`
  * `default_vettore`: `env('DVAEXPRESS_DEFAULT_VETTORE', 'SDAM')`
  * `default_cosa_spedire`: `env('DVAEXPRESS_DEFAULT_COSA_SPEDIRE', 'M')`
  * `carrier_map`: Maps neutral codes (`sda`, `brt`, `gls`, `poste`, `inpost`, `dhl`, `ups`, `fedex`) to EasyParcel carrier strings (`SDAM`, `BRT`, `GLS`, `PDB`, `INPOST`, `DHL`, `UPS`, `FEDEX`).
* **HTTP Client**: `DvaexpressClient`
  * Unified JSON POST transport via `{base_url}/{operation}/{api_key}`.
  * Formats error codes (e.g., auth codes 101, 102, 103, 107, 120; availability codes 104, 131) and flattens nested `errordetails` into clear error strings.
  * Throttles auth critical alerts to `Cache` (15 min) to prevent log flooding.
  * Translates HTTP/API failures into subsystem-specific exceptions (`TrackingException`, `LabelException`, `RateException`).

### Capabilities

1. **Tracking (`DvaexpressProvider`)**:
   * Polls `POST /tracking/{APIKEY}` with payload `['call' => 'tracking', 'details' => ['tracking' => $code]]`.
   * Maps Italian checkpoint descriptions and statuses (`Presa in carico`, `In transito`, `In consegna`, `Consegnata`, `Giacenza`, etc.) into canonical `TrackingStatus` enums.
2. **Rate Quoting (`DvaexpressRateProvider`)**:
   * Queries `POST /quotation/{APIKEY}` with sender, recipient, parcel weights/dimensions, COD, and insurance accessories.
   * Returns list of `RateQuote` DTOs with `quoteToken` set to the API's `codice_offerta` (valid for locked purchase), net/total prices, volumetric weight, and surcharge breakdown (remote area, COD, insurance).
3. **Wallet Balance (`DvaexpressRateProvider::balance()`)**:
   * Inspects prepaid master credit via `POST /balance/{APIKEY}` (`['call' => 'balance']`).
   * Parses `credito_master` / `balance_master` into `AccountBalance` (`EUR`).
4. **Label Creation (`DvaexpressLabelProvider`)**:
   * **Direct One-Step Creation**: `POST /order-fast/{APIKEY}` creates order and LDV waybill in one call. Parses `id_ordine`, `lettera_vettura` tracking number, and decodes inline base64 PDF label (`ldv_base64`).
   * **Two-Step Quote Purchase**: When a `quoteToken` (`codice_offerta`) is supplied, calls `POST /order/{APIKEY}` to book the exact locked quotation. Immediately follows with `POST /getwaybill/{APIKEY}` (`waybill_base64 = 'Y'`) to extract the tracking number and inline PDF waybill.
   * Caches the decoded PDF in-memory so subsequent `label()` call returns `LabelDocument` without an extra network download.
5. **Cancellation (`DvaexpressLabelProvider::cancelShipment()`)**:
   * Cancels uncollected orders via `POST /richiesta-storno/{APIKEY}` (`details.order_id = $id`).
   * Validates cancellation eligibility; throws `LabelRejected` if tracking has already commenced or order state forbids reversal.

---

# Shipping Rates Subsystem (`app/Shipping/Rates`)

A modular rate comparison and estimation engine that enables real-time quoting across multiple carriers, wallet balance inspection, standalone simulation for store staff, and margin delta calculation during order fulfillment.

## Architecture & Core Classes

```
  Admin Request ──► RateManager (Manager)
                         │
        ┌────────────────┼────────────────┐
        ▼                ▼                ▼
  FakeRateProvider  SpediamoRateProvider  DvaexpressRateProvider
   (local/testing)   (POST draft + DEL)   (POST /quotation)
        │                │                │
        └────────────────┼────────────────┘
                         ▼
        List of RateQuote DTOs sorted ascending by totalPrice
        + Per-driver error isolation + Wallet balances
```

| Piece | Location | Purpose |
|---|---|---|
| Manager | `Rates\RateManager` | Singleton extending `Illuminate\Support\Manager`. Dispatches quote requests across drivers, handles driver configuration resolution, and provides order-to-quote conversion helpers. |
| Contracts | `Rates\Contracts\RateProvider` | Required interface for rate drivers: `name()`, `supports(string $carrierCode)`, `quote(RateRequest $request): list<RateQuote>`. |
| | `Rates\Contracts\InspectsBalance` | Optional interface for drivers supporting prepaid wallet balances: `balance(): AccountBalance`. |
| DTOs | `Rates\DTO\RateRequest` | Provider-neutral shipment quote request with sender/recipient `RateAddress`, list of `RateParcel`, insurance, COD, coupon, and optional target carrier code. |
| | `Rates\DTO\RateQuote` | Canonical rate response: `provider`, `carrierCode`, `carrierName`, `serviceName`, `netPrice`, `totalPrice`, `listPrice`, `currency`, `taxableWeightKg`, `dimensionalWeightApplied`, `estimatedDelivery`, `quoteToken`, `surcharges`, `warnings`, `raw`. |
| | `Rates\DTO\AccountBalance` | Normalized prepaid wallet balance: `provider`, `balance`, `currency`, `raw`. |
| | `Rates\DTO\RateAddress`, `RateParcel` | Value objects for locations and physical parcel metrics. |
| Exceptions | `Rates\Exceptions\RateException` | Base exception. `RateProviderUnavailable` (connection timeouts, 5xx outages), `RateRejected` (validation, delivery area unserviceable). |

## Multi-Driver Fault Isolation (`RateManager::quoteAll`)

`RateManager::quoteAll(RateRequest $request, ?array $drivers = null): array` runs quoting concurrently across all selectable (or explicitly specified) drivers:
1. **Fault Isolation**: Each driver's `quote()` call is wrapped in an independent `try/catch`. If one carrier API times out, fails authentication, or rejects a destination, the error is recorded under `$errors[$driverName]`, and healthy drivers continue uninterrupted.
2. **Ascending Price Sorting**: All gathered `RateQuote` DTOs are sorted ascending by `totalPrice` (`usort($quotes, fn($a, $b) => $a->totalPrice <=> $b->totalPrice)`), ensuring the cheapest option is always presented first.
3. **Wallet Balance Collection**: Any driver implementing `InspectsBalance` is queried for current funds (`balances[$driverName]`), gracefully ignoring balance errors if rate quoting succeeded.

## Standalone Admin Shipping Quote Simulator

Accessible at `GET /admin/shipping/quotes` (`ShippingQuotesController::index`):
* Inertia view `Admin/Shipping/Quotes.tsx` renders a standalone quoting sandbox for store staff.
* Prefills the store's default sender address from settings (`shipping.sender_*` or `store.*`).
* Enables parcel weight/dimension customization, accessories (COD, insurance), coupon codes, and target carrier filtering.
* Dispatches `POST /admin/shipping/quotes/calculate`: validates input and returns `{ quotes, errors, balances }`.

## Order Ship Rate Comparison & Margin Delta

Embedded directly into the order fulfillment process (`/admin/orders/{order}/ship`):
* `POST /admin/orders/{order}/quotes` (`ShippingQuotesController::quoteOrder`):
  * Builds a `RateRequest` using the customer's verified shipping address from the order and items/parcels.
  * Gathers real-time quotes via `RateManager::quoteAll()`.
  * Computes the **margin delta** for each quote against what the customer paid:
    `margin_delta = round($order->shipping_amount - $quote->totalPrice, 2)`
  * Returns `{ quotes, errors, balances, customer_shipping_cost }`.
* **Visual Margin Indicators**:
  * Positive margin delta (green): carrier quote is cheaper than customer shipping charge (profit).
  * Negative margin delta (red): carrier quote exceeds customer shipping charge (loss).
* **1-Click Quote Selection**:
  * Clicking "Use quote" populates the ship form with the carrier code, selects the corresponding label driver, and attaches the `quote_token`.

## End-to-End Quote-to-Label Purchase Flow

When creating a label from a selected quote (`POST /admin/orders/{order}/ship` with `create_label: true` and `quote_token`):
1. `ShippingService::shipWithLabel` passes `quoteToken` via `ShipmentBuilder` into `ShipmentRequest`.
2. The active label driver (e.g. `DvaexpressLabelProvider`) detects `quoteToken` and skips ad-hoc quoting, calling `POST /order/{APIKEY}` with `codice_offerta = $quoteToken` to purchase the locked rate.
3. The provider fetches the waybill via `POST /getwaybill/{APIKEY}`, decodes the PDF label, stores it on the private disk, records the `Shipment` record with the provider driver name, and transitions the order to `shipped`.

---

## How to add a label carrier

1. **Adapter folder** `app/Shipping/Labels/Drivers/<Name>/`: `<Name>LabelProvider implements LabelProvider` (and `CancelsShipments` if the
   carrier can cancel). Map the neutral `ShipmentRequest`/`ShipmentResult` DTOs to the carrier's API; translate HTTP failures into
   `LabelRejected` / `LabelProviderUnavailable`; never log secrets, headers or bodies.
2. **Config** `shipping.labels.drivers.<name>` in `config/shipping.php` (secrets from env), and the env names in `.env.example`.
3. **One registration line** in `App\Providers\LabelServiceProvider`:
   `$manager->extend('<name>', fn () => new <Name>LabelProvider($manager->driverConfig('<name>')))`.
4. **Certify:** add a row to the `label providers` dataset in `tests/Feature/Shipping/LabelProviderContractTest.php` (`Http::fake`, no
   network) plus adapter tests (request/response mapping, 400 → rejected, 401/5xx → unavailable, missing token, no secrets in logs).
5. **Switch:** turn on `shipping.labels_enabled` and set `shipping.label_driver=<name>` in the admin settings (or the env defaults). Nothing
   in controllers, pages or emails changes.

Guard rail: the Spediamo scan in `tests/Unit/Shipping/ArchitectureTest.php` allows the carrier name only in `Drivers/Spediamo/` (tracking and
labels), `TrackingServiceProvider`, `LabelServiceProvider` and `config/shipping.php`.

## Customer-facing tracking

The customer sees their shipment on the account orders page (`Dashboard/Orders`), the account dashboard and the signed guest order page
(`Orders/GuestShow`), through one shared React component (`resources/js/components/orders/OrderTracking.tsx`). The payload comes from
`Order::customerTrackingArray()`, merged into the whitelisted customer order arrays (`Order::CUSTOMER_FIELDS` / `toCustomerArray()`, used by
`DashboardController::orders`, the `/dashboard` closure and `GuestOrderController`):

* `tracking_url` (`Order::trackingUrl()`, the active driver's public page, else the manual template), `carrier_name`
  (`Order::trackingCarrierName()`), `tracking_status`, `tracking_last_synced_at`.
* `tracking_events`: the `shipment_tracking_events` checkpoints the customer may see — **only** the provider-neutral `status` value
  (rendered via the `tracking_admin.status.*` labels), `description`, `location` and `occurred_at`. The stored `raw` payload, the carrier's
  own status string, the `provider` name, the `event_hash` and the `external_id` are never sent to the browser. The caller eager-loads the
  `trackingEvents` relation, so there is no N+1.

A cancelled order shows a dedicated "Cancelled" state (and "Refunded" when `payment_status` is refunded or partially refunded) instead of a
tracking link. Nothing here is carrier specific.
