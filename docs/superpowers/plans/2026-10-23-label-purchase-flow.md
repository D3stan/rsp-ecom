# Label Purchase Flow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Buying a label from the Ship page becomes a real workflow: the admin picks the pickup date (default: next working day), confirms price/date/balance, gets the label to download (Dvaexpress) or is sent to pay the Spediamo draft on Spediamo (their API has no pay operation), and the customer's "shipped" email goes out only when the carrier first scans the parcel.

**Architecture:** The pickup date travels `Ship.tsx` -> `processShip` -> `ShipInput` -> `ShipmentRequest` -> each label mapper. A shipment row gains `pickup_date`, `notify_on_pickup` and a new status `awaiting_payment`. Label purchase no longer sends the email: it sets `notify_on_pickup`, and `ShipmentTrackingService::advance()` sends the one shipped email the first time the carrier reports the parcel in transit. Spediamo's `createShipment` returns a draft flagged `awaitingPayment`; a new optional `ConfirmsPayment` capability fetches the paid shipment and its PDF on demand from the order page.

**Tech Stack:** Laravel 13, Pest 4, Inertia + React/TS, `lang/*.json` (en, it, de, fr, es).

**Decisions already taken with the owner (do not re-litigate):**
- The order goes to `shipped` as soon as the label is bought (or the draft is created); only the customer email is deferred.
- Email trigger = first carrier scan = tracking status `in_transit` or `out_for_delivery`. If the first status the sync ever sees is `delivered`, no shipped email is sent (the delivered email follows).
- Manual shipping (admin types the tracking number) is unchanged: email at once, as today.
- Spediamo: draft + "pay on Spediamo" at `https://spediamo.it/app/v1#/spedizioni/da-acquistare`; the owner pays with their prepaid balance there.
- Confirm step before buying: shows price, pickup date, balance after purchase when known; the label is downloadable afterwards.

**Facts verified against the live Spediamo API** (recorded in `tests/Fixtures/spediamo/`, replayed by `tests/Feature/Shipping/SpediamoProbeFixturesTest.php`):
- `requestedPickupDate` must be ISO `YYYY-MM-DD` (the error text claiming `gg/mm/aaaa` is wrong). Today, past dates, weekends and carrier holidays return `requestedPickupDate: invalid ("Data non consentita o non più valida")`. `requestedPickupTime` is optional (`MORNING`/`AFTERNOON`).
- With a valid pickup date the draft has no errors and `expectedDeliveryDate` is an object `{value, localizedValue}`.
- `GET /v1/shipments/settings?type=DOMESTIC` returns the earliest pickup date and the carrier's holiday list (`tests/Fixtures/spediamo/settings_domestic.json`). It is not used here (YAGNI: the local calendar plus the carrier's own rejection is enough), but is the upgrade path if the carrier's closures (24 and 31 December) keep biting.
- `PATCH`/`PUT /v1/shipments/{id}` answer `428` without extra preconditions, so the date is sent when the draft is created.
- Drafts are `status: DRAFT`. Spediamo's own docs: draft = not yet bought, booked = bought, ready = scheduled for pickup. Nothing in the exported API pays a draft. **The paid response shape has never been observed**: Task 5's paid-path tests are built from the documented statuses, not a recording; the plan says so in the test header and the final report must repeat it.
- Never call an undocumented payment endpoint on the live API; it may spend the owner's credit.

---

## File structure

