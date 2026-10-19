<?php

use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Shipping\Auth\BearerTokenAuthenticator;
use App\Shipping\Providers\Dvaexpress\DvaexpressRateProvider;
use App\Shipping\Providers\Spediamo\SpediamoRateProvider;
use App\Shipping\Rates\Contracts\InspectsBalance;
use App\Shipping\Rates\Contracts\RateProvider;
use App\Shipping\Rates\DTO\AccountBalance;
use App\Shipping\Rates\DTO\RateAddress;
use App\Shipping\Rates\DTO\RateParcel;
use App\Shipping\Rates\DTO\RateQuote;
use App\Shipping\Rates\DTO\RateRequest;
use App\Shipping\Rates\Exceptions\RateProviderUnavailable;
use App\Shipping\Rates\RateManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');
    $this->admin = User::factory()->create(['role' => 'admin']);
});

/*
|--------------------------------------------------------------------------
| 1. DvaexpressRateProvider: quote() and balance()
|--------------------------------------------------------------------------
*/

it('dvaexpress rate provider parses quotation.json into RateQuote DTOs and balance.json into AccountBalance', function () {
    $quotationFixture = json_decode(file_get_contents(base_path('tests/Fixtures/Shipping/dvaexpress/quotation.json')), true);
    $balanceFixture = json_decode(file_get_contents(base_path('tests/Fixtures/Shipping/dvaexpress/balance.json')), true);

    Http::fake([
        'https://api.easyparcel.it/quotation/test-dva-rate-key*' => Http::response($quotationFixture, 200),
        'https://api.easyparcel.it/balance/test-dva-rate-key*' => Http::response($balanceFixture, 200),
    ]);

    $config = [
        'base_url' => 'https://api.easyparcel.it',
        'api_key' => 'test-dva-rate-key',
        'carrier_map' => [
            'sda' => 'SDAM',
            'brt' => 'BRT',
        ],
    ];

    $provider = new DvaexpressRateProvider($config);

    $rateRequest = new RateRequest(
        sender: new RateAddress(postalCode: '20100', city: 'Milano', province: 'MI', country: 'IT'),
        recipient: new RateAddress(postalCode: '00100', city: 'Roma', province: 'RM', country: 'IT'),
        parcels: [new RateParcel(weightKg: 2.0, lengthCm: 30, widthCm: 20, heightCm: 15)],
    );

    // 1. Test quote()
    $quotes = $provider->quote($rateRequest);

    expect($quotes)->toBeArray()->toHaveCount(2);

    $sdaQuote = $quotes[0];
    expect($sdaQuote)->toBeInstanceOf(RateQuote::class)
        ->and($sdaQuote->provider)->toBe('dvaexpress')
        ->and($sdaQuote->carrierCode)->toBe('sda')
        ->and($sdaQuote->carrierName)->toBe('SDA')
        ->and($sdaQuote->quoteToken)->toBe('5dcc4a53b00fc')
        ->and($sdaQuote->totalPrice)->toBe(6.90)
        ->and($sdaQuote->listPrice)->toBe(10.62)
        ->and($sdaQuote->taxableWeightKg)->toBe(3.0)
        ->and($sdaQuote->currency)->toBe('EUR');

    $brtQuote = $quotes[1];
    expect($brtQuote)->toBeInstanceOf(RateQuote::class)
        ->and($brtQuote->provider)->toBe('dvaexpress')
        ->and($brtQuote->carrierCode)->toBe('brt')
        ->and($brtQuote->carrierName)->toBe('BRT')
        ->and($brtQuote->quoteToken)->toBe('5dcc4a53b00fd')
        ->and($brtQuote->totalPrice)->toBe(8.50)
        ->and($brtQuote->listPrice)->toBe(12.50)
        ->and($brtQuote->taxableWeightKg)->toBe(3.0);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/quotation/test-dva-rate-key')
            && ($request['call'] ?? null) === 'quotation'
            && ($request['mittente']['cap'] ?? null) === '20100'
            && ($request['destinatario']['cap'] ?? null) === '00100';
    });

    // 2. Test carrier code filtering
    $filteredRequest = new RateRequest(
        sender: new RateAddress(postalCode: '20100', city: 'Milano', province: 'MI', country: 'IT'),
        recipient: new RateAddress(postalCode: '00100', city: 'Roma', province: 'RM', country: 'IT'),
        parcels: [new RateParcel(weightKg: 2.0)],
        carrierCode: 'sda',
    );
    $filteredQuotes = $provider->quote($filteredRequest);
    expect($filteredQuotes)->toHaveCount(1)
        ->and($filteredQuotes[0]->carrierCode)->toBe('sda');

    // 3. Test balance()
    $balance = $provider->balance();
    expect($balance)->toBeInstanceOf(AccountBalance::class)
        ->and($balance->provider)->toBe('dvaexpress')
        ->and($balance->balance)->toBe(245.50)
        ->and($balance->currency)->toBe('EUR');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/balance/test-dva-rate-key')
            && ($request['call'] ?? null) === 'balance';
    });
});

