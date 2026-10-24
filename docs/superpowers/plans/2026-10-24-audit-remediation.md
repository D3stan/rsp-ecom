# Audit Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the audit findings that survived verification (the exception handler, duplicate label purchases, the editable payment status, the tracking lockout, carrier voiding on undo, proxy trust, a token in the logs), and check each assumption the audit could not settle before relying on it.

**Architecture:** Small, local changes in the places that already own the behaviour: `bootstrap/app.php` (exception handler, proxy trust), `ShippingService` (purchase guard, payment completion), `OrdersController` + `OrderFinancialEdit` (payment-status policy, undo), `SyncShipmentTracking` (due-order query). Every fix starts with a test that fails for the reason the audit gave. Phase 0 holds checks that decide or confirm a design choice; they change no code.

**Tech Stack:** Laravel 13.38 / PHP 8.5, Pest 4, Inertia 2 + React/TypeScript, Redis or database cache (atomic locks), Dokploy (Traefik in front of a `serversideup/php:8.5-fpm-nginx` container).

---

## Ground rules (apply to every task)

- Work on a new branch cut from `sandbox`: `git switch -c fix/audit-remediation`. **No deploys.** The owner deploys everything in one go at the end (see memory `rsp-remediation-deploy`); the production stack is never touched without an explicit ask.
- Before using a framework API named below (`trustProxies`, `Cache::lock`, `Log::spy`), run the Boost `search-docs` tool once for it and confirm the signature for Laravel 13.38. Do not assume.
- Project rule: run `graphify query "<question>"` before the first grep or source read for a new question; after code changes run `graphify update .`.
- Run the narrowest tests: `php artisan test --compact <file>` or `--filter=`. Never the full suite; the owner runs it at the end.
- After every PHP change run `vendor/bin/pint --dirty --format agent`.
- Every commit message ends with the two trailer lines from the session's attribution notice (`Co-Authored-By: ...` and `Claude-Session: ...`). The steps below show only the subject line.
- `public/robots.txt` has an unrelated uncommitted change. Never `git add -A`; add the listed files only.

## What is in and out of scope

Verified findings from the audit review, by claim number in the audit report:

| In this plan | Claim | Verified severity |
|---|---|---|
| Task 1 | #3 exception handler turns guest redirects and 419s into 500 | Critical (pre-existing since 2025-08-07) |
| Task 2 | #6 label bought twice, orphan shipment | High |
| Tasks 4, 5, 6 | #4 stale form reverts payment, #5 financial lock bypass, #19 manual `refunded` inflates revenue, #25 no audit event | High / Medium |
| Task 3 | #7 unpaid draft locks tracking for 5 years | Medium (trigger unverified, see V3) |
| Task 7 | #18 undo never voids the label at the carrier | Medium |
| Task 8 | #2 `trustProxies('*')` | High/Medium, depends on Traefik (V2) |
| Task 9 | #8 token and email written to the log | Low/Medium |
| Task 10 (conditional) | #1 cascade foreign key on `orders.*_address_id` | Low, latent (V4) |

Claims the review found **false or harmless**, so no work: #9, #12, #13, #17 (impact), #27 (impact), #32, #33, #36, #44.

**Deferred to a separate Low-severity plan** (each is real but small and independent: one PR of its own): #10, #11, #14, #15, #16, #20, #21, #22, #23, #24, #28, #29, #30, #31, #34, #35, #37, #38, #39, #40, #42, #43, #46, #47. Writing them into this plan would bury the eight items above.

## Decisions taken as defaults (change them before starting if you disagree)

| # | Decision | Default used | Where |
|---|---|---|---|
| D1 | Who may edit `payment_status` | Orders with a Stripe payment: read-only once captured, and nobody can set `refunded`/`partially_refunded` by hand (RefundService owns those). Offline-paid orders stay fully editable. | Task 5 |
| D2 | Undo of a shipment when the carrier refuses to void the label | The undo is refused with the carrier's message; the order stays `shipped`. | Task 7 |
| D3 | Schema change for #1 | Only after V4, and only with the owner's go-ahead. | Task 10 |

---

## Phase 0: checks (no code, do these first)

Each check has a pass/fail meaning. Write the result in the branch's first commit message or in the PR description so the next reader does not have to repeat it.

### V1: does the cache store support atomic locks? (needed by Task 2)

- [ ] **Step 1: local**

Run: `php artisan config:show cache.default`
Expected: `redis` (seen on 2026-10-24). `redis`, `database`, `memcached`, `dynamodb`, `file` all support `Cache::lock`; `array` and `null` do not work across requests.

- [ ] **Step 2: sandbox and production**

Read only the `CACHE_STORE` key (never print the whole env) of the sandbox and production compose apps in Dokploy. Production is compose app `ecommerce`, id `7GAPjcU7XPg1mdOXGUK1A`; find the sandbox one with `mcp__dokploy-mcp__compose-search`. Use `mcp__dokploy-mcp__compose-one` and read the `env` field.
Pass: `redis` or `database`. If it is `array`/unset in a stack, stop and tell the owner: Task 2 would silently not lock there (the memory note already requires a shared store for the admin-login nonce, so this should pass).

### V2: how do Traefik and nginx treat forwarded headers? (decides how Task 8 is shaped)

- [ ] **Step 1: find the sandbox domain without touching production**

Run `mcp__dokploy-mcp__domain-byComposeId` for the sandbox compose app. Call the host `SANDBOX_HOST`.

- [ ] **Step 2: send one harmless probe with spoofed headers**

Run:

```bash
curl -s -o /dev/null -w '%{http_code}\n' \
  -H 'X-Forwarded-Host: evil.example' -H 'X-Forwarded-For: 1.2.3.4' \
  "https://SANDBOX_HOST/verification/verify/probe/probe@example.test"
```

This hits the invalid-signature branch of `PendingUserVerificationController::verify`, which logs `host` (what Laravel resolved) and `x_forwarded_host` (what arrived). It writes one warning line and nothing else.

- [ ] **Step 3: read that log line and the nginx access line**

Run `mcp__dokploy-mcp__compose-readLogs` for the sandbox app and find `Pending verification link rejected`. Also note the nginx access-log line for the same request: its client address is the peer PHP sees (`REMOTE_ADDR`), i.e. Traefik's address.

Interpretation:

| Log shows | Meaning | Consequence for Task 8 |
|---|---|---|
| `host` = `SANDBOX_HOST`, `x_forwarded_host` = `SANDBOX_HOST` | Traefik overwrites the header | Host poisoning is not reachable today; Task 8 is defence in depth |
| `host` = `evil.example` | Traefik passes it through | Host poisoning is live: Task 8 is urgent and must exclude `X-Forwarded-Host` |
| peer address in `10.*`, `172.16-31.*`, `192.168.*` | Private Docker range | The default list in Task 8 is right |
| peer address is anything else (public, or `127.0.0.1`) | Different topology | Change the `at:` list in Task 8 to that range before committing |

Traefik treats all `X-Forwarded-*` headers the same way, so the host result also tells you what happens to `X-Forwarded-For`.

- [ ] **Step 4: does the login throttle even use the IP?**

Run: `grep -n "ip()" app/Http/Requests/Auth/LoginRequest.php`
Expected: a line in `throttleKey()`. If there is none, the login-throttle part of the #2 impact is wrong and the Task 8 commit message must not claim it.

### V3: what does Spediamo answer when a draft id is tracked? (confirms the trigger of Task 3)

The audit assumed a 400. The repo only documents 200 and 404, and no real draft-tracking response is recorded.

- [ ] **Step 1: needs a real draft** (owner action or sandbox Spediamo token). With a real draft id `shp_xxx` and the Spediamo token in `.env`, run:

```bash
php artisan tinker --execute 'try { $r = app(App\Shipping\Tracking\TrackingManager::class)->driver("spediamo")->track(new App\Shipping\Tracking\DTO\TrackingRequest("shp_xxx", "sda", "ORD-PROBE", "shp_xxx")); dump($r); } catch (Throwable $e) { dump(get_class($e), $e->getMessage()); }'
```

Pass/fail: `TrackingRejected` (or any 4xx other than 404) means the lockout is live today; a 404 or a normal result means it is only latent. **Task 3 is implemented either way**, because it is cheap and removes the dependence on this answer. The result only decides how urgent it is and whether the one-off data fix at the end of Task 3 is needed.

