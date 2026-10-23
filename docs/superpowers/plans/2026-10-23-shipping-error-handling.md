# Explicit Shipping Error Handling Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When a carrier rejects a quote because of wrong shipping information, the admin sees which field is wrong and why, and can correct the order's shipping address on the Ship page without leaving it.

**Architecture:** Providers stop flattening carrier errors into one string. They attach a list of structured `ShippingIssue`s (kind, field, code, carrier message) to the exception they already throw. `RateManager::quoteAll()` returns them next to the existing `errors` strings (which stay, for the CLI command and current tests). A cheap store-side check adds issues the carriers would only report badly. The Ship page groups issues by kind: address issues open an inline correction dialog that PATCHes a new endpoint and refetches rates; sender/credential issues link to the shipping settings; parcel issues open the parcel editor.

**Tech Stack:** Laravel 13, Pest 4, Inertia + React/TypeScript, `lang/*.json` (en, it, de, fr, es).

**Out of scope (follow-ups):** structured issues for label *purchase* failures (`LabelRejected` keeps today's flash), the free-form `/admin/shipping/quotes` page (its amber banner stays), structured Dvaexpress API errors (only its own pre-checks are converted, its API text shows as kind `other`).

---

## Task 0 (blocker, separate from this plan): the Spediamo `packages` format

The ship page cannot show rates while Spediamo rejects every request, so this must be fixed first. It is not part of the error-handling work and **cannot be verified without the live API**.

Evidence (`php artisan shipping:test-rates --driver=spediamo --raw`, 2026-10-23): `packages=1x1x35x25x8` returns `packages[0].quantity: integer`. Sender and recipient are accepted. Commits `0ff6c29` and `8809426` only reordered tokens and mocked the error in tests, so none of them proved the format. The error names `packages[0]` even though the string has one package, which suggests Spediamo is not splitting the string on `x`.

- [ ] **Step 1:** In the container, send the object form by hand (`curl -s -X POST https://api.spediamo.it/v1/shipments -H "Authorization: Bearer $TOKEN" --data-urlencode ...` with the same fields as the `--raw` output but `packages[0][quantity]=1&packages[0][weight]=1&packages[0][length]=35&packages[0][width]=25&packages[0][height]=8` instead of `packages=`), then `DELETE /v1/shipments/{id}` on the draft it creates.
- [ ] **Step 2:** If it is accepted, change `SpediamoRateProvider::formatPackages()` (`app/Shipping/Providers/Spediamo/SpediamoRateProvider.php:191`) and `SpediamoLabelRequestMapper::packages()` (`SpediamoLabelRequestMapper.php:106`) to return `array<string, scalar>` of `packages[N][field]` keys, spread into the payload. Replace the `packages` assertions in `tests/Feature/Shipping/SpediamoApiContractTest.php` and `SpediamoLabelProviderTest.php` with ones on the new keys.
- [ ] **Step 3:** Re-run the `--raw` command. Expected: `Returned Quotes (1 quotes ...)`. Commit.

---

## File structure

| File | Responsibility |
|---|---|
| Create `app/Shipping/Support/ShippingIssue.php` | One structured problem: provider, kind, field, code, message |
| Create `app/Shipping/Support/CarriesIssues.php` | Trait: `$issues` + `withIssues()` for exceptions |
| Modify `app/Shipping/Rates/Exceptions/RateException.php` | Use the trait |
| Create `app/Shipping/Providers/Spediamo/SpediamoIssueMapper.php` | Spediamo `errors[]` to `ShippingIssue[]` |
| Modify `app/Shipping/Providers/Spediamo/SpediamoRateProvider.php` | Attach issues when it rejects |
| Modify `app/Shipping/Providers/Dvaexpress/DvaexpressRateProvider.php` | Attach issues to its own pre-check rejections |
| Create `app/Shipping/Support/RecipientAddressCheck.php` | Store-side check of the order's recipient |
| Modify `app/Shipping/Rates/RateManager.php` | `quoteAll()` also returns `issues` |
| Modify `app/Http/Controllers/Admin/ShippingQuotesController.php` | `quoteOrder()` returns `issues` |
| Modify `app/Http/Controllers/Admin/OrdersController.php`, `routes/admin.php` | `updateShippingAddress` endpoint |
| Create `resources/js/components/admin/orders/ShippingIssues.tsx` | Issue panel + correction dialog |
| Modify `resources/js/pages/Admin/Orders/Ship.tsx` | Keep `issues` in the rates state, render the panel |
| Modify `lang/{en,it,de,fr,es}.json` | New `admin.orders.ship.issue_*` keys |
| Tests | `tests/Unit/Shipping/ShippingIssueTest.php`, `tests/Feature/Shipping/ShippingIssuesTest.php`, `tests/Feature/Shipping/ShippingAddressCorrectionTest.php` |

`tests/Unit/Shipping/ArchitectureTest.php` constrains what `app/Shipping` may depend on. Run it after Task 1.

---

### Task 1: `ShippingIssue` and the exception trait

**Files:**
- Create: `app/Shipping/Support/ShippingIssue.php`, `app/Shipping/Support/CarriesIssues.php`
- Modify: `app/Shipping/Rates/Exceptions/RateException.php`
- Test: `tests/Unit/Shipping/ShippingIssueTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Shipping\Rates\Exceptions\RateRejected;
use App\Shipping\Support\ShippingIssue;

it('serialises an issue and rides on a rate exception', function () {
    $issue = new ShippingIssue('spediamo', ShippingIssue::ADDRESS, 'recipient.postal_code', 'invalid', 'Non valido');

    expect($issue->toArray())->toBe([
        'provider' => 'spediamo',
        'kind' => 'address',
        'field' => 'recipient.postal_code',
        'code' => 'invalid',
        'message' => 'Non valido',
    ]);

    $e = (new RateRejected('boom'))->withIssues([$issue]);

    expect($e->issues)->toBe([$issue])->and($e->getMessage())->toBe('boom');
});
```

- [ ] **Step 2:** `./vendor/bin/pest tests/Unit/Shipping/ShippingIssueTest.php`. Expected: FAIL, class `ShippingIssue` not found.

- [ ] **Step 3: Implement**

`app/Shipping/Support/ShippingIssue.php`:

```php
<?php

namespace App\Shipping\Support;

/**
 * One concrete problem behind a failed quote. `message` is the carrier's own wording: admin-only, never for customers.
 */
final readonly class ShippingIssue
{
    /** Recipient data the admin can correct on the order. */
    public const ADDRESS = 'address';

    /** Sender data: fixed in the shipping settings. */
    public const SETTINGS = 'settings';

    public const PARCEL = 'parcel';

    /** Timeout, 5xx, bad credentials: nothing to correct on the order. */
    public const UNAVAILABLE = 'unavailable';

    public const OTHER = 'other';

    public function __construct(
        public string $provider,
        public string $kind,
        public ?string $field,
        public string $code,
        public string $message,
    ) {}

    /** @return array{provider: string, kind: string, field: ?string, code: string, message: string} */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
```

`app/Shipping/Support/CarriesIssues.php`:

```php
<?php

namespace App\Shipping\Support;

trait CarriesIssues
{
    /** @var list<ShippingIssue> */
    public array $issues = [];

    /** @param list<ShippingIssue> $issues */
    public function withIssues(array $issues): static
    {
        $this->issues = $issues;

        return $this;
    }
}
```

`RateException`: add `use CarriesIssues;` inside the class (import `App\Shipping\Support\CarriesIssues`).

- [ ] **Step 4:** Re-run the test (PASS), then `./vendor/bin/pest tests/Unit/Shipping`. Expected: all PASS.
- [ ] **Step 5:** `git add app/Shipping tests/Unit/Shipping && git commit -m "feat(shipping): structured ShippingIssue carried by rate exceptions"`

---

### Task 2: Spediamo maps its field errors to issues

**Files:**
- Create: `app/Shipping/Providers/Spediamo/SpediamoIssueMapper.php`
- Modify: `SpediamoRateProvider.php` (the two `RateRejected` throws in `quote()` and `send()`)
- Test: `tests/Feature/Shipping/ShippingIssuesTest.php`

- [ ] **Step 1: Write the failing test** (this uses the exact body Spediamo returned from the live API)

```php
<?php

use App\Shipping\Auth\BearerTokenAuthenticator;
use App\Shipping\Providers\Spediamo\SpediamoRateProvider;
use App\Shipping\Rates\DTO\RateAddress;
use App\Shipping\Rates\DTO\RateParcel;
use App\Shipping\Rates\DTO\RateRequest;
use App\Shipping\Rates\Exceptions\RateRejected;
use Illuminate\Support\Facades\Http;

it('turns Spediamo field errors into typed issues on the exception', function () {
    Http::fake(['api.spediamo.it/v1/shipments' => Http::response([
        'errors' => [
            ['field' => 'recipientPostalCode', 'code' => 'invalid', 'message' => 'Non valido'],
            ['field' => 'senderPhone', 'code' => 'required', 'message' => 'Obbligatorio'],
            ['field' => 'packages[0].quantity', 'code' => 'integer', 'message' => 'Deve essere un numero senza virgole'],
        ],
        'warnings' => null,
    ], 400)]);

    $provider = new SpediamoRateProvider(['base_url' => 'https://api.spediamo.it'], new BearerTokenAuthenticator('t'));
    $request = new RateRequest(
        sender: new RateAddress('47522', 'Cesena', 'FC', 'IT'),
        recipient: new RateAddress('48105', 'Cervia', 'RA', 'IT'),
        parcels: [new RateParcel(weightKg: 1)],
    );

    try {
        $provider->quote($request);
        $this->fail('expected a rejection');
    } catch (RateRejected $e) {
        expect(collect($e->issues)->map->toArray()->all())->toBe([
            ['provider' => 'spediamo', 'kind' => 'address', 'field' => 'recipient.postal_code', 'code' => 'invalid', 'message' => 'Non valido'],
            ['provider' => 'spediamo', 'kind' => 'settings', 'field' => 'senderPhone', 'code' => 'required', 'message' => 'Obbligatorio'],
            ['provider' => 'spediamo', 'kind' => 'parcel', 'field' => 'packages[0].quantity', 'code' => 'integer', 'message' => 'Deve essere un numero senza virgole'],
        ])->and($e->getMessage())->toContain('recipientPostalCode');
    }
});
```

- [ ] **Step 2:** `./vendor/bin/pest tests/Feature/Shipping/ShippingIssuesTest.php`. Expected: FAIL (`$e->issues` is empty).

- [ ] **Step 3: Implement**

`app/Shipping/Providers/Spediamo/SpediamoIssueMapper.php`:

```php
<?php

namespace App\Shipping\Providers\Spediamo;

use App\Shipping\Support\ShippingIssue;

final class SpediamoIssueMapper
{
    /** Spediamo request field => our recipient field (the order's address form uses the same names). */
    private const RECIPIENT = [
        'recipientPostalCode' => 'recipient.postal_code',
        'recipientTown' => 'recipient.city',
        'recipientProvince' => 'recipient.province',
        'recipientAddress' => 'recipient.street',
        'recipientPhone' => 'recipient.phone',
        'recipientEmail' => 'recipient.email',
        'recipientName' => 'recipient.name',
        'recipientContactName' => 'recipient.name',
        'recipientCountry' => 'recipient.country',
    ];

    /**
     * @param  array<int|string, mixed>  $errors  the `errors` list of a Spediamo response
     * @return list<ShippingIssue>
     */
    public static function fromErrors(array $errors): array
    {
        $issues = [];

        foreach ($errors as $error) {
            if (! is_array($error)) {
                continue; // scalar errors have no field: the exception message is all there is
            }

            $raw = (string) ($error['field'] ?? '');
            $field = self::RECIPIENT[$raw] ?? null;
            $kind = match (true) {
                $field !== null => ShippingIssue::ADDRESS,
                str_starts_with($raw, 'sender') => ShippingIssue::SETTINGS,
                str_starts_with($raw, 'packages') => ShippingIssue::PARCEL,
                default => ShippingIssue::OTHER,
            };

            $issues[] = new ShippingIssue(
                SpediamoRateProvider::NAME,
                $kind,
                $field ?? ($raw !== '' ? $raw : null),
                (string) ($error['code'] ?? ''),
                (string) ($error['message'] ?? ''),
            );
        }

        return $issues;
    }
}
```

In `SpediamoRateProvider::quote()`, replace the `RateRejected` built from `$data['errors']`:

```php
$e = (new RateRejected(self::NAME.': '.$errorMsg))->withIssues(SpediamoIssueMapper::fromErrors($data['errors']));
```

In `send()`, the 400/404/422 branch:

```php
$ex = (new RateRejected($msg ?? self::NAME.': HTTP '.$status))
    ->withIssues(SpediamoIssueMapper::fromErrors((array) ($response->json('errors') ?? [])));
```

(`$response->json('errors')` returns `null` when the body has no `errors`; `(array) null` is `[]`.)

- [ ] **Step 4:** Re-run the test (PASS), then `./vendor/bin/pest tests/Feature/Shipping`. Expected: all PASS (the message string is unchanged).
- [ ] **Step 5:** Commit `feat(shipping): Spediamo field errors become typed issues`.

---

### Task 3: Dvaexpress pre-checks become issues

**Files:**
- Modify: `app/Shipping/Providers/Dvaexpress/DvaexpressRateProvider.php:50-81` (the three `RateRejected` throws)
- Test: add to `tests/Feature/Shipping/ShippingIssuesTest.php`

- [ ] **Step 1: Write the failing test**

```php
it('reports a missing Dvaexpress recipient postal code as an address issue', function () {
    config(['shipping.providers.dvaexpress.api_key' => 'k']);
    $provider = app(\App\Shipping\Rates\RateManager::class)->driver('dvaexpress');

    $request = new RateRequest(
        sender: new RateAddress('47522', 'Cesena', 'FC', 'IT'),
        recipient: new RateAddress('', 'Cervia', 'RA', 'IT'),
        parcels: [new RateParcel(weightKg: 1)],
    );

    try {
        $provider->quote($request);
        $this->fail('expected a rejection');
    } catch (RateRejected $e) {
        expect($e->issues[0]->toArray())->toMatchArray(['kind' => 'address', 'field' => 'recipient.postal_code', 'code' => 'required']);
    }
});
```

- [ ] **Step 2:** Run it. Expected: FAIL (`$e->issues[0]` undefined).
- [ ] **Step 3: Implement.** Replace the first three throws with these (the sender one is kind `settings`, the recipient ones `address`; the US/CA province check keeps its message):

```php
if (trim((string) $request->sender->postalCode) === '' || trim((string) $request->sender->city) === '') {
    throw (new RateRejected('Dvaexpress: Sender address requires at least postal code and city (configure shipping.sender_postal_code and shipping.sender_city in settings or .env).'))
        ->withIssues([new ShippingIssue('dvaexpress', ShippingIssue::SETTINGS, 'sender.postal_code', 'required', 'Sender postal code and city are required')]);
}

foreach (['postalCode' => 'postal_code', 'city' => 'city'] as $property => $field) {
    if (trim((string) $request->recipient->{$property}) === '') {
        throw (new RateRejected('Dvaexpress: Recipient address requires at least postal code and city.'))
            ->withIssues([new ShippingIssue('dvaexpress', ShippingIssue::ADDRESS, "recipient.{$field}", 'required', 'Required')]);
    }
}

// ... after $recipientCountry is computed:
if (in_array($recipientCountry, ['US', 'CA'], true) && trim((string) $request->recipient->province) === '') {
    throw (new RateRejected("Dvaexpress: Recipient province (state code) is required for {$recipientCountry}."))
        ->withIssues([new ShippingIssue('dvaexpress', ShippingIssue::ADDRESS, 'recipient.province', 'required', 'Required')]);
}
```

Add `use App\Shipping\Support\ShippingIssue;` and `use App\Shipping\Rates\Exceptions\RateRejected;` (the file uses fully-qualified names today; import instead).

- [ ] **Step 4:** Run `./vendor/bin/pest tests/Feature/Shipping`. Expected: PASS.
- [ ] **Step 5:** Commit `feat(shipping): Dvaexpress pre-check rejections carry issues`.

---

### Task 4: Store-side recipient check

Carriers are slow and inconsistent at telling you the phone or province is missing. The store checks the basics itself, for Italian addresses strictly. (`48105` passes this check, it has five digits; only the carrier knows it is not a real postcode. That is why both layers exist.)

**Files:**
- Create: `app/Shipping/Support/RecipientAddressCheck.php`
- Test: `tests/Unit/Shipping/RecipientAddressCheckTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Shipping\Rates\DTO\RateAddress;
use App\Shipping\Support\RecipientAddressCheck;

it('flags missing and malformed recipient fields', function () {
    $issues = RecipientAddressCheck::issues(new RateAddress('481', 'Cervia', '', 'IT', 'Ada Rossi', '', ''));

    expect(collect($issues)->mapWithKeys(fn ($i) => [$i->field => $i->code])->all())->toBe([
        'recipient.postal_code' => 'invalid',
        'recipient.street' => 'required',
        'recipient.phone' => 'required',
        'recipient.province' => 'required',
    ]);
});

it('accepts a complete Italian address', function () {
    expect(RecipientAddressCheck::issues(new RateAddress('48015', 'Cervia', 'RA', 'IT', 'Ada Rossi', 'Via Roma 1', '3331234567')))->toBe([]);
});

it('does not apply Italian postcode and province rules abroad', function () {
    expect(RecipientAddressCheck::issues(new RateAddress('75001', 'Paris', '', 'FR', 'Ada Rossi', '1 Rue X', '+33123456789')))->toBe([]);
});
```

- [ ] **Step 2:** Run it. Expected: FAIL, class not found.
- [ ] **Step 3: Implement**

```php
<?php

namespace App\Shipping\Support;

use App\Shipping\Rates\DTO\RateAddress;

/**
 * Cheap checks on the order's recipient before (and next to) the carriers' own validation. Provider "store".
 */
final class RecipientAddressCheck
{
    /** @return list<ShippingIssue> */
    public static function issues(RateAddress $recipient): array
    {
        $italy = strtoupper($recipient->country) === 'IT';
        $postal = trim($recipient->postalCode);
        $province = trim($recipient->province);

        $codes = [
            'recipient.postal_code' => $postal === '' ? 'required' : ($italy && ! preg_match('/^\d{5}$/', $postal) ? 'invalid' : null),
            'recipient.city' => trim($recipient->city) === '' ? 'required' : null,
            'recipient.street' => trim($recipient->street) === '' ? 'required' : null,
            'recipient.phone' => trim($recipient->phone) === '' ? 'required' : null,
            'recipient.province' => $italy && ! preg_match('/^[A-Za-z]{2}$/', $province) ? ($province === '' ? 'required' : 'invalid') : null,
        ];

        $issues = [];
        foreach (array_filter($codes) as $field => $code) {
            $issues[] = new ShippingIssue('store', ShippingIssue::ADDRESS, $field, $code, $code === 'required' ? 'Required' : 'Not valid');
        }

        return $issues;
    }
}
```

- [ ] **Step 4:** Run the test (PASS).
- [ ] **Step 5:** Commit `feat(shipping): store-side recipient address check`.

---

### Task 5: `RateManager::quoteAll()` and `quoteOrder()` return the issues

**Files:**
- Modify: `app/Shipping/Rates/RateManager.php` (`quoteAll`), `app/Http/Controllers/Admin/ShippingQuotesController.php` (`quoteOrder`)
- Test: add to `tests/Feature/Shipping/ShippingIssuesTest.php`

- [ ] **Step 1: Write the failing tests**

```php
it('returns provider issues next to the error strings from quoteAll', function () {
    // same Http::fake and RateRequest as the Spediamo test above
    config(['shipping.providers.spediamo.token' => 't', 'shipping.providers.spediamo.base_url' => 'https://api.spediamo.it']);
    Http::fake(['api.spediamo.it/v1/shipments' => Http::response(['errors' => [
        ['field' => 'recipientPostalCode', 'code' => 'invalid', 'message' => 'Non valido'],
    ]], 400)]);

    $result = app(\App\Shipping\Rates\RateManager::class)->quoteAll(new RateRequest(
        sender: new RateAddress('47522', 'Cesena', 'FC', 'IT'),
        recipient: new RateAddress('48105', 'Cervia', 'RA', 'IT'),
        parcels: [new RateParcel(weightKg: 1)],
    ), ['spediamo']);

    expect($result['errors'])->toHaveKey('spediamo')
        ->and($result['issues'][0]->field)->toBe('recipient.postal_code');
});

it('wraps an exception without issues as one issue so nothing is lost', function () {
    $result = app(\App\Shipping\Rates\RateManager::class)->quoteAll(new RateRequest(
        sender: new RateAddress('47522', 'Cesena', 'FC', 'IT'),
        recipient: new RateAddress('48015', 'Cervia', 'RA', 'IT'),
        parcels: [new RateParcel(weightKg: 1)],
    ), ['nope']);

    expect($result['issues'][0]->toArray())->toMatchArray(['provider' => 'nope', 'kind' => 'unavailable']);
});
```

and a controller test that posts to `admin/orders/{id}/quotes` for an order whose shipping address has an empty phone and asserts the JSON `issues` contains `recipient.phone` from provider `store` (use `Http::fake` + the `fake` driver per `ShipFormTest`'s setup; `drivers: ['fake']`).

- [ ] **Step 2:** Run them. Expected: FAIL (`issues` key missing).

- [ ] **Step 3: Implement** in `RateManager::quoteAll()`:

```php
$issues = [];
// ...
} catch (Throwable $e) {
    $errors[$name] = $e->getMessage();
    array_push($issues, ...self::issuesOf($name, $e));
}
// the driver-resolution catch gets the same two lines
// ...
return ['quotes' => $quotes, 'errors' => $errors, 'balances' => $balances, 'issues' => $issues];
```

```php
/** @return list<ShippingIssue> */
private static function issuesOf(string $provider, Throwable $e): array
{
    if ($e instanceof RateException && $e->issues !== []) {
        return $e->issues;
    }

    return [new ShippingIssue($provider, $e instanceof RateRejected ? ShippingIssue::OTHER : ShippingIssue::UNAVAILABLE, null, 'error', $e->getMessage())];
}
```

Update the docblock return shape (`issues: list<ShippingIssue>`). In `quoteOrder()`:

```php
'issues' => array_map(
    fn (ShippingIssue $issue) => $issue->toArray(),
    [...RecipientAddressCheck::issues($rateRequest->recipient), ...$result['issues']],
),
```

- [ ] **Step 4:** `./vendor/bin/pest tests/Feature/Shipping tests/Unit/Shipping`. Expected: PASS. If a test asserts the exact `quoteAll` key set, add `issues` there.
- [ ] **Step 5:** Commit `feat(shipping): quote responses carry structured issues`.

---

### Task 6: Let the admin correct the shipping address

**Files:**
- Modify: `routes/admin.php` (next to line 28), `app/Http/Controllers/Admin/OrdersController.php`
- Test: `tests/Feature/Shipping/ShippingAddressCorrectionTest.php`

Design notes: only while `$order->canShip()` (a shipped order's address is history). If the address row belongs to a user's address book (`user_id` set), copy it and repoint the order, so the customer's saved address is not edited behind their back; otherwise update in place. The change is logged as an `OrderEvent` note with the before/after values.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Models\Address;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->address = Address::factory()->create(['user_id' => null, 'postal_code' => '48105', 'city' => 'Cervia']);
    $this->order = Order::factory()->create([
        'status' => 'processing', 'payment_status' => 'succeeded', 'shipping_address_id' => $this->address->id,
    ]);
    $this->payload = [
        'first_name' => 'Ada', 'last_name' => 'Rossi', 'company' => null,
        'address_line_1' => 'Via Roma 1', 'address_line_2' => null,
        'city' => 'Cervia', 'state' => 'RA', 'postal_code' => '48015', 'country' => 'IT', 'phone' => '3331234567',
    ];
});

it('corrects the shipping address in place and logs the change', function () {
    $this->actingAs($this->admin)
        ->patch("/admin/orders/{$this->order->id}/shipping-address", $this->payload)
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($this->address->fresh()->postal_code)->toBe('48015');
    expect(OrderEvent::where('order_id', $this->order->id)->latest('id')->first()->payload['reason'])
        ->toContain('postal_code: 48105 → 48015');
});

it('copies an address-book address instead of editing the customer\'s saved one', function () {
    $customer = User::factory()->create();
    $this->address->update(['user_id' => $customer->id]);

    $this->actingAs($this->admin)->patch("/admin/orders/{$this->order->id}/shipping-address", $this->payload)->assertRedirect();

    expect($this->address->fresh()->postal_code)->toBe('48105')
        ->and($this->order->fresh()->shippingAddress->postal_code)->toBe('48015')
        ->and($this->order->fresh()->shipping_address_id)->not->toBe($this->address->id);
});

it('validates the address and refuses a shipped order', function () {
    $this->actingAs($this->admin)->patch("/admin/orders/{$this->order->id}/shipping-address", [...$this->payload, 'postal_code' => ''])
        ->assertSessionHasErrors('postal_code');

    $this->order->update(['status' => 'shipped']);
    $this->actingAs($this->admin)->patch("/admin/orders/{$this->order->id}/shipping-address", $this->payload)
        ->assertSessionHas('error');
    expect($this->address->fresh()->postal_code)->toBe('48105');
});
```

- [ ] **Step 2:** Run it. Expected: FAIL, 404 (route missing).

- [ ] **Step 3: Implement.** Route, inside the same admin group:

```php
Route::patch('orders/{order}/shipping-address', [OrdersController::class, 'updateShippingAddress'])->name('orders.update-shipping-address');
```

Controller method (next to `processShip`):

```php
/** Correct the recipient while the order can still ship (typo found when a carrier rejects the address). */
public function updateShippingAddress(Request $request, Order $order)
{
    if (! $order->canShip()) {
        return back()->with('error', "Order {$order->order_number} can no longer be edited: it is not waiting to ship.");
    }

    $data = $request->validate([
        'first_name' => 'required|string|max:100',
        'last_name' => 'required|string|max:100',
        'company' => 'nullable|string|max:100',
        'address_line_1' => 'required|string|max:255',
        'address_line_2' => 'nullable|string|max:255',
        'city' => 'required|string|max:100',
        'state' => 'required|string|max:50',
        'postal_code' => 'required|string|max:20',
        'country' => 'required|string|size:2',
        'phone' => 'nullable|string|max:50',
    ]);

    $address = $order->shippingAddress;
    $changes = collect($data)->filter(fn ($value, $key) => (string) $value !== (string) $address?->{$key})
        ->map(fn ($value, $key) => "{$key}: {$address?->{$key}} → {$value}");

    if ($changes->isEmpty()) {
        return back();
    }

    DB::transaction(function () use ($order, $address, $data, $changes, $request) {
        if ($address === null || $address->user_id !== null) {
            // never edit the customer's saved address behind their back: the order gets its own copy
            $copy = ($address?->replicate() ?? new Address)->fill([...$data, 'user_id' => null, 'is_default' => false, 'type' => 'shipping']);
            $copy->save();
            $order->update(['shipping_address_id' => $copy->id]);
        } else {
            $address->update($data);
        }

        $order->events()->create([
            'actor_id' => $request->user()->id,
            'type' => OrderEvent::TYPE_NOTE,
            'payload' => ['reason' => 'Shipping address corrected — '.$changes->implode('; '), 'meta' => ['source' => 'admin']],
        ]);
    });

    return back()->with('success', 'Shipping address updated.');
}
```

`Order::events()` exists (`app/Models/Order.php:209`). `addresses.type` is a NOT NULL enum: `replicate()` keeps it, but the `new Address` branch (an order with no address) needs `'type' => 'shipping'` in the `fill` array, so add it there. Import `Address` and `OrderEvent` in `OrdersController` if they are not already.

- [ ] **Step 4:** Run the new test file and `tests/Feature/Admin`. Expected: PASS.
- [ ] **Step 5:** Commit `feat(admin): correct an order's shipping address from the ship page`.

---

### Task 7: Ship page UI

**Files:**
- Create: `resources/js/components/admin/orders/ShippingIssues.tsx`
- Modify: `resources/js/pages/Admin/Orders/Ship.tsx`, `lang/{en,it,de,fr,es}.json`

No JS test runner is wired to this page, so verification is `npx tsc --noEmit`, `npm run build`, and the manual checks in Step 6. Keep the panel's logic in props so a future Vitest test is cheap.

- [ ] **Step 1: i18n keys.** Add to every `lang/*.json` next to the other `admin.orders.ship.*` keys (English shown; write real it/de/fr/es translations, `LocalesTest` and the i18n sweep expect parity):

```json
"admin.orders.ship.issues_title": "Fix these details to get rates",
"admin.orders.ship.issue_address": "The carrier rejected the address: {field} ({message}).",
"admin.orders.ship.issue_required": "{field} is required.",
"admin.orders.ship.issue_invalid": "{field} is not valid.",
"admin.orders.ship.issue_settings": "{provider}: sender or account details are wrong ({message}).",
"admin.orders.ship.issue_parcel": "{provider}: check the parcel weight and size ({message}).",
"admin.orders.ship.issue_unavailable": "{provider} is unavailable ({message}). Check its credentials in the shipping settings.",
"admin.orders.ship.issue_other": "{provider}: {message}",
"admin.orders.ship.fix_address": "Correct the address",
"admin.orders.ship.address_saved": "Address saved, getting new rates…",
"admin.orders.ship.save_address": "Save and retry",
"admin.orders.ship.field_phone": "Phone"
```

Field labels reuse `admin.orders.first_name`, `last_name`, `address_line_1`, `address_line_2`, `city`, `postal_code`, `state`, `country`.

- [ ] **Step 2: The component.** `ShippingIssues.tsx` exports `ShippingIssue` (`{provider, kind, field, code, message}`), the default component `ShippingIssues({ orderId, address, issues, onFixed })`:
  - Renders nothing when `issues` is empty.
  - One list item per issue, text from the matching `issue_*` key: `address` kind uses `issue_required` / `issue_invalid` for `code` `required` / `invalid` (else `issue_address`) with `field` = the label of `field.replace('recipient.', '')`; `settings` kind adds a `Link` to `/admin/settings/#shipping`; `unavailable` also links there; `parcel` renders a button calling a new `onEditParcels` prop (Ship.tsx passes `() => setParcelsOpen(true)`).
  - Provider `store` issues and carrier issues for the same `field` are deduplicated (keep the first).
  - Address issues show a **Correct the address** button opening a `Dialog` with a `useForm` seeded from `address` (`first_name … phone`), inputs for each field, `aria-invalid` and the issue text under every field named by an issue, and a **Save and retry** button doing `patch(\`/admin/orders/${orderId}/shipping-address\`, { preserveScroll: true, onSuccess: () => { setOpen(false); onFixed(); } })`. Server validation errors come back in `errors` and render under the matching input.

- [ ] **Step 3: Wire into `Ship.tsx`.** Add `issues: ShippingIssue[]` to `Rates` (initial `[]`, the error state `[]`), set it from `result.issues ?? []` in the `setRates({ status: 'ready', ... })` call, and render, just above the "providers unavailable" block:

```tsx
<ShippingIssues
    orderId={order.id}
    address={order.shippingAddress ?? null}
    issues={rates.issues}
    onEditParcels={() => setParcelsOpen(true)}
    onFixed={() => setRefreshCount((n) => n + 1)}
/>
```

Keep the collapsed "providers unavailable" list for the raw text, but show it only when `rates.issues` is empty (the panel supersedes it); drop the `noQuotes` auto-expand from the previous change in the same edit if the panel covers it.

- [ ] **Step 4:** `npx tsc --noEmit && npm run build`. Expected: no errors.
- [ ] **Step 5:** `./vendor/bin/pest tests/Feature/LocalesTest.php tests/Feature/Shipping`. Expected: PASS.
- [ ] **Step 6: Manual check** on an order whose address has postal code `48105` (Spediamo, once Task 0 is done): the panel lists "Postal Code is not valid", **Correct the address** opens the dialog with the field marked, saving to `48015` closes it and rates reload. Repeat with an empty phone (store issue, rates still load) and with a wrong Spediamo token (an `unavailable` issue with the settings link).
- [ ] **Step 7:** `graphify update .`, then commit `feat(admin): ship page explains rejected shipping details and lets you fix the address`.

---

## Self-review

- **Spec coverage:** explicit errors, in plain words, per field (Tasks 1-5, 7); admin can correct wrong customer info (Tasks 6-7); sender/credentials problems are routed to settings instead of the order (Task 7). Label-purchase errors and the standalone quotes page are named as out of scope.
- **Placeholders:** none; Task 0 is deliberately a verify-first step because the right `packages` format is unknown, and says what evidence decides it.
- **Types:** `ShippingIssue` fields (`provider, kind, field, code, message`) and the kinds (`address, settings, parcel, unavailable, other`) are the same in the PHP DTO, the `toArray()` JSON and the TS interface; `issues` is the key everywhere (`quoteAll`, `quoteOrder` JSON, `Rates.issues`).
- **Known risks:** `quoteAll()` gains a key (tests that assert the whole array shape need `issues`); Task 6's `Order::events()` name and `addresses.type` default must be confirmed in code before writing it; the Spediamo field map covers the fields seen so far, unknown fields land in `other` with the carrier's text, which is still shown.