/*
|--------------------------------------------------------------------------
| 2. SpediamoRateProvider: quote() and auto-cleanup of draft
|--------------------------------------------------------------------------
*/

it('spediamo rate provider parses shipment_draft.json into RateQuote DTO and auto-cleans draft via DELETE', function () {
    $draftFixture = json_decode(file_get_contents(base_path('tests/Fixtures/Shipping/spediamo/shipment_draft.json')), true);

    Http::fake([
        'https://api.spediamo.it/v1/shipments/spd-draft-901' => Http::response(['success' => true], 200),
        'https://api.spediamo.it/v1/shipments' => Http::response($draftFixture, 200),
    ]);

    $config = [
        'base_url' => 'https://api.spediamo.it',
        'token' => 'test-spediamo-rate-token',
        'cleanup_quote_drafts' => true,
        'carrier_map' => [
            'sda' => 'SDA',
        ],
    ];

    $provider = new SpediamoRateProvider($config, new BearerTokenAuthenticator('test-spediamo-rate-token'));

    $rateRequest = new RateRequest(
        sender: new RateAddress(
            postalCode: '20100',
            city: 'Milano',
            province: 'MI',
            country: 'IT',
            name: 'Negozio Online',
            street: 'Via Roma 1',
            phone: '021234567',
            email: 'shop@example.com',
        ),
        recipient: new RateAddress(
            postalCode: '00100',
            city: 'Roma',
            province: 'RM',
            country: 'IT',
            name: 'Mario Rossi',
            street: 'Via Verdi 2',
            phone: '061234567',
            email: 'mario.rossi@example.com',
        ),
        parcels: [new RateParcel(weightKg: 2.5, lengthCm: 30, widthCm: 25, heightCm: 20)],
    );

    $quotes = $provider->quote($rateRequest);

    expect($quotes)->toBeArray()->toHaveCount(1);
    $quote = $quotes[0];

    expect($quote)->toBeInstanceOf(RateQuote::class)
        ->and($quote->provider)->toBe('spediamo')
        ->and($quote->carrierCode)->toBe('sda')
        ->and($quote->carrierName)->toBe('SDA')
        ->and($quote->serviceName)->toBe('DOMESTIC')
        ->and($quote->netPrice)->toBe(6.05)
        ->and($quote->totalPrice)->toBe(7.38)
        ->and($quote->listPrice)->toBe(5.50)
        ->and($quote->taxableWeightKg)->toBe(3.0)
        ->and($quote->dimensionalWeightApplied)->toBeTrue()
        ->and($quote->estimatedDelivery)->toBe('2026-10-21')
        ->and($quote->surcharges)->toBe(['fuel' => 0.55])
        ->and($quote->quoteToken)->toBeNull(); // null because draft was deleted

    // Verify draft creation payload was sent
    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.spediamo.it/v1/shipments'
            && $request->method() === 'POST'
            && ($request['senderPostalCode'] ?? null) === '20100'
            && ($request['recipientPostalCode'] ?? null) === '00100'
            && ($request['packages'] ?? null) === '2.5x30x25x20';
    });

    // Verify DELETE was triggered to clean up the temporary draft
    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.spediamo.it/v1/shipments/spd-draft-901'
            && $request->method() === 'DELETE';
    });
});