- [ ] **Step 2:** If no Spediamo access is available, skip and write "not verified" in the PR description.

### V4: do production orders share address rows with the address book? (decides Task 10)

- [ ] **Step 1: the owner runs these read-only on the production database**

```sql
SELECT COUNT(*) FROM orders o JOIN addresses a ON a.id IN (o.billing_address_id, o.shipping_address_id) WHERE a.user_id IS NOT NULL;
SELECT COUNT(*) FROM (SELECT address_id FROM (SELECT billing_address_id address_id FROM orders UNION ALL SELECT shipping_address_id FROM orders) t GROUP BY address_id HAVING COUNT(*) > 1) s;
```

Pass: both `0`. Order addresses were always created with `user_id = null` (`OrderCreationService::createAddress`, unchanged since before the address book), so `0` is expected. If either is above zero, `AddressController::destroy` can delete real orders today: raise Task 10 to the top and add a guard in `destroy`.

### V5: what do the carriers allow when voiding a label? (informs D2)

- [ ] **Step 1:** Run `grep -n -i "cancel\|delete\|void" docs/spediamo/*.md docs/dvaexpress/*.md | head -40` and read the matching sections. Note when each carrier refuses (already collected, already paid, too late). That is the case where Task 7 refuses the undo; if either carrier has a "cancel after pickup" path the admin needs, tell the owner before Task 7.

### V6: is the Task 1 bug live right now?

- [ ] **Step 1:** Run `curl -s -o /dev/null -w '%{http_code}\n' https://SANDBOX_HOST/settings/profile`
Expected after the fix: `302`. A `500` confirms the bug. Only if the sandbox runs `APP_ENV=production` and `APP_DEBUG=false` is the result representative; if it runs `local`, the custom 500 page is skipped by design and the check says nothing. Ask the owner to run the same `curl` against production; a plain GET of a login-protected page is harmless.

---

## File structure

| File | Change | Task |
|---|---|---|
| `bootstrap/app.php` | exception callback excludes redirects/HTTP exceptions; narrower `trustProxies` | 1, 8 |
| `app/Shipping/ShippingService.php` | purchase lock and pre-check; reset polling in `completePurchase` | 2, 3 |
| `app/Console/Commands/SyncShipmentTracking.php` | skip orders awaiting payment | 3 |
| `app/Http/Controllers/Admin/OrdersController.php` | stale-form guard, audit event, void on undo | 4, 6, 7 |
| `app/Domain/Orders/OrderFinancialEdit.php` | payment-status policy | 5 |
| `app/Models/Order.php` | lock financials once shipped/delivered | 5 |
| `app/Models/OrderEvent.php` | `TYPE_PAYMENT_STATUS_CHANGED` | 6 |
| `app/Exceptions/OrderEditRefused.php` | `paymentStatusMoved()` | 4 |
| `resources/js/pages/Admin/Orders/Edit.tsx` | send `payment_status_was` | 4 |
| `resources/js/components/admin/orders/OrderTimeline.tsx`, `lang/*.json` | show the new event | 6 |
| `app/Http/Controllers/Auth/PendingUserVerificationController.php` | stop logging the URL | 9 |
| `database/migrations/2026_10_24_100000_restrict_order_address_deletion.php` | conditional | 10 |
| `tests/Feature/Security/ProductionExceptionPagesTest.php`, `TrustedProxiesTest.php`, `VerificationLogTest.php` | new | 1, 8, 9 |
| `tests/Feature/Shipping/LabelPurchaseGuardTest.php`, `AwaitingPaymentPollingTest.php`; `SpediamoAwaitingPaymentTest.php` (append) | new / extended | 2, 3, 7 |
| `tests/Feature/Admin/PaymentStatusEditTest.php` | new | 4, 5, 6 |

---

## Task 1: Stop the exception handler from swallowing redirects and HTTP errors (#3)

**Files:**
- Modify: `bootstrap/app.php` (imports at the top; the `\Throwable` render callback near line 103)
- Test: `tests/Feature/Security/ProductionExceptionPagesTest.php`

Why: Laravel runs render callbacks before it converts `AuthenticationException` into a redirect, and the callback only excludes `ValidationException`, so in production a guest hitting a protected page gets a 500 page. A CSRF mismatch (419) and a 405 fall through the `HttpException` callback (it returns `null` below 500) into the same catch-all.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Security/ProductionExceptionPagesTest.php`:

```php
<?php

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
 * bootstrap/app.php renders Errors/ServerError for unexpected exceptions, but not in the testing or local-debug
 * environment, so these tests pretend to be production.
 */

beforeEach(function () {
    app()->detectEnvironment(fn () => 'production');
    config(['app.debug' => false]);
});

function inertiaHeaders(): array
{
    return ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) Inertia::getVersion()];
}

it('redirects a guest to the login page instead of rendering the 500 page', function () {
    $this->get('/settings/profile')->assertRedirect(route('login'));
});

it('redirects a guest to the login page for an Inertia visit too', function () {
    $this->get('/settings/profile', inertiaHeaders())->assertRedirect(route('login'));
});

it('keeps the status of an expired CSRF token', function () {
    Route::get('/_test/csrf', fn () => throw new TokenMismatchException);

    $this->get('/_test/csrf')->assertStatus(419);
});

it('keeps the status of a method that is not allowed', function () {
    $this->post('/')->assertStatus(405);
});

it('still renders the server error page for an unexpected exception', function () {
    Route::get('/_test/boom', fn () => throw new RuntimeException('boom'));

    $this->get('/_test/boom', inertiaHeaders())
        ->assertStatus(500)
        ->assertJsonPath('component', 'Errors/ServerError');
});
```

- [ ] **Step 2: Run the test to confirm it fails for the audit's reason**

Run: `php artisan test --compact tests/Feature/Security/ProductionExceptionPagesTest.php`
Expected: the first four tests FAIL with a status of `500` where `302`/`419`/`405` is expected; the last PASSES. If the Inertia tests fail with `409`, the version header is wrong: fix `inertiaHeaders()`, not the app. If the first test already passes, stop: the bug is not reproducible in this environment and the audit claim needs re-checking before any change.

- [ ] **Step 3: Implement**

In `bootstrap/app.php` add the imports next to the existing ones:

```php
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
```

Replace the start of the `\Throwable` callback:

```php
        $exceptions->render(function (\Throwable $e, $request) {
            // Don't intercept ValidationException - let Laravel handle it
            if ($e instanceof ValidationException) {
                return null;
            }
```

with:

```php
        $exceptions->render(function (\Throwable $e, $request) {
            // Laravel turns these into a redirect or a plain HTTP response itself (guest to login, 419, 405, 429, ...)
            if ($e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof HttpExceptionInterface) {
                return null;
            }
```

- [ ] **Step 4: Run the test to confirm it passes**

Run: `php artisan test --compact tests/Feature/Security/ProductionExceptionPagesTest.php`
Expected: 5 passed. Then run `php artisan test --compact tests/Feature/Auth tests/Feature/Security` to make sure nothing else in those folders depended on the old behaviour.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add bootstrap/app.php tests/Feature/Security/ProductionExceptionPagesTest.php
git commit -m "fix: let guest redirects and HTTP errors through the production exception page"
```

---

## Task 2: One label purchase per order, and never for an order that cannot ship (#6)

**Files:**
- Modify: `app/Shipping/ShippingService.php` (imports; `shipWithLabel`, around line 182)
- Test: `tests/Feature/Shipping/LabelPurchaseGuardTest.php`

Why: `createShipment` (the call that spends money) runs at `ShippingService.php:199`, before the only guard (`lockAndGuard`, inside the later transaction). `OrdersController::processShip` does not check `canShip()` either, so even a second sequential POST reaches the carrier and ends in an orphan shipment row. Requires V1 to have passed.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Shipping/LabelPurchaseGuardTest.php`:

```php
<?php

use App\Models\Order;
use App\Models\User;
use App\Shipping\Support\NextWorkingDay;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');
    $this->admin = User::factory()->create(['role' => 'admin']);
    config(['shipping.carriers' => ['acme' => ['name' => 'Acme Courier', 'tracking_url' => 'https://track.example.test/?n={number}']]]);
    settings()->set('shipping.labels_enabled', true);
    settings()->set('shipping.label_driver', 'fake');
    $this->order = Order::factory()->create([
        'user_id' => null,
        'guest_email' => 'guest@example.test',
        'status' => 'processing',
        'payment_status' => 'succeeded',
        'tracking_number' => null,
        'tracking_carrier' => null,
    ]);
    $this->order->orderItems()->create(['product_name' => 'Widget', 'quantity' => 1, 'price' => 10, 'total' => 10]);
});

function purchaseBody(): array
{
    return [
        'tracking_carrier' => 'acme',
        'create_label' => true,
        'pickup_date' => NextWorkingDay::after(CarbonImmutable::now())->toDateString(),
        'parcels' => [['weight' => 1.5]],
    ];
}

it('does not call the carrier again for an order that already shipped', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), purchaseBody())->assertSessionHas('success');
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), purchaseBody())->assertSessionHas('error');

    // a second carrier call would have left an orphan shipment row behind
    expect($this->order->shipments()->count())->toBe(1);
});

