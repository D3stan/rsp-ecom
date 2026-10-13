# Carrier tracking module

A self-contained, swappable module (`app/Shipping/Tracking`) that gives orders automatic shipment tracking through a carrier
API. Today **no carrier adapter exists**: with the default `SHIPPING_TRACKING_DRIVER=manual` the application behaves exactly as
before (tracking links built from URL templates, status changes done by the admin). Adding a carrier means writing one adapter
class and one config entry.

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
| Drivers | `manual` (default), `null` (disabled), `fake` (tests/demos, refused in production); `Drivers\AbstractHttpProvider` helper base class for HTTP adapters |
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
| `SHIPPING_TRACKING_POLL_MINUTES` | `shipping.tracking.poll.interval_minutes` | 120 |
| `SHIPPING_TRACKING_SLOW_AFTER_DAYS` / `_SLOW_POLL_MINUTES` | `poll.slow_after_days` / `poll.slow_interval_minutes` | 7 / 720 |
| `SHIPPING_TRACKING_MAX_AGE_DAYS` | `poll.max_age_days` | 45 |
| – | `shipping.tracking.max_raw_bytes` | 8192 |
| adapter specific | `shipping.tracking.drivers.<name>` | `base_url`, `api_key`, `timeout`, `retries`, `retry_sleep_ms`, `webhook_secret` ... always from env |
| – | `shipping.carriers` | `code => ['name' => ..., 'tracking_url' => 'https://.../{number}']`; feeds the admin carrier select and the manual driver |

Polling needs a **queue worker and the scheduler** (see `docs/reports/email-operations.md`). With `manual`/`null` nothing is scheduled.
`fake` exists for tests/demos only and refuses to start in production.

## Add a new carrier in 5 steps

1. **Write the adapter** (skeleton below), e.g. `app/Shipping/Tracking/Drivers/ExampleCarrierProvider.php`. Extend `AbstractHttpProvider`
   for an HTTP API; implement `SupportsWebhooks` only if the carrier pushes. Map every carrier status in `statusMap()`.
2. **Add its settings** to `config/shipping.php` under `tracking.drivers.example` (values from env) and document the env names in `.env.example`.
3. **Register it** from a service provider (the module needs no other change):
   ```php
   $this->callAfterResolving(TrackingManager::class, fn (TrackingManager $m) =>
       $m->extend('example', fn () => new ExampleCarrierProvider($m->driverConfig('example'))));
   ```
4. **Certify it**: add one row to the dataset in `tests/Feature/Shipping/TrackingProviderContractTest.php` (with `Http::fake()` for the
   API) and make the contract test pass; add adapter-specific tests for the status mapping and error handling.
5. **Switch on**: add carriers to `shipping.carriers` (codes the admin selects), set `SHIPPING_TRACKING_DRIVER=example` and the env
   values, make sure worker + scheduler run, and (for webhooks) point the carrier to `https://<domain>/webhooks/tracking/example`.

### Skeleton adapter (not part of the codebase, "ExampleCarrier" is fictitious)

```php
<?php

namespace App\Shipping\Tracking\Drivers;

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

    protected function authenticate(PendingRequest $request): PendingRequest
    {
        return $request->withToken((string) ($this->config['api_key'] ?? ''));
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

## Fit check against the sample API doc (`docs/spediamo-doc.html`, Swagger page of a shipping platform)

Used only to sanity-check the interfaces; nothing was implemented for it.

* Auth is a header `Authorization: Bearer <token>`; the token issuing endpoint is not in the page ⇒ `authenticate()` hook + `api_key` config fit; how/when the token expires is an open question.
* Tracking lookup exists: `GET /v1/shipments/tracking?code=<tracking code>` (with `If-None-Match`/ETag support) ⇒ maps to `track(TrackingRequest)`; an ETag cache would live inside the adapter (a 304 can be answered from its own cache).
* The answer holds an overall shipment status plus `tracking[]` events with `date`, `time{hour,minute,second}`, `description`, `longDescription`, `site`, `status` ⇒ `TrackingEvent` (`site` → location, date+time combined and converted to UTC by the adapter). Events have no id ⇒ `externalId` null, the content hash is used.
* `courier` (e.g. a courier code) is returned ⇒ the platform is an aggregator: `supports()` would return true for all codes and the `courier` could be mapped back to `shipping.carriers` codes.
* `expectedDeliveryDate` / `deliveryDate` ⇒ `TrackingResult::$estimatedDelivery` (not persisted yet).
* Status vocabulary is not fully documented for tracking events (examples show DRAFT/OPEN; the shipments search lists draft, booked, ready, current, delivered, on_hold, underpaid, failed) ⇒ the `statusMap()` needs the real list from the provider.
* No webhook/callback endpoint appears in the page ⇒ polling only; `SupportsWebhooks` would not be implemented.
* 404 `Shipment not found` is documented for the lookup: it may happen before the first scan, so such an adapter should treat `TrackingRejected` with `httpStatus === 404` as "unknown yet" (return `TrackingResult::unknown()`), not as a rejection.
* The API also covers creating/buying shipments and waybill PDFs ⇒ the reserved `SupportsShipmentCreation` capability fits; no rate limits are documented ⇒ rely on `poll.*` settings and ask the provider.
* Tracking probably only works for shipments bought through that platform; our `tracking_number` must be the platform's tracking code.