| File | Responsibility |
|---|---|
| Create `database/migrations/2026_10_23_200000_add_pickup_and_notify_to_shipments.php` | `pickup_date`, `notify_on_pickup`, status as string with `awaiting_payment` |
| Modify `app/Models/Shipment.php` | New status constant, casts, fillable |
| Create `app/Shipping/Support/NextWorkingDay.php` | Italian working-day calendar |
| Modify `app/Shipping/ShipInput.php`, `Labels/DTO/ShipmentRequest.php`, `Labels/ShipmentBuilder.php` | Carry `pickupDate` |
| Modify `app/Http/Controllers/Admin/OrdersController.php` | `showShip` prop, `processShip` validation, new `completeShipment` action; routes in `routes/admin.php` |
| Modify `app/Shipping/Providers/Spediamo/SpediamoLabelRequestMapper.php`, `SpediamoRateProvider.php`, `SpediamoLabelProvider.php` | Send the date, draft-awaiting-payment, `confirmPurchase` |
| Modify `app/Shipping/Providers/Dvaexpress/DvaexpressLabelRequestMapper.php` | Pickup date from the request |
| Create `app/Shipping/Labels/Contracts/ConfirmsPayment.php`; modify `Labels/DTO/ShipmentResult.php` | Capability + `awaitingPayment` flag |
| Modify `app/Shipping/ShippingService.php` | Label path: no immediate email, awaiting payment, `completePurchase()` |
| Modify `app/Shipping/Tracking/ShipmentTrackingService.php` | Send the deferred email on first scan |
| Modify `app/Models/Order.php` (`toFrontendArray` shipments) | Expose `status`, `pickup_date`, `pay_url`, `notify_on_pickup` |
| Modify `resources/js/pages/Admin/Orders/Ship.tsx`, `Show.tsx`; create `resources/js/components/admin/orders/BuyLabelDialog.tsx` | UI |
| Modify `lang/{en,it,de,fr,es}.json` | New keys |
| Tests | `tests/Unit/Shipping/NextWorkingDayTest.php`, `tests/Feature/Shipping/PickupDateTest.php`, `tests/Feature/Shipping/DeferredShippedEmailTest.php`, `tests/Feature/Shipping/SpediamoAwaitingPaymentTest.php` |

Existing tests to keep green and update where behaviour changes on purpose: `LabelShippingTest.php`, `DvaexpressLabelProviderTest.php`, `SpediamoLabelProviderTest.php`, `ShipFormTest.php`, `ShipGuardTest.php`, `SyncTrackingTest.php`, `tests/Unit/Shipping/ArchitectureTest.php`.

---

### Task 1: Shipment columns and model

**Files:** create the migration; modify `app/Models/Shipment.php`; test in `tests/Feature/Shipping/PickupDateTest.php`.

- [ ] **Step 1: Failing test** (the model accepts and casts the new columns):

```php
it('stores the pickup date, the deferred-email flag and the awaiting_payment status on a shipment', function () {
    $shipment = \App\Models\Order::factory()->create()->shipments()->create([
        'carrier_code' => 'pdb', 'driver' => 'spediamo', 'status' => \App\Models\Shipment::STATUS_AWAITING_PAYMENT,
        'pickup_date' => '2026-10-26', 'notify_on_pickup' => true,
    ]);

    expect($shipment->fresh()->pickup_date->toDateString())->toBe('2026-10-26')
        ->and($shipment->fresh()->notify_on_pickup)->toBeTrue()
        ->and($shipment->fresh()->status)->toBe('awaiting_payment');
});
```

- [ ] **Step 2:** `php vendor/pestphp/pest/bin/pest tests/Feature/Shipping/PickupDateTest.php`. Expected: FAIL (constant undefined).
- [ ] **Step 3: Implement.** Migration `up()`:

```php
Schema::table('shipments', function (Blueprint $table) {
    $table->date('pickup_date')->nullable()->after('label_path');
    // label purchases defer the shipped email until the carrier first scans the parcel
    $table->boolean('notify_on_pickup')->default(false)->after('pickup_date');
    $table->string('status', 30)->default('created')->change();
});
```

`down()`: drop the two columns (leave `status` a string; note it in a comment). Model: `public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';`, add `pickup_date`, `notify_on_pickup` to `$fillable`, casts `'pickup_date' => 'date', 'notify_on_pickup' => 'boolean'`. Check every place that filters `where('status', Shipment::STATUS_CREATED)` (`ShippingService::trackingChanged`, the order page) and decide for each whether an `awaiting_payment` shipment counts as active; `cancelShipment` must still work on it (cancel = delete the draft at Spediamo).
- [ ] **Step 4:** Run the test (PASS) and `tests/Feature/Shipping`. Commit `Add pickup date, deferred-email flag and awaiting_payment status to shipments`.

---

### Task 2: Italian working-day calendar

**Files:** create `app/Shipping/Support/NextWorkingDay.php`; test `tests/Unit/Shipping/NextWorkingDayTest.php`.

- [ ] **Step 1: Failing test**