it('refuses a purchase while another one for the same order is in progress', function () {
    $lock = Cache::lock("ship-order:{$this->order->id}", 30);
    expect($lock->get())->toBeTrue();

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), purchaseBody())->assertSessionHas('error');

    expect($this->order->fresh()->status)->toBe('processing')
        ->and($this->order->shipments()->count())->toBe(0);

    $lock->release();
});

it('releases the lock after a purchase so the next order action is not blocked', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), purchaseBody())->assertSessionHas('success');

    $lock = Cache::lock("ship-order:{$this->order->id}", 30);
    expect($lock->get())->toBeTrue();
    $lock->release();
});
```

- [ ] **Step 2: Run to confirm failure**

Run: `php artisan test --compact tests/Feature/Shipping/LabelPurchaseGuardTest.php`
Expected: test 1 FAILS (`count()` is `2`), test 2 FAILS (the purchase went through: status `shipped`), test 3 PASSES.

- [ ] **Step 3: Implement**

In `app/Shipping/ShippingService.php` add the import before `use Illuminate\Support\Facades\DB;`:

```php
use Illuminate\Support\Facades\Cache;
```

Replace

```php
    /**
     * Labels on: create the shipment and the label PDF at the carrier, then record it and ship. The network call
     * happens first and outside the transaction, so a refusal or an outage never leaves a half-shipped order.
     */
    private function shipWithLabel(Order $order, ShipInput $input, User $actor): ShipOutcome
    {
```

with

```php
    /**
     * Labels on. The carrier call spends money and cannot be rolled back, so the same check that protects the database
     * write (lockAndGuard) also runs before it, under a per-order lock: a double click or a second admin gets a refusal
     * instead of a second purchase.
     */
    private function shipWithLabel(Order $order, ShipInput $input, User $actor): ShipOutcome
    {
        $lock = Cache::lock("ship-order:{$order->id}", 120);

        if (! $lock->get()) {
            throw new LabelProviderUnavailable('A label purchase for this order is already in progress. Wait a moment, then check the order.');
        }

        try {
            $order->refresh();

            if (! $order->canShip()) {
                throw new InvalidOrderTransition("Order {$order->order_number} cannot ship now: it must be processing and paid.");
            }

            return $this->purchaseLabelAndShip($order, $input, $actor);
        } finally {
            $lock->release();
        }
    }

    /**
     * Create the shipment and the label PDF at the carrier, then record it and ship. The network call happens first
     * and outside the transaction, so a refusal or an outage never leaves a half-shipped order.
     */
    private function purchaseLabelAndShip(Order $order, ShipInput $input, User $actor): ShipOutcome
    {
```

- [ ] **Step 4: Run to confirm it passes, then the neighbours**

Run: `php artisan test --compact tests/Feature/Shipping/LabelPurchaseGuardTest.php tests/Feature/Shipping/LabelShippingTest.php tests/Feature/Shipping/ShipGuardTest.php tests/Feature/Shipping/SpediamoAwaitingPaymentTest.php tests/Feature/Shipping/PickupDateTest.php`
Expected: all pass. If a neighbour fails because it expected the old "orphan on second ship" behaviour, read that test: it should assert the refusal, update it to the new guarantee, and say so in the commit message.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Shipping/ShippingService.php tests/Feature/Shipping/LabelPurchaseGuardTest.php
git commit -m "fix: lock and re-check the order before buying a shipping label"
```

---

## Task 3: A paid Spediamo draft starts polling again; unpaid drafts are not polled (#7)

**Files:**
- Modify: `app/Shipping/ShippingService.php` (`completePurchase`, the `toBase()->update` near line 358)
- Modify: `app/Console/Commands/SyncShipmentTracking.php` (`dueOrders`, imports)
- Test: `tests/Feature/Shipping/AwaitingPaymentPollingTest.php` (new), `tests/Feature/Shipping/SpediamoAwaitingPaymentTest.php` (append)

Why: `completePurchase` uses a query-builder update so the `Order::updating` hook (which resets the polling schedule when the tracking number changes) never fires, and a draft that was polled and rejected is left with `tracking_next_sync_at` five years ahead (`ShipmentTrackingService::disablePolling`). Both halves are fixed: the schedule is reset on payment, and unpaid drafts are never queued. V3 only decides urgency.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Shipping/AwaitingPaymentPollingTest.php`:

```php
<?php

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\Bus;
use Tests\Feature\Shipping\Support\TrackingFixtures as Fx;

it('does not queue an order whose shipment still waits for payment', function () {
    Bus::fake();
    Fx::useFake();

    $draft = Fx::shippedOrder(['tracking_number' => 'shp_draft_1']);
    $draft->shipments()->create([
        'carrier_code' => 'sda', 'driver' => 'spediamo', 'external_id' => 'shp_draft_1',
        'tracking_number' => 'shp_draft_1', 'status' => Shipment::STATUS_AWAITING_PAYMENT,
    ]);
    $paid = Fx::shippedOrder(['tracking_number' => 'TRK-2']);

    $this->artisan('tracking:sync')->assertSuccessful()->expectsOutputToContain('Queued 1');

    // a queued order is claimed with a lease (tracking_next_sync_at is set); the draft must stay untouched
    expect(Order::query()->whereKey($draft->id)->value('tracking_next_sync_at'))->toBeNull()
        ->and(Order::query()->whereKey($paid->id)->value('tracking_next_sync_at'))->not->toBeNull();
});
```

Append to `tests/Feature/Shipping/SpediamoAwaitingPaymentTest.php` (after the `fetches and stores the label once the shipment is paid` test; the helpers `awaitingShipment` and the `beforeEach` are already in that file):

```php
it('starts the polling schedule over when the draft is paid', function () {
    $shipment = awaitingShipment($this->order);
    // what a rejected poll of the unpaid draft leaves behind (ShipmentTrackingService::disablePolling)
    $this->order->forceFill(['tracking_next_sync_at' => now()->addYears(5), 'tracking_started_at' => now()->subDays(40)])->saveQuietly();
    Http::fake([
        'api.spediamo.test/v1/shipments/shp_draft_1' => Http::response(['id' => 'shp_draft_1', 'status' => 'BOOKED', 'trackingNumber' => 'COURIER-77', 'courier' => 'SDA']),
        'api.spediamo.test/v1/waybills/shp_draft_1/pdf*' => Http::response("%PDF-1.4\n%%EOF", 200, ['Content-Type' => 'application/pdf']),
    ]);

    $this->actingAs($this->admin)->post(route('admin.orders.shipments.complete', [$this->order, $shipment]))
        ->assertSessionHas('success');

    $order = $this->order->fresh();
    expect($order->tracking_number)->toBe('COURIER-77')
        ->and($order->tracking_next_sync_at)->toBeNull()
        ->and($order->tracking_started_at)->toBeNull();
});
```

- [ ] **Step 2: Run to confirm failure**

Run: `php artisan test --compact tests/Feature/Shipping/AwaitingPaymentPollingTest.php tests/Feature/Shipping/SpediamoAwaitingPaymentTest.php --filter="waits for payment|starts the polling"`
Expected: both FAIL (the draft is queued: `Queued 2`; `tracking_next_sync_at` still five years ahead).

- [ ] **Step 3: Implement**

In `app/Shipping/ShippingService.php`, `completePurchase`, replace

```php
        // query builder: Order::updating would wipe the tracking status and polling schedule
        Order::query()->whereKey($order->id)->toBase()->update(['tracking_number' => $paid->trackingNumber]);
```

with

```php
        // query builder: Order::updating would also wipe the tracking status. The unpaid draft may have been polled and
        // rejected (polling switched off for years), so the schedule and the polling window start over with the real number.
        Order::query()->whereKey($order->id)->toBase()->update([
            'tracking_number' => $paid->trackingNumber,
            'tracking_next_sync_at' => null,
            'tracking_started_at' => null,
        ]);
```

In `app/Console/Commands/SyncShipmentTracking.php` add `use App\Models\Shipment;` after `use App\Models\Order;`, and in `dueOrders` add one line after `->where('tracking_number', '!=', '')`:

```php
            ->whereDoesntHave('shipments', fn ($shipments) => $shipments->where('status', Shipment::STATUS_AWAITING_PAYMENT))
```

- [ ] **Step 4: Run to confirm it passes**

Run: `php artisan test --compact tests/Feature/Shipping/AwaitingPaymentPollingTest.php tests/Feature/Shipping/SpediamoAwaitingPaymentTest.php tests/Feature/Shipping/SyncTrackingTest.php tests/Feature/Shipping/DeferredShippedEmailTest.php`
Expected: all pass.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Shipping/ShippingService.php app/Console/Commands/SyncShipmentTracking.php tests/Feature/Shipping/AwaitingPaymentPollingTest.php tests/Feature/Shipping/SpediamoAwaitingPaymentTest.php
git commit -m "fix: reset tracking polling when a Spediamo draft is paid and skip unpaid drafts"
```

- [ ] **Step 6: One-off data check (only if V3 showed the lockout is live, or the owner has already bought drafts)**

Rows already locked out keep the five-year date. List them first, read-only:

```sql
SELECT id, order_number, tracking_number, tracking_next_sync_at FROM orders WHERE status = 'shipped' AND tracking_next_sync_at > NOW() + INTERVAL 1 YEAR;
```

Reset only the rows that belong to a paid Spediamo shipment (`shipments.driver = 'spediamo'` and `status = 'created'`) with `UPDATE orders SET tracking_next_sync_at = NULL, tracking_started_at = NULL WHERE id IN (...)`. Rows disabled for an unsupported carrier are meant to stay disabled. Hand the list to the owner; do not run the update yourself.

---

## Task 4: A stale edit form must not overwrite a newer payment status (#4)

**Files:**
- Modify: `app/Http/Controllers/Admin/OrdersController.php` (`update`, around lines 237-281; new private method)
- Modify: `app/Exceptions/OrderEditRefused.php`
- Modify: `resources/js/pages/Admin/Orders/Edit.tsx` (`submitForm`, around line 296)
- Test: `tests/Feature/Admin/PaymentStatusEditTest.php`

Why: `Edit.tsx` sends `payment_status` on every save and `update()` always writes it. A form opened while the payment was `pending` and saved after the Stripe webhook set `succeeded` puts `pending` back, even if the admin only changed a note. New rule: the form also sends the value it showed (`payment_status_was`); `payment_status` is only written when the admin changed it, and only when it has not moved since.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Admin/PaymentStatusEditTest.php`:

```php
<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->admin = User::factory()->create(['role' => 'admin']);
});