it('spediamo rate provider preserves quoteToken when cleanup_quote_drafts is false', function () {
    $draftFixture = json_decode(file_get_contents(base_path('tests/Fixtures/Shipping/spediamo/shipment_draft.json')), true);

    Http::fake([
        'https://api.spediamo.it/v1/shipments' => Http::response($draftFixture, 200),
    ]);

    $config = [
        'base_url' => 'https://api.spediamo.it',
        'token' => 'test-spediamo-rate-token',
        'cleanup_quote_drafts' => false,
    ];

    $provider = new SpediamoRateProvider($config, new BearerTokenAuthenticator('test-spediamo-rate-token'));

    $rateRequest = new RateRequest(
        sender: new RateAddress(postalCode: '20100', city: 'Milano', province: 'MI', country: 'IT'),
        recipient: new RateAddress(postalCode: '00100', city: 'Roma', province: 'RM', country: 'IT'),
        parcels: [new RateParcel(weightKg: 1.0)],
    );

    $quotes = $provider->quote($rateRequest);

    expect($quotes[0]->quoteToken)->toBe('spd-draft-901');

    Http::assertNotSent(function ($request) {
        return $request->method() === 'DELETE';
    });
});

/*
|--------------------------------------------------------------------------
| 3. RateManager::quoteAll() aggregation, sorting, balances, fault isolation
|--------------------------------------------------------------------------
*/

it('rate manager quoteAll aggregates quotes, sorts by totalPrice ascending, collects balances and isolates driver failures', function () {
    $manager = app(RateManager::class);

    $manager->extend('provider_fast', fn () => new class implements RateProvider {
        public function name(): string { return 'provider_fast'; }
        public function supports(string $carrierCode): bool { return true; }
        public function quote(RateRequest $request): array {
            return [
                new RateQuote(
                    provider: 'provider_fast',
                    carrierCode: 'fast',
                    carrierName: 'Fast Courier',
                    rawCarrierCode: 'FAST',
                    serviceName: 'Express',
                    netPrice: 12.00,
                    totalPrice: 14.64,
                ),
            ];
        }
    });

    $manager->extend('provider_economy', fn () => new class implements InspectsBalance, RateProvider {
        public function name(): string { return 'provider_economy'; }
        public function supports(string $carrierCode): bool { return true; }
        public function quote(RateRequest $request): array {
            return [
                new RateQuote(
                    provider: 'provider_economy',
                    carrierCode: 'eco',
                    carrierName: 'Economy Post',
                    rawCarrierCode: 'ECO',
                    serviceName: 'Standard',
                    netPrice: 5.00,
                    totalPrice: 6.10,
                ),
            ];
        }
        public function balance(): AccountBalance {
            return new AccountBalance(
                provider: 'provider_economy',
                balance: 180.25,
                currency: 'EUR',
            );
        }
    });

    $manager->extend('provider_failing', fn () => new class implements RateProvider {
        public function name(): string { return 'provider_failing'; }
        public function supports(string $carrierCode): bool { return true; }
        public function quote(RateRequest $request): array {
            throw new RateProviderUnavailable('Provider API endpoint unreachable.');
        }
    });

    $request = new RateRequest(
        sender: new RateAddress(postalCode: '20100', city: 'Milano', province: 'MI', country: 'IT'),
        recipient: new RateAddress(postalCode: '00100', city: 'Roma', province: 'RM', country: 'IT'),
        parcels: [new RateParcel(weightKg: 2.0)],
    );

    $result = $manager->quoteAll($request, ['provider_fast', 'provider_economy', 'provider_failing']);

    // 1. Fault isolation: provider_failing does not prevent fast and economy from returning
    expect($result['errors'])->toHaveKey('provider_failing')
        ->and($result['errors']['provider_failing'])->toContain('Provider API endpoint unreachable.');

    // 2. Aggregation & sorting: quotes are sorted ascending by totalPrice
    expect($result['quotes'])->toHaveCount(2);
    expect($result['quotes'][0]->totalPrice)->toBe(6.10)
        ->and($result['quotes'][0]->provider)->toBe('provider_economy');
    expect($result['quotes'][1]->totalPrice)->toBe(14.64)
        ->and($result['quotes'][1]->provider)->toBe('provider_fast');

    // 3. Balances collected from InspectsBalance
    expect($result['balances'])->toHaveKey('provider_economy')
        ->and($result['balances']['provider_economy']['balance'])->toBe(180.25)
        ->and($result['balances']['provider_economy']['currency'])->toBe('EUR')
        ->and($result['balances'])->not->toHaveKey('provider_fast');
});