```php
use App\Shipping\Support\NextWorkingDay;
use Carbon\CarbonImmutable;

it('skips weekends and Italian national holidays', function (string $from, string $expected) {
    expect(NextWorkingDay::after(CarbonImmutable::parse($from))->toDateString())->toBe($expected);
})->with([
    'friday to monday' => ['2026-10-23', '2026-10-26'],
    'all saints on a sunday' => ['2026-10-30', '2026-11-02'],
    'christmas and boxing day' => ['2026-12-24', '2026-12-28'],
    'easter monday 2027' => ['2027-03-26', '2027-03-30'],
    'easter monday 2026' => ['2026-04-03', '2026-04-07'],
    'liberation day on a saturday' => ['2026-04-24', '2026-04-27'],
]);

it('knows which days are working days', function () {
    expect(NextWorkingDay::isWorkingDay(CarbonImmutable::parse('2026-08-15')))->toBeFalse()
        ->and(NextWorkingDay::isWorkingDay(CarbonImmutable::parse('2026-10-26')))->toBeTrue();
});
```

- [ ] **Step 2:** Run (FAIL, class missing).
- [ ] **Step 3: Implement**

```php
<?php

namespace App\Shipping\Support;

use Carbon\CarbonImmutable;

/**
 * Italian working days for pickups: Monday to Friday except national holidays (incl. Easter Monday).
 * shortcut: carrier-specific closures (e.g. 24 and 31 December at Spediamo) are not modelled; the carrier rejects those
 * dates and the admin sees its message. Upgrade: read GET /v1/shipments/settings (pickupDate + holidays).
 */
final class NextWorkingDay
{
    private const FIXED = ['01-01', '01-06', '04-25', '05-01', '06-02', '08-15', '11-01', '12-08', '12-25', '12-26'];

    public static function after(CarbonImmutable $from): CarbonImmutable
    {
        $day = $from->startOfDay()->addDay();

        while (! self::isWorkingDay($day)) {
            $day = $day->addDay();
        }

        return $day;
    }

    public static function isWorkingDay(CarbonImmutable $day): bool
    {
        return ! $day->isWeekend()
            && ! in_array($day->format('m-d'), self::FIXED, true)
            && $day->toDateString() !== self::easterMonday($day->year)->toDateString();
    }

    /** Anonymous Gregorian algorithm (no ext-calendar needed). */
    private static function easterMonday(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $dayOfMonth = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $dayOfMonth)->addDay();
    }
}
```

- [ ] **Step 4:** Run the test (PASS). Commit `Add Italian working-day calendar for pickups`.

---

### Task 3: Carry the pickup date to the label providers

**Files:** `ShipInput.php` (new `?string $pickupDate`, Y-m-d, normalised like the other fields), `ShipmentRequest.php` (new `?string $pickupDate = null` as last constructor argument), `ShipmentBuilder::build()` (new last parameter, passed through), `ShippingService::shipWithLabel` (passes `$input->pickupDate`), `OrdersController::showShip` / `processShip`; test in `PickupDateTest.php`.

- [ ] **Step 1: Failing tests** (feature, via the HTTP endpoint with a fake label driver like `LabelShippingTest.php` does):
  - posting a label purchase without `pickup_date` -> `assertSessionHasErrors('pickup_date')`;
  - with a Saturday, a national holiday, today, or a malformed date -> `assertSessionHasErrors('pickup_date')`;
  - with the next working day -> the fake driver's `createShipment` received a `ShipmentRequest` with that `pickupDate`, and the `shipments` row has it in `pickup_date`;
  - `showShip` returns `default_pickup_date` equal to `NextWorkingDay::after(now())->toDateString()`.
- [ ] **Step 2:** Run them (FAIL).
- [ ] **Step 3: Implement.** In `processShip`'s `$rules`:

```php
'pickup_date' => [Rule::requiredIf($wantsLabel), 'nullable', 'date_format:Y-m-d', function ($attribute, $value, $fail) {
    if ($value === null) {
        return;
    }

    $day = \Carbon\CarbonImmutable::parse($value);
    if ($day->lessThanOrEqualTo(now()->startOfDay()) || ! \App\Shipping\Support\NextWorkingDay::isWorkingDay($day)) {
        $fail('The pickup date must be a future working day.');
    }
}],
```

Pass `pickupDate: $wantsLabel ? ($data['pickup_date'] ?? null) : null` into `ShipInput`; `showShip` adds `'default_pickup_date' => NextWorkingDay::after(CarbonImmutable::now())->toDateString()`. `shipWithLabel` stores `'pickup_date' => $input->pickupDate` on the shipment.
- [ ] **Step 4:** Run `tests/Feature/Shipping`. Commit `Carry the pickup date from the ship form to the shipment request`.