function paymentEditOrder(string $paymentStatus, array $attributes = []): Order
{
    $order = Order::factory()->create(array_merge([
        'status' => 'processing',
        'payment_status' => $paymentStatus,
        'user_id' => null,
        'guest_email' => 'buyer@example.test',
        'stripe_payment_intent_id' => null,
    ], $attributes));
    $order->orderItems()->create(['product_id' => null, 'product_name' => 'Line A', 'quantity' => 2, 'price' => 10.00, 'total' => 20.00]);

    return $order->fresh('orderItems');
}

it('does not put an old payment status back when an unrelated save comes from a stale form', function () {
    // the form was loaded while the payment was pending; the Stripe webhook has since captured it
    $order = paymentEditOrder('succeeded', ['stripe_payment_intent_id' => 'pi_test_1']);

    $this->actingAs($this->admin)->put(route('admin.orders.update', $order), [
        'payment_status' => 'pending',
        'payment_status_was' => 'pending',
        'notes' => 'called the customer',
    ])->assertSessionHas('success');

    expect($order->fresh()->payment_status)->toBe('succeeded')
        ->and($order->fresh()->notes)->toBe('called the customer');
});

it('refuses a payment status change made on a form that is out of date', function () {
    $order = paymentEditOrder('succeeded');

    $this->actingAs($this->admin)->put(route('admin.orders.update', $order), [
        'payment_status' => 'failed',
        'payment_status_was' => 'pending',
    ])->assertSessionHasErrors('order');

    expect($order->fresh()->payment_status)->toBe('succeeded');
});

it('still lets an admin mark an offline order as paid', function () {
    $order = paymentEditOrder('pending');

    $this->actingAs($this->admin)->put(route('admin.orders.update', $order), [
        'payment_status' => 'succeeded',
        'payment_status_was' => 'pending',
    ])->assertSessionHas('success');

    expect($order->fresh()->payment_status)->toBe('succeeded');
});
```

- [ ] **Step 2: Run to confirm failure**

Run: `php artisan test --compact tests/Feature/Admin/PaymentStatusEditTest.php`
Expected: test 1 FAILS (`payment_status` is `pending`), test 2 FAILS (no error, the status became `failed`), test 3 PASSES.

- [ ] **Step 3: Implement the server side**

In `app/Exceptions/OrderEditRefused.php` add after `paymentStatus()`:

```php
    public static function paymentStatusMoved(string $orderNumber, string $current): self
    {
        return new self("The payment status of order {$orderNumber} changed to \"{$current}\" while you were editing it. Reload the page and try again.");
    }
```

In `OrdersController::update`, add to the validation array after the `payment_status` rule:

```php
                'payment_status_was' => ['sometimes', 'string', Rule::in(Order::PAYMENT_STATUSES)],
```

Right after the `$request->validate([...]);` call (before the `shipFormOnly` check) add:

```php
            $paymentStatus = $this->editedPaymentStatus($request, $order);
