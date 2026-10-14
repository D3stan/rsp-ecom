# Carrier tracking module

A self-contained, swappable module (`app/Shipping/Tracking`) that gives orders automatic shipment tracking through a carrier
API. With the default `SHIPPING_TRACKING_DRIVER=manual` the application behaves exactly as before (tracking links built from URL
templates, status changes done by the admin). One adapter ships with the code, **`spediamo`** (polling, tracking only), and it
stays dormant until the owner sets the env (see "Spediamo adapter"). Adding or replacing a carrier API means one adapter folder,
one registration line and one env value; the core, the `Order` model, the UI and the database do not change.

## Core versus adapters

* **Core (provider neutral, never mentions a carrier):** `Contracts`, `DTO`, `Enums`, `Exceptions`, `Support\CarrierCodeMap`,
  `Auth\*` authenticators, `ShipmentTrackingService`, `Jobs`, `Http`, `TrackingManager`, `Drivers\AbstractHttpProvider`,
  `Drivers\Concerns\*`, and the consumers outside the module (`Order`, `SyncShipmentTracking`, admin orders UI).
* **Adapters (one folder each):** `Drivers\<Name>\` holding the provider class, its response mapper and its status map. Their
  settings live only under `shipping.tracking.drivers.<name>`; they are registered by one line in
  `App\Providers\TrackingServiceProvider`.
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

1. The admin ships the order and picks the carrier (`tracking_carrier`, a key of `config('shipping.carriers')`) and later adds the
   tracking number. The shipped email's link comes from `Order::trackingUrl()` → `TrackingManager::trackingUrl()` (active driver,
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