---

### Task 4: Providers use the pickup date

**Files:** `SpediamoLabelRequestMapper.php`, `SpediamoRateProvider.php` + `Rates/DTO/RateRequest.php` (optional `?string $pickupDate = null`, last argument), `ShippingQuotesController::quoteOrder` + `RateManager::requestFromOrder` (new optional `pickup_date` in, validated like Task 3 but `nullable`), `DvaexpressLabelRequestMapper::mapRitiro`.

- [ ] **Step 1: Failing tests**
  - Spediamo label mapper: with `pickupDate: '2026-10-26'` the mapped params contain `requestedPickupDate => '2026-10-26'`; without it the key is absent (not `''`).
  - Spediamo rate payload: same, and replaying `tests/Fixtures/spediamo/probes/accept_pickup_date_iso.json` through `quote()` returns a quote with `estimatedDelivery === '2026-10-29'` (already green via the fixture test; add an assertion on the outgoing request too).
  - Dvaexpress `mapOrderFromQuote` with `pickupDate: '2026-10-27'` has `ritiro.dettagli.disponibile_dal === '2026-10-27'`; without it the previous default (config, else next weekday) is unchanged.
- [ ] **Step 2:** Run (FAIL).
- [ ] **Step 3: Implement.** Spediamo: add `'requestedPickupDate' => $request->pickupDate` to both param arrays (the existing `array_filter` drops null). Dvaexpress: `mapRitiro(?string $pickupDate)` uses `$pickupDate ?? $pickup['date'] ?? now()->addWeekday()->format('Y-m-d')`, called with `$request->pickupDate` from `mapOrderFromQuote` and the non-quote order mapper (find its call site with `grep -n mapRitiro`). `RateManager::requestFromOrder` takes `?string $pickupDate = null` as a new last parameter.
- [ ] **Step 4:** Run `tests/Feature/Shipping`. Commit `Send the chosen pickup date to Spediamo and Dvaexpress`.

---

### Task 5: Spediamo "draft, pay on Spediamo, then fetch the label"

**Files:** create `app/Shipping/Labels/Contracts/ConfirmsPayment.php`; modify `ShipmentResult.php`, `SpediamoLabelProvider.php`, `ShippingService.php`, `OrdersController.php` (+ route), `config/shipping.php` (`providers.spediamo.purchase_url`); test `tests/Feature/Shipping/SpediamoAwaitingPaymentTest.php`.

Contract:

```php
interface ConfirmsPayment
{
    /**
     * The shipment once it has been paid, or null while it is still a draft.
     *
     * @throws LabelRejected
     * @throws LabelProviderUnavailable
     */
    public function confirmPurchase(string $externalId): ?ShipmentResult;
}
```

`ShipmentResult` gets `public bool $awaitingPayment = false` as the last constructor argument.