```

Change the transaction closure header to receive it:

```php
            DB::transaction(function () use ($request, $order, $financial, $newStatus, $newTrackingNumber, $originalStatus, $paymentStatus) {
```

and replace

```php
                if ($request->has('payment_status')) {
                    $attributes['payment_status'] = $request->input('payment_status');
                }
```

with

```php
                if ($paymentStatus !== null) {
                    $attributes['payment_status'] = $paymentStatus;
                }
```

Add the private method next to `refusedTransition`:

```php
    /**
     * The payment status the admin changed on the form, or null when the form left it as it showed it. A value that moved
     * since the form was loaded (webhook, refund) is never overwritten.
     *
     * @throws OrderEditRefused
     */
    private function editedPaymentStatus(Request $request, Order $order): ?string
    {
        if (! $request->has('payment_status')) {
            return null;
        }

        $wanted = (string) $request->input('payment_status');
        $shown = (string) $request->input('payment_status_was', $order->payment_status);

        if ($wanted === $shown) {
            return null;
        }

        if ($order->payment_status !== $shown) {
            throw OrderEditRefused::paymentStatusMoved((string) $order->order_number, (string) $order->payment_status);
        }

        return $wanted;
    }
```

- [ ] **Step 4: Send the value the form showed**

In `resources/js/pages/Admin/Orders/Edit.tsx`, `submitForm`, add under `payment_status: formData.payment_status,`:

```tsx
            payment_status_was: order.payment_status,
```

- [ ] **Step 5: Run to confirm it passes, then the neighbours**

Run: `php artisan test --compact tests/Feature/Admin/PaymentStatusEditTest.php tests/Feature/Admin/OrderEditGuardsTest.php tests/Feature/Orders/OrderStatusConsistencyTest.php`
Expected: all pass. Then `npx tsc --noEmit` (or `npm run types` if that script exists in `package.json`): no new errors.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/OrdersController.php app/Exceptions/OrderEditRefused.php resources/js/pages/Admin/Orders/Edit.tsx tests/Feature/Admin/PaymentStatusEditTest.php
git commit -m "fix: only write the payment status an admin changed, never from a stale form"
```

---

## Task 5: Payment-status policy and a lock once the order is fulfilled (#5, #19)

**Files:**
- Modify: `app/Domain/Orders/OrderFinancialEdit.php` (new method; header comment)
- Modify: `app/Http/Controllers/Admin/OrdersController.php` (`editedPaymentStatus`)
- Modify: `app/Models/Order.php` (`financialsLocked`)
- Test: `tests/Feature/Admin/PaymentStatusEditTest.php` (append)

Why: with the guard removed in commit `14533d1`, an admin can take a paid order to `pending`, rewrite lines and prices (stock is not adjusted), and mark it `succeeded` again; or mark an unpaid order `refunded`, which `Revenue::net` counts as full revenue because no `refunds` row exists. Decision D1 applies: Stripe-paid orders keep their payment status read-only once captured, `refunded`/`partially_refunded` are only ever written by `RefundService`, and shipped or delivered orders can no longer have lines or totals edited. `OrderEditRefused::paymentStatus()` already exists with the right message ("it follows Stripe and refunds") and is unused.

- [ ] **Step 1: Check the lock's other users first**

Run: `grep -rn "financialsLocked\|financials_locked" app resources/js`
Expected: `OrderFinancialEdit::plan`, `OrdersController` (the `financials_locked` Inertia prop), `Edit.tsx`. All three want the broader meaning; if anything else turns up (for example a refund path), read it before changing the method.

- [ ] **Step 2: Write the failing tests** (append to `tests/Feature/Admin/PaymentStatusEditTest.php`)

```php
it('does not let a Stripe-captured payment be taken back by an edit', function () {
    $order = paymentEditOrder('succeeded', ['stripe_payment_intent_id' => 'pi_test_1']);

    $this->actingAs($this->admin)->put(route('admin.orders.update', $order), [
        'payment_status' => 'pending',
        'payment_status_was' => 'succeeded',
    ])->assertSessionHasErrors('order');

    expect($order->fresh()->payment_status)->toBe('succeeded');
});

it('does not let anyone set a refunded status by hand on a Stripe order', function (string $status) {
    $order = paymentEditOrder('pending', ['stripe_payment_intent_id' => 'pi_test_1']);

    $this->actingAs($this->admin)->put(route('admin.orders.update', $order), [
        'payment_status' => $status,
        'payment_status_was' => 'pending',
    ])->assertSessionHasErrors('order');

    expect($order->fresh()->payment_status)->toBe('pending');
})->with(['refunded', 'partially_refunded']);

it('locks lines and totals once the order has shipped, even when the payment status was edited', function (string $status) {
    $order = paymentEditOrder('pending', ['status' => $status]);
    $item = $order->orderItems->first();

    $this->actingAs($this->admin)->put(route('admin.orders.update', $order), [
        'order_items' => json_encode([['id' => $item->id, 'quantity' => 5, 'price' => 10]]),
    ])->assertSessionHasErrors('order');

    expect($item->fresh()->quantity)->toBe(2);
})->with(['shipped', 'delivered']);
```

- [ ] **Step 3: Run to confirm failure**

Run: `php artisan test --compact tests/Feature/Admin/PaymentStatusEditTest.php`
Expected: the new tests FAIL (the status changed; the quantity became `5`); the three from Task 4 still pass.

- [ ] **Step 4: Implement the policy**

In `app/Domain/Orders/OrderFinancialEdit.php` add after `plan()`:

```php
    /**
     * Admins may correct the payment status of an order paid outside Stripe (bank transfer, cash). For an order paid
     * through Stripe the captured states follow Stripe and the refunds flow: an edit cannot take them back, and
     * "refunded" or "partially_refunded" are only ever written by RefundService.
     *
     * @throws OrderEditRefused
     */
    public static function assertPaymentStatusEditable(Order $order, string $to): void
    {
        if (! $order->hasStripePaymentIntent() && ! $order->hasStripeCheckoutSession()) {
            return;
        }

        $claimsRefund = in_array($to, ['refunded', 'partially_refunded'], true);

        if ($order->hasCapturedPayment() || $claimsRefund) {
            throw OrderEditRefused::paymentStatus();
        }
    }
```

Update the class docblock line `- payment_status is editable directly by admins in the edit order form.` to:

```
 * - payment_status is editable by admins for orders paid outside Stripe; see assertPaymentStatusEditable().
```

In `OrdersController::editedPaymentStatus` replace the final `return $wanted;` with:

```php
        OrderFinancialEdit::assertPaymentStatusEditable($order, $wanted);

        return $wanted;
```

In `app/Models/Order.php` replace the body of `financialsLocked()`:

```php
        return $this->payment_status === 'processing' || $this->hasCapturedPayment();
```

with

```php
        return $this->payment_status === 'processing'
            || $this->hasCapturedPayment()
            || in_array($this->status, ['shipped', 'delivered'], true);
```

and extend its docblock with: `A shipped or delivered order is fulfilled: whatever its payment status says, its lines and totals no longer change.`

- [ ] **Step 5: Run to confirm it passes, then the order suites**

Run: `php artisan test --compact tests/Feature/Admin tests/Feature/Orders`
Expected: all pass. If an existing test edited lines of a shipped order, read it; it encoded the loophole, so update it to expect the refusal and mention it in the commit message.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Orders/OrderFinancialEdit.php app/Http/Controllers/Admin/OrdersController.php app/Models/Order.php tests/Feature/Admin/PaymentStatusEditTest.php
git commit -m "fix: keep Stripe payment status read-only once captured and lock fulfilled orders"
```

---

## Task 6: Record payment-status edits in the order's audit trail (#25)

**Files:**
- Modify: `app/Models/OrderEvent.php`
- Modify: `app/Http/Controllers/Admin/OrdersController.php` (`update`)
- Modify: `resources/js/components/admin/orders/OrderTimeline.tsx`
- Modify: `lang/en.json`, `lang/it.json`, `lang/de.json`, `lang/es.json`, `lang/fr.json`
- Test: `tests/Feature/Admin/PaymentStatusEditTest.php` (append)

Why: a payment-only edit goes through `$order->save()`, which writes no `OrderEvent`; `OrderEvent` has no payment type at all. `order_events.type` is `string(30)` and `from_status`/`to_status` are `string(20)`, so `payment_status_changed` (22) and `partially_refunded` (18) fit without a migration.

- [ ] **Step 1: Write the failing test** (append)

```php
it('records who changed the payment status, from what and to what', function () {
    $order = paymentEditOrder('pending');

    $this->actingAs($this->admin)->put(route('admin.orders.update', $order), [
        'payment_status' => 'succeeded',
        'payment_status_was' => 'pending',
        'reason' => 'bank transfer received',
    ])->assertSessionHas('success');

    $event = $order->events()->where('type', 'payment_status_changed')->sole();

    expect($event->actor_id)->toBe($this->admin->id)
        ->and($event->from_status)->toBe('pending')
        ->and($event->to_status)->toBe('succeeded')
        ->and($event->payload['reason'])->toBe('bank transfer received');
});

it('records nothing when the payment status was left alone', function () {
    $order = paymentEditOrder('pending');

    $this->actingAs($this->admin)->put(route('admin.orders.update', $order), [
        'payment_status' => 'pending',
        'payment_status_was' => 'pending',
        'notes' => 'just a note',
    ])->assertSessionHas('success');

    expect($order->events()->where('type', 'payment_status_changed')->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run to confirm failure**

Run: `php artisan test --compact tests/Feature/Admin/PaymentStatusEditTest.php --filter="records"`
Expected: the first FAILS (`sole()` finds no row); the second PASSES.

- [ ] **Step 3: Implement**

In `app/Models/OrderEvent.php` add after `TYPE_STATUS_CHANGED`:

```php
    public const TYPE_PAYMENT_STATUS_CHANGED = 'payment_status_changed';
```

In `OrdersController::update`, inside the transaction closure capture the old value before `$order->fill($attributes);`:

```php
                $paymentFrom = $order->payment_status;
```

and after the `if ($newStatus !== $originalStatus) {...} else {...}` block (before the address loop) add:

```php
                if ($paymentStatus !== null) {
                    $order->events()->create([
                        'actor_id' => $request->user()?->getKey(),
                        'type' => OrderEvent::TYPE_PAYMENT_STATUS_CHANGED,
                        'from_status' => $paymentFrom,
                        'to_status' => $paymentStatus,
                        'payload' => array_filter(['reason' => trim((string) $request->input('reason')) ?: null]) ?: null,
                    ]);
                }
```

In `resources/js/components/admin/orders/OrderTimeline.tsx`, add a case to the `title` switch before `default:`:

```tsx
            case 'payment_status_changed':
                return t('admin.orders.timeline.payment_status_changed', {
                    from: t(`admin.orders.payment_status.${event.from_status}`),
                    to: t(`admin.orders.payment_status.${event.to_status}`),
                });
```

Add the key after `"admin.orders.timeline.note"` in each language file (find the line with `grep -n '"admin.orders.timeline.note"' lang/*.json`, keep the file's indentation and trailing comma):

| File | Line to add |
|---|---|
| `lang/en.json` | `"admin.orders.timeline.payment_status_changed": "Payment: {from} → {to}",` |
| `lang/it.json` | `"admin.orders.timeline.payment_status_changed": "Pagamento: {from} → {to}",` |
| `lang/de.json` | `"admin.orders.timeline.payment_status_changed": "Zahlung: {from} → {to}",` |
| `lang/es.json` | `"admin.orders.timeline.payment_status_changed": "Pago: {from} → {to}",` |
| `lang/fr.json` | `"admin.orders.timeline.payment_status_changed": "Paiement : {from} → {to}",` |

- [ ] **Step 4: Run to confirm it passes**

Run: `php artisan test --compact tests/Feature/Admin tests/Feature/Orders tests/Feature/LocalesTest.php`
Expected: all pass (`LocalesTest` guards the language files). Then `npx tsc --noEmit`.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/OrderEvent.php app/Http/Controllers/Admin/OrdersController.php resources/js/components/admin/orders/OrderTimeline.tsx lang/en.json lang/it.json lang/de.json lang/es.json lang/fr.json tests/Feature/Admin/PaymentStatusEditTest.php
git commit -m "feat: record admin payment status edits in the order timeline"
```

---

## Task 7: Void the carrier label when a shipment is undone (#18)

**Files:**
- Modify: `app/Http/Controllers/Admin/OrdersController.php` (`updateStatus`, `update`, two private methods, one import)
- Test: `tests/Feature/Shipping/SpediamoAwaitingPaymentTest.php` (append; its helpers and Spediamo config are already there)

Why: moving a shipped order back to `processing` runs `Order::transitionTo`, which only marks the shipment rows `cancelled` in the database. The separate "cancel shipment" action does call the carrier; the status path does not, so the label stays live and billable. Decision D2: the undo is refused if the carrier refuses. `shipped → processing` requires a reason (`OrderTransitions::REASON_REQUIRED`), so the carrier must not be called when the transition itself would be refused afterwards.

- [ ] **Step 1: Write the failing tests** (append to `SpediamoAwaitingPaymentTest.php`)

```php
it('voids the draft at the carrier when the order is moved back to processing from the status form', function () {
    $shipment = awaitingShipment($this->order);
    Http::fake(['api.spediamo.test/v1/shipments/shp_draft_1' => Http::response('', 204)]);

    $this->actingAs($this->admin)->patch(route('admin.orders.update-status', $this->order), ['status' => 'processing', 'reason' => 'Wrong parcel size'])
        ->assertSessionHas('success');

    Http::assertSent(fn (HttpRequest $request) => $request->method() === 'DELETE' && str_contains($request->url(), '/v1/shipments/shp_draft_1'));
    expect($shipment->fresh()->status)->toBe(Shipment::STATUS_CANCELLED)
        ->and($this->order->fresh()->status)->toBe('processing');
});

it('keeps the order shipped and the shipment active when the carrier refuses to void it', function () {
    $shipment = awaitingShipment($this->order);
    Http::fake(['api.spediamo.test/v1/shipments/shp_draft_1' => Http::response(['message' => 'Already collected'], 409)]);

    $this->actingAs($this->admin)->patch(route('admin.orders.update-status', $this->order), ['status' => 'processing', 'reason' => 'Wrong parcel size'])
        ->assertSessionHas('error');

    expect($shipment->fresh()->status)->toBe(Shipment::STATUS_AWAITING_PAYMENT)
        ->and($this->order->fresh()->status)->toBe('shipped');
});

it('does not call the carrier when the undo is refused for a missing reason', function () {
    awaitingShipment($this->order);
    Http::fake();

    $this->actingAs($this->admin)->patch(route('admin.orders.update-status', $this->order), ['status' => 'processing'])
        ->assertSessionHas('error');

    Http::assertNothingSent();
    expect($this->order->fresh()->status)->toBe('shipped');
});

it('voids the draft when the undo comes through the edit form', function () {
    $shipment = awaitingShipment($this->order);
    Http::fake(['api.spediamo.test/v1/shipments/shp_draft_1' => Http::response('', 204)]);

    $this->actingAs($this->admin)->put(route('admin.orders.update', $this->order), ['status' => 'processing', 'reason' => 'Wrong parcel size'])
        ->assertSessionHas('success');

    Http::assertSent(fn (HttpRequest $request) => $request->method() === 'DELETE');
    expect($shipment->fresh()->status)->toBe(Shipment::STATUS_CANCELLED);
});
```

- [ ] **Step 2: Run to confirm failure**

Run: `php artisan test --compact tests/Feature/Shipping/SpediamoAwaitingPaymentTest.php --filter="voids|refuses to void|missing reason"`
Expected: tests 1, 2 and 4 FAIL (no `DELETE` was sent; test 2: the order became `processing`); test 3 PASSES (the reason rule already refuses it). If test 2 fails differently because Spediamo's 409 does not become a `LabelException`, read `SpediamoLabelProvider::cancelShipment` (line ~221) and adjust the fake status to the one that provider treats as a refusal; do not change the controller to catch more.

- [ ] **Step 3: Implement**

In `OrdersController` add the import `use App\Domain\Orders\OrderTransitions;` (before `use App\Domain\Orders\OrderFinancialEdit;` alphabetically: after it, since `OrderF` < `OrderT`; keep the block sorted).

Add two private methods near `refusedTransition`:

```php
    /**
     * Is this status change an undo of a shipment that the lifecycle will accept? Only then may the carrier be called:
     * a transition that is refused afterwards must not leave a voided label behind.
     */
    private function undoesShipment(Order $order, string $to, mixed $reason): bool
    {
        return $order->status === 'shipped'
            && $to === 'processing'
            && OrderTransitions::allows('shipped', 'processing')
            && (! OrderTransitions::requiresReason('shipped', 'processing') || trim((string) $reason) !== '');
    }

    /**
     * Void every active label of the order at its carrier. A refusal throws (nothing else has changed yet), and a retry
     * only has the remaining active shipments left to void.
     *
     * @throws LabelException
     */
    private function voidActiveShipments(Order $order, ShippingService $shipping): void
    {
        foreach ($order->shipments()->whereIn('status', Shipment::ACTIVE_STATUSES)->get() as $shipment) {
            $shipping->cancelShipment($shipment);
        }
    }
```

In `updateStatus` replace

```php
        try {
            $order->transitionTo($request->status, $request->user(), $request->input('reason'));
        } catch (InvalidOrderTransition $e) {
            return $this->refusedTransition($e);
        }
```

with

```php
        try {
            if ($this->undoesShipment($order, $request->status, $request->input('reason'))) {
                $this->voidActiveShipments($order, app(ShippingService::class));
            }

            $order->transitionTo($request->status, $request->user(), $request->input('reason'));
        } catch (InvalidOrderTransition $e) {
            return $this->refusedTransition($e);
        } catch (LabelException $e) {
            return back()->withErrors(['status' => $e->getMessage()])->with('error', $e->getMessage());
        }
```

In `update`, right before the `DB::transaction(function () ...` call, add:

```php
            // the carrier call stays outside the transaction; a refusal stops the whole edit before anything is written
            if ($this->undoesShipment($order, $newStatus, $request->input('reason'))) {
                $this->voidActiveShipments($order, app(ShippingService::class));
            }
```

and add a catch before the generic `catch (\Exception $e)` in `update`:

```php
        } catch (LabelException $e) {
            return back()->withErrors(['status' => $e->getMessage()])->with('error', $e->getMessage());
```

- [ ] **Step 4: Run to confirm it passes, then the neighbours**

Run: `php artisan test --compact tests/Feature/Shipping tests/Feature/Admin/OrderEventsTest.php tests/Feature/Orders/OrderStatusConsistencyTest.php`
Expected: all pass, including `ShipmentUndoE2ETest` (it undoes a manual shipment; `cancelShipment` marks manual shipments cancelled without a network call).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/OrdersController.php tests/Feature/Shipping/SpediamoAwaitingPaymentTest.php
git commit -m "fix: void the carrier label when an admin moves a shipped order back to processing"
```

---

## Task 8: Trust only the proxy that is actually in front of the app (#2)

**Files:**
- Modify: `bootstrap/app.php` (import; `trustProxies` at line 29)
- Test: `tests/Feature/Security/TrustedProxiesTest.php`

Why: `trustProxies(at: '*')` trusts every peer and every forwarded header, so `Request::ip()` returns the leftmost `X-Forwarded-For` entry, which the client controls (runtime proof in the review: `1.2.3.4, 9.9.9.9` gave `1.2.3.4`). Limiting the trusted peers to the private ranges makes Symfony take the entry Traefik appended; dropping `X-Forwarded-Host` stops a client-supplied host from reaching signed-URL generation (the app has `URL::forceScheme` but no `forceRootUrl`). This is not a regression (the deleted `TrustProxies.php` also had `'*'`). **Use the V2 result:** if it showed a peer address outside the private ranges, change the `at:` list below before committing.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Security/TrustedProxiesTest.php`:

```php
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/_test/client', fn (Request $request) => response()->json(['ip' => $request->ip(), 'host' => $request->getHost()]));
});

it('takes the client address the proxy appended, not the one the client sent', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.2'])
        ->withHeaders(['X-Forwarded-For' => '1.2.3.4, 9.9.9.9'])
        ->getJson('/_test/client')
        ->assertJsonPath('ip', '9.9.9.9');
});

it('ignores forwarded headers from a peer outside the private ranges', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
        ->withHeaders(['X-Forwarded-For' => '1.2.3.4'])
        ->getJson('/_test/client')
        ->assertJsonPath('ip', '8.8.8.8');
});

it('does not let X-Forwarded-Host change the host', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.2'])
        ->withHeaders(['X-Forwarded-Host' => 'evil.example'])
        ->getJson('/_test/client')
        ->assertJsonPath('host', fn (string $host) => $host !== 'evil.example');
});
```

- [ ] **Step 2: Run to confirm failure**

Run: `php artisan test --compact tests/Feature/Security/TrustedProxiesTest.php`
Expected: tests 1 and 3 FAIL (`ip` is `1.2.3.4`; `host` is `evil.example`); test 2 FAILS too (with `'*'` the public peer is trusted: `ip` is `1.2.3.4`).

- [ ] **Step 3: Implement**

In `bootstrap/app.php` add `use Illuminate\Http\Request;` with the other `Illuminate\Http` imports and replace

```php
        $middleware->trustProxies(at: '*');
```

with

```php
        // Traefik (and the container's own nginx) sit in the Docker private ranges. Trust only those, and only for
        // the client IP and scheme: Host, Prefix and Port are deliberately not trusted.
        $middleware->trustProxies(
            at: ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', 'fc00::/7'],
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
        );
```

- [ ] **Step 4: Run to confirm it passes, then everything that builds URLs**

Run: `php artisan test --compact tests/Feature/Security tests/Feature/Auth tests/Feature/Checkout`
Expected: all pass (signed verification links and Stripe return URLs depend on scheme and host).

- [ ] **Step 5: Verify on the sandbox after the owner deploys it** (never production)

Repeat the V2 probe: the log line must show `host` = `SANDBOX_HOST` while `x_forwarded_host` may still show the spoofed value. Then open a real verification email link and confirm it still validates (HTTPS scheme and host must survive the proxy). If the signature fails, the peer is outside the list: re-read the V2 peer address and fix `at:`.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add bootstrap/app.php tests/Feature/Security/TrustedProxiesTest.php
git commit -m "fix: trust forwarded headers only from the private proxy ranges"
```

---

## Task 9: Stop writing the verification token and email to the log (#8)

**Files:**
- Modify: `app/Http/Controllers/Auth/PendingUserVerificationController.php` (line ~60)
- Test: `tests/Feature/Security/VerificationLogTest.php`

Why: the invalid-signature branch logs `$request->fullUrl()`, which contains the 64-character token, the email address, `expires` and the signature. The fields that explain a host or scheme mismatch (`scheme`, `host`, `port`, `app_url`, `expires`, `now`, the three `x_forwarded_*` headers) are already logged separately, so dropping the URL loses nothing needed for diagnosis.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Security/VerificationLogTest.php`:

```php
<?php

use Illuminate\Support\Facades\Log;

it('does not write the token or the email to the log when a verification link has an invalid signature', function () {
    Log::spy();

    $this->get('/verification/verify/secrettoken1234567890/someone@example.test')->assertRedirect(route('register'));

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context) => $message === 'Pending verification link rejected: invalid signature'
            && ! str_contains(json_encode($context), 'secrettoken1234567890')
            && ! str_contains(json_encode($context), 'someone@example.test'))
        ->once();
});
```

- [ ] **Step 2: Run to confirm failure**

Run: `php artisan test --compact tests/Feature/Security/VerificationLogTest.php`
Expected: FAIL (`shouldHaveReceived` finds the call but the context contains the token). If it fails with a 404, the route path differs: take it from `php artisan route:list --path=verification` and fix the test, not the app.

- [ ] **Step 3: Implement**

In `PendingUserVerificationController::verify` delete the line

```php
                'request_url' => $request->fullUrl(),