/*
|--------------------------------------------------------------------------
| 4. Admin ShippingQuotesController endpoints
|--------------------------------------------------------------------------
*/

it('GET /admin/shipping/quotes renders Admin/Shipping/Quotes Inertia page for admin and blocks unauthorized users', function () {
    // 1. Unauthenticated user redirected
    $this->get(route('admin.shipping.quotes'))
        ->assertRedirect(route('login'));

    // 2. Regular user receives 403
    $regularUser = User::factory()->create(['role' => 'customer']);
    $this->actingAs($regularUser)
        ->get(route('admin.shipping.quotes'))
        ->assertForbidden();

    // 3. Admin user renders Inertia view with required props
    $this->actingAs($this->admin)
        ->get(route('admin.shipping.quotes'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Shipping/Quotes')
            ->has('sender')
            ->has('carriers')
            ->has('rate_drivers')
            ->has('default_parcel_weight_kg')
        );
});

it('POST /admin/shipping/quotes/calculate validates input and returns sorted quotes, errors, and balances', function () {
    $payload = [
        'sender' => [
            'postal_code' => '20100',
            'city' => 'Milano',
            'province' => 'MI',
            'country' => 'IT',
            'name' => 'Store Srl',
        ],
        'recipient' => [
            'postal_code' => '00100',
            'city' => 'Roma',
            'province' => 'RM',
            'country' => 'IT',
            'name' => 'Mario Rossi',
        ],
        'parcels' => [
            ['weight' => 2.5, 'length' => 30, 'width' => 20, 'height' => 15, 'quantity' => 1],
        ],
        'drivers' => ['fake'],
    ];

    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.shipping.quotes.calculate'), $payload);

    $response->assertOk()
        ->assertJsonStructure([
            'quotes' => [
                '*' => [
                    'provider',
                    'carrier_code',
                    'carrier_name',
                    'service_name',
                    'net_price',
                    'total_price',
                    'currency',
                ],
            ],
            'errors',
            'balances',
        ]);

    $data = $response->json();
    expect($data['quotes'])->toBeArray()->not->toBeEmpty();
    // fake driver returns SDA (6.20) and DHL (8.50) sorted by totalPrice ascending
    expect($data['quotes'][0]['total_price'])->toBeLessThanOrEqual($data['quotes'][1]['total_price']);
    expect($data['balances'])->toHaveKey('fake');
});

it('POST /admin/orders/{order}/quotes calculates quotes and computes margin_delta', function () {
    $order = Order::factory()->create([
        'user_id' => null,
        'guest_email' => 'customer@example.test',
        'status' => 'processing',
        'payment_status' => 'succeeded',
        'shipping_amount' => 10.00,
    ]);
    $order->orderItems()->create([
        'product_name' => 'Gadget',
        'quantity' => 1,
        'price' => 50,
        'total' => 50,
    ]);
    $order->shippingAddress->update([
        'first_name' => 'Luigi',
        'last_name' => 'Verdi',
        'address_line_1' => 'Via Garibaldi 10',
        'city' => 'Torino',
        'postal_code' => '10100',
        'state' => 'TO',
        'country' => 'IT',
        'phone' => '0112345678',
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.orders.quotes', $order), [
            'drivers' => ['fake'],
        ]);

    $response->assertOk()
        ->assertJsonStructure([
            'quotes',
            'errors',
            'balances',
            'customer_shipping_cost',
        ]);

    $data = $response->json();
    expect((float) $data['customer_shipping_cost'])->toEqual(10.0);

    // Each quote must have margin_delta = round($order->shipping_cost - $quote['total_price'], 2)
    foreach ($data['quotes'] as $quote) {
        expect($quote)->toHaveKey('margin_delta');
        $expectedMargin = round(10.00 - (float) $quote['total_price'], 2);
        expect((float) $quote['margin_delta'])->toEqual($expectedMargin);
    }
});

/*
|--------------------------------------------------------------------------
| 5. End-to-end quote-to-label purchase handoff
|--------------------------------------------------------------------------
*/

it('completes end-to-end quote-to-label purchase handoff via POST /admin/orders/{order}/ship', function () {
    settings()->set('shipping.labels_enabled', true);
    settings()->set('shipping.label_driver', 'dvaexpress');

    config([
        'shipping.carriers.sda' => [
            'name' => 'SDA Express',
            'tracking_url' => 'https://track.example.test/?n={number}',
        ],
        'shipping.providers.dvaexpress.api_key' => 'dva-handoff-key',
        'shipping.providers.dvaexpress.base_url' => 'https://api.easyparcel.it',
        'shipping.providers.dvaexpress.carrier_map' => [
            'sda' => 'SDAM',
        ],
    ]);

    $orderFixture = json_decode(file_get_contents(base_path('tests/Fixtures/Shipping/dvaexpress/order.json')), true);
    $waybillFixture = json_decode(file_get_contents(base_path('tests/Fixtures/Shipping/dvaexpress/getwaybill.json')), true);
    $waybillFixture['waybill_number'] = '2875561502819';

    Http::fake([
        'https://api.easyparcel.it/order/dva-handoff-key*' => Http::response($orderFixture, 200),
        'https://api.easyparcel.it/getwaybill/dva-handoff-key*' => Http::response($waybillFixture, 200),
    ]);

    $order = Order::factory()->create([
        'user_id' => null,
        'guest_email' => 'buyer@example.test',
        'status' => 'processing',
        'payment_status' => 'succeeded',
        'shipping_amount' => 12.00,
        'tracking_number' => null,
        'tracking_carrier' => null,
    ]);
    $order->orderItems()->create([
        'product_name' => 'Pro Camera',
        'quantity' => 1,
        'price' => 300,
        'total' => 300,
    ]);
    $order->shippingAddress->update([
        'first_name' => 'Anna',
        'last_name' => 'Bianchi',
        'address_line_1' => 'Corso Vittorio 15',
        'city' => 'Napoli',
        'postal_code' => '80100',
        'state' => 'NA',
        'country' => 'IT',
        'phone' => '0812345678',
    ]);

    $shipPayload = [
        'create_label' => true,
        'label_driver' => 'dvaexpress',
        'tracking_carrier' => 'sda',
        'quote_token' => '5dcc4a53b00fc',
        'parcels' => [
            ['weight' => 2.5, 'length' => 30, 'width' => 20, 'height' => 15],
        ],
    ];

    $response = $this->actingAs($this->admin)
        ->post(route('admin.orders.process-ship', $order), $shipPayload);

    $response->assertRedirect(route('admin.orders.show', $order))
        ->assertSessionHas('success');

    // 1. Order transitioned to shipped with tracking number & carrier
    $order->refresh();
    expect($order->status)->toBe('shipped')
        ->and($order->tracking_carrier)->toBe('sda')
        ->and($order->tracking_number)->toBe('2875561502819');

    // 2. Shipment recorded with driver 'dvaexpress'
    $shipment = $order->shipments()->first();
    expect($shipment)->not->toBeNull()
        ->and($shipment->driver)->toBe('dvaexpress')
        ->and($shipment->carrier_code)->toBe('sda')
        ->and($shipment->tracking_number)->toBe('2875561502819')
        ->and($shipment->external_id)->toBe('160854')
        ->and($shipment->status)->toBe(Shipment::STATUS_CREATED)
        ->and($shipment->label_path)->not->toBeNull();

    // 3. Label PDF stored on private disk
    Storage::disk('local')->assertExists($shipment->label_path);
    $pdfContents = Storage::disk('local')->get($shipment->label_path);
    expect($pdfContents)->toStartWith('%PDF');

    // 4. Verify HTTP calls: POST /order/{APIKEY} passing codice_offerta
    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/order/dva-handoff-key')) {
            return false;
        }
        $data = $request->data();

        return ($data['call'] ?? null) === 'order'
            && ($data['dettagli']['codice_offerta'] ?? null) === '5dcc4a53b00fc';
    });

    // 5. Verify HTTP calls: POST /getwaybill/{APIKEY}
    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/getwaybill/dva-handoff-key')) {
            return false;
        }
        $data = $request->data();

        return ($data['call'] ?? null) === 'getwaybill'
            && (string) ($data['details']['order_id'] ?? '') === '160854'
            && ($data['details']['waybill_base64'] ?? null) === 'Y';
    });
});