Behaviour:
- `SpediamoLabelProvider::createShipment`: when the response `status` is `DRAFT` the result has `awaitingPayment: true` (today it already falls back to the draft id as tracking number; keep that placeholder, the tracking sync replaces it with the courier number once the shipment is paid).
- `SpediamoLabelProvider implements ConfirmsPayment`: `GET /v1/shipments/{id}`; status `DRAFT` (or `UNDERPAID`, `FAILED`, anything unpaid per the documented status list: draft, booked, ready, current, on_hold, underpaid, failed) -> `null`; otherwise return a `ShipmentResult` with the carrier tracking number (`trackingNumber`, else `code`).
- `ShippingService::shipWithLabel`: when `$result->awaitingPayment`, skip `$provider->label()` and the PDF, create the shipment with status `awaiting_payment`, `label_path` null, `pickup_date`, `notify_on_pickup` true. Otherwise as today (status `created`, `notify_on_pickup` true). The order still moves to `shipped`.
- New `ShippingService::completePurchase(Shipment $shipment): bool`: resolves the provider, requires `ConfirmsPayment` (else `LabelRejected`), calls `confirmPurchase`; `null` -> returns false; otherwise fetches `label()`, stores the PDF exactly like `shipWithLabel` does (reuse the same private helper, extract it), updates the shipment (`status` created, `tracking_number`, `label_path`) and the order's `tracking_number`, returns true.
- `OrdersController::completeShipment(Order, Shipment)` -> `POST admin/orders/{order}/shipments/{shipment}/complete` (`scopeBindings`, admin group): true -> `success` "Payment received, the label is ready to download."; false -> `warning` "Spediamo still shows this shipment as unpaid. Pay it on Spediamo, then check again."; `LabelException` -> `error` with the message.
- `config/shipping.php`: `providers.spediamo.purchase_url` = `env('SPEDIAMO_PURCHASE_URL', 'https://spediamo.it/app/v1#/spedizioni/da-acquistare')`. `Order::toFrontendArray` shipments expose `status`, `pickup_date`, `notify_on_pickup` and, for `awaiting_payment`, `pay_url` (that config value) and `draft_code` (the shipment's `tracking_number`).

- [ ] **Step 1: Failing tests** (the paid shape is NOT recorded: say so in a header comment of the test file):
  - creating with a `DRAFT` response -> shipment `awaiting_payment`, no `label_path`, order `shipped`, `Http::assertNotSent` for `/v1/waybills`;
  - `confirmPurchase` returns `null` for the recorded draft body (`tests/Fixtures/spediamo/probes/accept_pickup_date_iso.json`'s response) and a `ShipmentResult` for a `BOOKED` body with `trackingNumber`;
  - `completeShipment` endpoint: unpaid -> warning flash and shipment unchanged; paid (fake `BOOKED` + PDF bytes starting `%PDF`) -> shipment `created`, `label_path` stored, order tracking number updated;
  - a non-admin gets 403.
- [ ] **Step 2:** Run (FAIL).
- [ ] **Step 3: Implement** as specified. Keep `cancelShipment` working for `awaiting_payment` (it deletes the draft).
- [ ] **Step 4:** Run `tests/Feature/Shipping tests/Feature/Admin tests/Unit/Shipping`. Commit `Spediamo: create a draft awaiting payment and fetch the label once it is paid`.

---

### Task 6: Customer email only at the first carrier scan

**Files:** `ShippingService.php` (`shipWithLabel`, `trackingChanged`), `ShipmentTrackingService.php` (`advance`), `OrdersController::processShip` (flash text); test `tests/Feature/Shipping/DeferredShippedEmailTest.php`.

- [ ] **Step 1: Failing tests** (follow `ShipFormTest.php`'s `Queue::fake()` / `OrderEmail` helpers):
  - buying a label (fake driver, not awaiting payment) sends NO shipped email, leaves `notify_on_pickup` true, `ShipOutcome::$emailFailed` is false;
  - manual shipping with a tracking number still sends one email immediately (existing `ShipFormTest` cases stay green);
  - a tracking update to `in_transit` for that order sends exactly one shipped email carrying the order's current tracking number and clears the flag; a second `in_transit` update sends nothing;
  - `out_for_delivery` as the first status also sends it; `info_received` and `exception` send nothing and keep the flag; `delivered` as the first status sends nothing and clears the flag;
  - `trackingChanged()` on an order with a pending flag sends nothing (the admin editing the number must not mail the customer early);
  - if the email cannot be queued the flag stays true (the next sync retries) and the sync still succeeds.
- [ ] **Step 2:** Run (FAIL).
- [ ] **Step 3: Implement.** `shipWithLabel`: delete the `sendOrderShipped` call, `return new ShipOutcome($shipment, false)`. In `ShipmentTrackingService::advance()`, after the order is saved (outside the lock-sensitive part, still inside `apply`'s transaction is fine because the email is queued), add:

```php
if ($moves && in_array($new, [TrackingStatus::InTransit, TrackingStatus::OutForDelivery, TrackingStatus::Delivered], true)) {
    $this->sendDeferredShippedEmail($order, $new);
}
```

```php
/** Label purchases hold the shipped email back until the carrier has the parcel. */
private function sendDeferredShippedEmail(Order $order, TrackingStatus $status): void
{
    $shipment = $order->shipments()->where('notify_on_pickup', true)->reorder()->latest('id')->first();

    if ($shipment === null) {
        return;
    }

    // a parcel first seen as delivered gets the delivered email only
    if ($status === TrackingStatus::Delivered || app(EmailService::class)->sendOrderShipped($order, $order->tracking_number)) {
        $shipment->update(['notify_on_pickup' => false]);

        return;
    }

    Log::warning('Deferred shipped email could not be queued; it will be retried on the next sync', ['order_id' => $order->id]);
}
```

`trackingChanged()`: at the top, `if ($order->shipments()->where('notify_on_pickup', true)->exists()) { return true; }`. `processShip`'s success flash for a label: `'Label bought. The customer is emailed when the carrier picks the parcel up.'` (awaiting payment: `'Draft created on Spediamo. Pay it there, then press "Check payment" on this order.'`).
- [ ] **Step 4:** Run `tests/Feature/Shipping tests/Feature/Mail tests/Feature/Admin`. Commit `Send the shipped email on the first carrier scan for purchased labels`.

---

### Task 7: Ship page and order page

**Files:** create `resources/js/components/admin/orders/BuyLabelDialog.tsx`; modify `Ship.tsx`, `Show.tsx`, `lang/*.json`.

- [ ] **Step 1: Lang keys** in all five files (real translations), under `admin.orders.ship.`: `pickup_date` ("Pickup date"), `pickup_date_help` ("The carrier collects the parcel on this day. Weekends and holidays are not available."), `confirm_buy_title` ("Buy this label?"), `confirm_buy_summary` (carrier + service + price), `confirm_pickup` ("Pickup {date}"), `confirm_delivery` ("Estimated delivery {date}"), `confirm_balance` ("Balance {provider}: {before} → {after}"), `confirm_buy_action` ("Buy for €{price}"), `email_on_pickup` ("The customer is emailed when the carrier picks the parcel up."), `awaiting_payment_title` ("Waiting for payment on {provider}"), `awaiting_payment_body` ("Open Spediamo, find draft {code} under 'To buy' and pay it with your balance."), `pay_on_provider` ("Pay on {provider}"), `check_payment` ("Check payment"), `label_ready` ("Label ready").
- [ ] **Step 2: Ship.tsx.** Receive `default_pickup_date`; state `pickupDate` (initial default); a date `Input` (`min` = tomorrow's date, `aria-invalid` from `errors.pickup_date`) in the Options row next to insurance, with the help text; included in the form data as `pickup_date`; added to `quoteInputs` so rates reload on change and sent as `pickup_date` to `/admin/orders/{id}/quotes`. The footer button for the label tab no longer submits directly: it opens `BuyLabelDialog`, which shows the selected quote (carrier, service, price, margin), the pickup date, `estimated_delivery` when present, the destination address (name, street, postal code, city) and, when `rates.balances[selected.provider]` is known, the balance before and after; the footer hint becomes `email_on_pickup` for the label tab. Confirming calls the existing `send()`. Manual tab unchanged.
- [ ] **Step 3: Show.tsx.** Shipment rows: status `awaiting_payment` -> an amber panel (`awaiting_payment_title/body`), a button linking to `pay_url` in a new tab (`rel="noopener noreferrer"`) and a **Check payment** button doing `router.post('/admin/orders/{id}/shipments/{shipment}/complete', {}, { preserveScroll: true })`; no download link until `created`. Created label shipments keep the download link, and show `email_on_pickup` while `notify_on_pickup` is true. The `?label=` print banner only for `created` shipments.
- [ ] **Step 4:** `npx tsc --noEmit`, `npm run build`, eslint + prettier on the touched files, `php vendor/pestphp/pest/bin/pest tests/Feature/LocalesTest.php tests/Feature/Shipping`. Expected: clean. Then the full suite `php vendor/pestphp/pest/bin/pest`.
- [ ] **Step 5:** `graphify update .`, commit the UI as `Ship page: pickup date, buy confirmation and awaiting-payment panel` and graphify-out as its own commit.

---

## Self-review

- **Coverage:** pickup date editable, default next working day (Tasks 2-3, 7); confirm step with balance and label download (7); Spediamo pay-on-Spediamo (5); deferred customer email at the first scan (6); incremental commits (one per task).
- **Not verified live:** the paid Spediamo response (status values after payment, tracking number field), Dvaexpress `disponibile_dal` acceptance of a custom date (docs only), the UI in a browser.
- **Known gaps:** carrier-specific closures are only caught by the carrier's rejection; Spediamo prepaid balance is not read (no documented endpoint), so the dialog shows a balance only for providers that implement `InspectsBalance` (Dvaexpress).