```

- [ ] **Step 4: Run to confirm it passes, then the verification suite**

Run: `php artisan test --compact tests/Feature/Security/VerificationLogTest.php tests/Feature/Auth`
Expected: all pass.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Auth/PendingUserVerificationController.php tests/Feature/Security/VerificationLogTest.php
git commit -m "fix: keep the verification token and email out of the signature-mismatch log"
```

- [ ] **Step 6: Existing log files**

The token of links that were rejected before this fix is already in `storage/logs/laravel.log` on the sandbox and production containers. Tell the owner; whether to scrub or rotate is their call (a rejected link's token is only usable with a valid signature, so the practical risk is low).

---

## Task 10 (conditional on V4 and the owner's go-ahead): restrict deleting an address an order uses (#1)

**Files:**
- Create: `database/migrations/2026_10_24_100000_restrict_order_address_deletion.php`
- Test: `tests/Feature/Orders/OrderAddressRestrictTest.php`

Why: `orders.billing_address_id` and `shipping_address_id` still cascade on delete (`2025_07_27_104230_create_orders_table.php:21-22`; `2026_10_15_100000` only fixed `user_id`). The audit review found no app path that deletes an order-linked address, so this is a latent hazard, not a live bug. A restrict makes a stray delete fail loudly instead of removing an order. It changes the production schema, so it waits for V4 (both counts `0`) and the owner's approval. If V4 found shared rows, do this task first and also stop `AddressController::destroy` from deleting any address an order references.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Orders/OrderAddressRestrictTest.php`:

```php
<?php

use App\Models\Order;
use Illuminate\Database\QueryException;

it('refuses to delete an address that an order uses, instead of deleting the order', function () {
    $order = Order::factory()->create();

    expect(fn () => $order->shippingAddress->delete())->toThrow(QueryException::class);
    expect(Order::query()->whereKey($order->id)->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run to confirm failure**

Run: `php artisan test --compact tests/Feature/Orders/OrderAddressRestrictTest.php`
Expected: FAIL (no exception: the delete succeeds and takes the order with it). Also check that the test database enforces foreign keys: `php artisan tinker --execute 'dump(DB::select("PRAGMA foreign_keys"));'` should show `1` under the testing environment.

- [ ] **Step 3: Implement the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An order's billing and shipping addresses were ON DELETE CASCADE: deleting an address row deleted the order. Deleting
 * an address an order uses is now refused by the database. (Order addresses are separate rows from the address book, so
 * no application path hits this; it guards against a stray delete.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['billing_address_id']);
            $table->dropForeign(['shipping_address_id']);
            $table->foreign('billing_address_id')->references('id')->on('addresses')->restrictOnDelete();
            $table->foreign('shipping_address_id')->references('id')->on('addresses')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['billing_address_id']);
            $table->dropForeign(['shipping_address_id']);
            $table->foreign('billing_address_id')->references('id')->on('addresses')->cascadeOnDelete();
            $table->foreign('shipping_address_id')->references('id')->on('addresses')->cascadeOnDelete();
        });
    }
};
```

- [ ] **Step 4: Run to confirm it passes, then everything that deletes users or addresses**

Run: `php artisan test --compact tests/Feature/Orders tests/Feature/Account tests/Feature/Settings`
Expected: all pass, in particular `AccountDeletionKeepsOrdersTest`, `OrderMigrationsTest` and the address-book tests. Also run the migration on a copy of a real database (sandbox MariaDB) before it goes anywhere near production: `php artisan migrate --pretend` shows the SQL, and MariaDB must accept the `dropForeign` names Laravel generates (`orders_billing_address_id_foreign`).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_10_24_100000_restrict_order_address_deletion.php tests/Feature/Orders/OrderAddressRestrictTest.php
git commit -m "fix: refuse to delete an address that an order still uses"
```

---

## Final verification (after the tasks you chose to do)

- [ ] **Step 1:** `graphify update .` (AST only, no API cost) and commit the refreshed `graphify-out/` as its own commit.
- [ ] **Step 2:** Run the groups touched, once each: `php artisan test --compact tests/Feature/Security tests/Feature/Shipping tests/Feature/Admin tests/Feature/Orders tests/Feature/Auth tests/Feature/Checkout tests/Feature/LocalesTest.php`. Expected: all pass.
- [ ] **Step 3:** `npx tsc --noEmit` and `npm run build`. Expected: no errors.
- [ ] **Step 4:** Ask the owner to run the whole suite with `php artisan test --compact` (project rule). The known baseline failures on `main` are the `ProductListPerPageTest` cases from the missing `Categories/Show.tsx`, per the memory notes.
- [ ] **Step 5: go-live checklist for the owner's single deploy** (nothing here is deployed by this plan):
  1. V1 passed on production: `CACHE_STORE` is `redis` or `database`.
  2. Task 8: after deploy, the verification-email link still validates on production, and `X-Forwarded-Host: evil.example` no longer changes the resolved host.
  3. Task 1: `curl -s -o /dev/null -w '%{http_code}\n' https://ecom.rsp-industries.com/settings/profile` returns `302`.
  4. Task 3, Step 6: the list of locked-out Spediamo orders (if any) is handed over.
  5. Results of V2, V3, V4, V5 are written in the PR description.

## Self-review

- **Coverage:** every row of the scope table maps to a task (3 → T1, 6 → T2, 7 → T3, 4/5/19/25 → T4-T6, 18 → T7, 2 → T8, 8 → T9, 1 → T10). Claims the review found false have no task, listed explicitly. The Low batch is deferred by name, not forgotten.
- **Placeholders:** none; each code step carries the code. The two places that depend on facts only a live run can show (Spediamo's 409 mapping in Task 7 Step 2, the peer range in Task 8) say exactly what to read and what to change.
- **Names:** `editedPaymentStatus` (Task 4) is extended in Task 5 and its result `$paymentStatus` is used in Task 6; `OrderEditRefused::paymentStatusMoved` (Task 4) and `paymentStatus()` (existing, Task 5) are the only exception factories used; `undoesShipment`/`voidActiveShipments` are defined and used in Task 7 only; the lock key `ship-order:{id}` is identical in the code and in the tests of Task 2.

---

## Execution notes (2026-10-24)

Branch `fix/audit-remediation`, one commit per task, each reviewed for spec compliance and code quality.

| Task | Commits | Notes |
|---|---|---|
| 1 | 11c3d1c | as planned |
| 2 | fdee634 | as planned |
| 3 | 6dd959e | as planned (Step 6 data check left to the owner) |
| 4 | d6cc0fd, 138a510 | review found `payment_status_was` read from the refreshed prop; now captured once at mount |
| 5 | d2f5a59 | as planned |
| 6 | 836ca0c | as planned |
| 7 | 65d517e | as planned |
| 8 | b600fb5, b1c3a3f | review found `X-Forwarded-Prefix` also poisons `root()` (`@evil.example`); Prefix and Port are no longer trusted (the Task 8 code above is the corrected version) |
| 8b | 9b4f8bd | not in the original plan: `TrustAppUrlScheme` middleware, see below |
| 9 | ea24ced | as planned |
| 10 | not done | conditional on V4 and the owner's approval |

**Task 8b: why it exists.** Phase 0 and a follow-up check showed the real topology is Cloudflare, then Traefik, then nginx in `serversideup/php:8.5-fpm-nginx`. That image rewrites the client address from `CF-Connecting-IP`, so PHP's `REMOTE_ADDR` is the public client IP, and it sets no `HTTPS` fastcgi param. With Task 8 alone the peer is untrusted, `X-Forwarded-Proto` is ignored, `isSecure()` is false, cookies lose `Secure` and signed links (generated https by `forceScheme`, validated against the request scheme) would be rejected. `TrustAppUrlScheme` sets `HTTPS=on` when `APP_URL` is https and the environment is not local/testing, which is correct under either topology.

**Phase 0 results.** V1: local cache is redis; remote `CACHE_STORE` unreadable (Dokploy redacts env), so it must be confirmed by the owner. V2: the sandbox accepted a spoofed `X-Forwarded-Host` (redirect to `https://evil.example/register`) before Task 8; `LoginRequest::throttleKey` uses `ip()`. V3 and V4: not run (need a real Spediamo draft and the production database). V5: Spediamo deletes only DRAFT shipments, DVA Express cancels through `richiesta-storno` for shipments that have not departed. V6: sandbox `/settings/profile` redirected to `/login` (sandbox env redacted, so not conclusive).

**Open items for the owner before the single deploy**
1. In Dokploy (sandbox first): `APP_URL` starts with `https://`; `APP_ENV` is not `local`/`testing`; `CACHE_STORE` is `redis`, `database` or `file`; set `SESSION_SECURE_COOKIE=true` (belt and braces; the middleware also covers it).
2. Behind Cloudflare the image trusts `CF-Connecting-IP` from private peers: make sure the origin is only reachable through Cloudflare, otherwise a client can forge its IP at nginx level (or set `TRUSTED_PROXY=off`/`local` in the container env).
3. After deploying the sandbox: open a real emailed verification link (it must validate); `curl -sI https://<sandbox>/ | grep -i set-cookie` must show `secure`; replay the `X-Forwarded-Host: evil.example` and `X-Forwarded-Prefix: @evil.example` probes (the redirect and email links must stay on the sandbox host); confirm Task 1 with `curl -s -o /dev/null -w '%{http_code}' https://<host>/settings/profile` returning `302`.
4. Run V4 on production before deciding on Task 10; hand over the Task 3 Step 6 list if V3 shows the lockout is live.
5. Behaviour changes to tell support staff: Stripe-captured payment status is read-only and cannot be set to refunded by hand; shipped/delivered orders have locked lines and totals; undoing a shipment with a bought Spediamo label is refused (Spediamo cannot void it through the API, cancel in the Spediamo portal); a second label purchase for the same order is refused.
6. Existing `storage/logs/laravel.log` files may still contain rejected verification tokens from before Task 9.

**Minor review notes not acted on** (candidates for the Low-severity follow-up plan): `HttpResponseException` is not excluded by the Task 1 guard (unused today); cache outage while taking/releasing the Task 2 lock gives a raw 500 (fails closed); no test that the Task 2 lock is released after a carrier failure; the shipped-order lock message still says "paid or in progress"; the payment status Select still offers options the server refuses; the same reason text is stored on both events when status and payment change together; the `str_starts_with(app.url, 'https://')` check now exists in `AppServiceProvider` and `TrustAppUrlScheme`; `docs/reports/mixed-content-https-fix.md` still describes `$proxies = '*'`; `PendingUserVerificationController` and `bootstrap/app.php` carry pre-existing Pint style violations that were left untouched to keep diffs minimal.
