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

it('dvaexpress rate provider ignores 0.01 placeholder quote request entries like DVA and DVALM', function () {
    $quotationFixture = [
        'result' => 'OK',
        'errorcode' => 0,
        'quotation' => [
            [
                'codice_offerta' => 'dva-placeholder-1',
                'vettore' => 'DVA',
                'famiglia' => 'DVA',
                'nome_vettore' => 'QUOTAZIONE',
                'consegna' => 'E',
                'totale_dovuto' => '0.01',
                'messaggio_tariffa' => 'Da qui puoi richiedere un preventivo in tempo reale...',
            ],
            [
                'codice_offerta' => 'dvalm-placeholder-2',
                'vettore' => 'DVALM',
                'famiglia' => 'DVALM',
                'nome_vettore' => 'QUOTAZIONE - LAST MINUTE',
                'consegna' => 'E',
                'totale_dovuto' => '0.01',
                'messaggio_tariffa' => 'Da qui puoi richiedere un preventivo in tempo reale per una spedizione LAST MINUTE...',
            ],
            [
                'codice_offerta' => 'sda-real-quote',
                'vettore' => 'SDAM',
                'famiglia' => 'SDA',
                'nome_vettore' => 'SDA',
                'consegna' => 'E',
                'totale_dovuto' => '6.90',
                'importo_tariffa' => '10.62',
            ],
        ],
    ];

    Http::fake([
        'https://api.easyparcel.it/quotation/test-dva-rate-key*' => Http::response($quotationFixture, 200),
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
    $request = new RateRequest(
        sender: new RateAddress(postalCode: '20100', city: 'Milano', province: 'MI', country: 'IT'),
        recipient: new RateAddress(postalCode: '00100', city: 'Roma', province: 'RM', country: 'IT'),
        parcels: [new RateParcel(weightKg: 2.0)],
    );

    $quotes = $provider->quote($request);

    // Only real courier quote should be retained; 0.01 quote requests are filtered out
    expect($quotes)->toHaveCount(1)
        ->and($quotes[0]->carrierCode)->toBe('sda')
        ->and($quotes[0]->totalPrice)->toBe(6.90);
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
            && $request->isForm()
            && ($request['type'] ?? null) === 'DOMESTIC'
            && ($request['senderPostalCode'] ?? null) === '20100'
            && ($request['recipientPostalCode'] ?? null) === '00100'
            && ($request['packages'] ?? null) === '1:2.5:20:30:25';
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

it('spediamo rate provider parses responses with polymorphic object price representations', function () {
    $objectPriceFixture = [
        'id' => 'spd-draft-obj',
        'courier' => 'SDA',
        'type' => 'DOMESTIC',
        'basePrice' => ['value' => 5.56, 'localizedValue' => '5,56'],
        'vat' => ['value' => 1.22, 'localizedValue' => '1,22'],
        'netPrice' => ['value' => 6.12, 'localizedValue' => '6,12'],
        'totalPrice' => ['value' => 7.46, 'localizedValue' => '7,46'],
        'fuelSurcharge' => ['value' => 0.56, 'localizedValue' => '0,56'],
    ];

    Http::fake([
        'https://api.spediamo.it/v1/shipments*' => Http::response($objectPriceFixture, 200),
    ]);

    $config = [
        'base_url' => 'https://api.spediamo.it',
        'token' => 'test-spediamo-rate-token',
        'cleanup_quote_drafts' => false,
        'carrier_map' => [
            'sda' => 'SDA',
        ],
    ];

    $provider = new SpediamoRateProvider($config, new BearerTokenAuthenticator('test-spediamo-rate-token'));

    $rateRequest = new RateRequest(
        sender: new RateAddress(postalCode: '20100', city: 'Milano', province: 'MI', country: 'IT'),
        recipient: new RateAddress(postalCode: '00100', city: 'Roma', province: 'RM', country: 'IT'),
        parcels: [new RateParcel(weightKg: 1.0)],
    );

    $quotes = $provider->quote($rateRequest);

    expect($quotes)->toBeArray()->toHaveCount(1);
    $quote = $quotes[0];

    expect($quote->netPrice)->toBe(6.12)
        ->and($quote->totalPrice)->toBe(7.46)
        ->and($quote->listPrice)->toBe(5.56)
        ->and($quote->surcharges['fuel'])->toBe(0.56);
});

/*
|--------------------------------------------------------------------------
| 3. RateManager::quoteAll() aggregation, sorting, balances, fault isolation
|--------------------------------------------------------------------------
*/

it('rate manager quoteAll aggregates quotes, sorts by totalPrice ascending, collects balances and isolates driver failures', function () {
    $manager = app(RateManager::class);

    $manager->extend('provider_fast', fn () => new class implements RateProvider
    {
        public function name(): string
        {
            return 'provider_fast';
        }

        public function supports(string $carrierCode): bool
        {
            return true;
        }

        public function quote(RateRequest $request): array
        {
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

    $manager->extend('provider_economy', fn () => new class implements InspectsBalance, RateProvider
    {
        public function name(): string
        {
            return 'provider_economy';
        }

        public function supports(string $carrierCode): bool
        {
            return true;
        }

        public function quote(RateRequest $request): array
        {
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

        public function balance(): AccountBalance
        {
            return new AccountBalance(
                provider: 'provider_economy',
                balance: 180.25,
                currency: 'EUR',
            );
        }
    });

    $manager->extend('provider_failing', fn () => new class implements RateProvider
    {
        public function name(): string
        {
            return 'provider_failing';
        }

        public function supports(string $carrierCode): bool
        {
            return true;
        }

        public function quote(RateRequest $request): array
        {
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

it('POST /admin/orders/{order}/quotes marks a quote buyable only when its provider labels one of our carriers', function () {
    settings()->set('shipping.labels_enabled', true);
    // fake rates return sda and dhl; only sda is one of our carriers
    config(['shipping.carriers' => ['sda' => ['name' => 'SDA', 'tracking_url' => 'https://track.example.test/?n={number}']]]);
    $order = Order::factory()->create(['status' => 'processing', 'payment_status' => 'succeeded', 'shipping_amount' => 10.00]);

    $quotes = collect($this->actingAs($this->admin)
        ->postJson(route('admin.orders.quotes', $order), ['drivers' => ['fake']])
        ->assertOk()
        ->json('quotes'))->keyBy('carrier_code');

    expect($quotes['sda']['store_carrier_code'])->toBe('sda')
        ->and($quotes['dhl']['store_carrier_code'])->toBeNull();

    // labels off: nothing can be bought, the rates are still shown
    settings()->set('shipping.labels_enabled', false);
    $quotes = $this->actingAs($this->admin)->postJson(route('admin.orders.quotes', $order), ['drivers' => ['fake']])->json('quotes');
    expect(collect($quotes)->pluck('store_carrier_code')->filter())->toBeEmpty();
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
        'shipping.labels.sender.postal_code' => '00100',
        'shipping.labels.sender.city' => 'Roma',
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

    $response->assertRedirectContains(route('admin.orders.show', $order))
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

/*
|--------------------------------------------------------------------------
| 6. Provider Country-Aware Pre-Flight Checks & Carrier Aliasing
|--------------------------------------------------------------------------
*/

it('dvaexpress rate provider pads IT cap, defaults international province to --, and rejects US/CA without state', function () {
    $quotationFixture = json_decode(file_get_contents(base_path('tests/Fixtures/Shipping/dvaexpress/quotation.json')), true);

    Http::fake([
        'https://api.easyparcel.it/quotation/test-key*' => Http::response($quotationFixture, 200),
    ]);

    $config = [
        'base_url' => 'https://api.easyparcel.it',
        'api_key' => 'test-key',
        'carrier_map' => [
            'sda' => 'SDAM',
            'poste' => 'PDB',
            'poste_italiane' => 'PDB',
        ],
    ];
    $provider = new DvaexpressRateProvider($config);

    // 1. IT CAP padding (e.g. '100' -> '00100')
    $req = new RateRequest(
        sender: new RateAddress(postalCode: '100', city: 'Roma', province: 'RM', country: 'IT'),
        recipient: new RateAddress(postalCode: '9121', city: 'Cagliari', province: 'CA', country: 'IT'),
        parcels: [new RateParcel(weightKg: 1.0)],
    );
    $provider->quote($req);

    Http::assertSent(function ($request) {
        return ($request['mittente']['cap'] ?? null) === '00100'
            && ($request['destinatario']['cap'] ?? null) === '09121';
    });

    // 2. International destination defaults province to '--'
    $reqIntl = new RateRequest(
        sender: new RateAddress(postalCode: '00100', city: 'Roma', province: 'RM', country: 'IT'),
        recipient: new RateAddress(postalCode: '75001', city: 'Paris', province: '', country: 'FR'),
        parcels: [new RateParcel(weightKg: 1.0)],
    );
    $provider->quote($reqIntl);

    Http::assertSent(function ($request) {
        return ($request['destinatario']['provincia'] ?? null) === '--'
            && ($request['destinatario']['nazione'] ?? null) === 'FR';
    });

    // 3. US destination without province throws RateRejected
    $reqUsNoState = new RateRequest(
        sender: new RateAddress(postalCode: '00100', city: 'Roma', province: 'RM', country: 'IT'),
        recipient: new RateAddress(postalCode: '90210', city: 'Beverly Hills', province: '', country: 'US'),
        parcels: [new RateParcel(weightKg: 1.0)],
    );
    expect(fn () => $provider->quote($reqUsNoState))
        ->toThrow(\App\Shipping\Rates\Exceptions\RateRejected::class, 'Recipient province (state code) is required for US');
});

it('dvaexpress and spediamo filter quotes using areSameCourier aliases', function () {
    // 1. Dvaexpress: quote returned has vettore SDAM (mapped to sda). Requesting 'sda' matches it.
    $quotationFixture = [
        'result' => 'OK',
        'quotation' => [
            [
                'vettore' => 'PDB',
                'nome_vettore' => 'Poste Delivery Business',
                'consegna' => 'S',
                'totale_dovuto' => 7.50,
            ],
        ],
    ];

    Http::fake([
        'https://api.easyparcel.it/quotation/test-key*' => Http::response($quotationFixture, 200),
    ]);

    $dvaProvider = new DvaexpressRateProvider([
        'base_url' => 'https://api.easyparcel.it',
        'api_key' => 'test-key',
        'carrier_map' => [
            'poste' => 'PDB',
            'poste_italiane' => 'PDB',
        ],
    ]);

    // Requesting alias 'poste_italiane' should match quote having PDB/poste via areSameCourier
    $quotes = $dvaProvider->quote(new RateRequest(
        sender: new RateAddress(postalCode: '00100', city: 'Roma', province: 'RM', country: 'IT'),
        recipient: new RateAddress(postalCode: '20100', city: 'Milano', province: 'MI', country: 'IT'),
        parcels: [new RateParcel(weightKg: 1.0)],
        carrierCode: 'poste_italiane',
    ));

    expect($quotes)->toHaveCount(1)
        ->and($quotes[0]->carrierCode)->toBe('poste_italiane');

    // 2. Spediamo: shipment draft returns courier SDA. Requesting alias 'poste_italiane' matches it.
    $draftFixture = json_decode(file_get_contents(base_path('tests/Fixtures/Shipping/spediamo/shipment_draft.json')), true);
    Http::fake([
        'https://api.spediamo.it/v1/shipments*' => Http::response($draftFixture, 200),
    ]);

    $spediamoProvider = new SpediamoRateProvider([
        'base_url' => 'https://api.spediamo.it',
        'token' => 'test-token',
        'cleanup_quote_drafts' => false,
        'carrier_map' => [
            'sda' => 'SDA',
            'poste_italiane' => 'SDA',
        ],
    ], new BearerTokenAuthenticator('test-token'));

    $spedQuotes = $spediamoProvider->quote(new RateRequest(
        sender: new RateAddress(postalCode: '100', city: 'Roma', province: 'RM', country: 'IT'),
        recipient: new RateAddress(postalCode: '20100', city: 'Milano', province: 'MI', country: 'IT'),
        parcels: [new RateParcel(weightKg: 1.0)],
        carrierCode: 'poste_italiane',
    ));

    expect($spedQuotes)->toHaveCount(1)
        ->and($spedQuotes[0]->carrierCode)->toBe('poste_italiane');
});

it('spediamo formats validation errors without numeric index prefix', function () {
    Http::fake([
        'https://api.spediamo.it/v1/shipments' => Http::response([
            'errors' => [
                '0' => '0: Il campo senderTown è obbligatorio.',
                'senderPostalCode' => ['Il CAP non è valido.'],
            ],
        ], 422),
    ]);

    $provider = new SpediamoRateProvider([
        'base_url' => 'https://api.spediamo.it',
        'token' => 'test-token',
    ], new BearerTokenAuthenticator('test-token'));

    try {
        $provider->quote(new RateRequest(
            sender: new RateAddress(postalCode: '00100', city: 'Roma', province: 'RM', country: 'IT'),
            recipient: new RateAddress(postalCode: '20100', city: 'Milano', province: 'MI', country: 'IT'),
            parcels: [new RateParcel(weightKg: 1.0)],
        ));
        test()->fail('Expected RateRejected was not thrown');
    } catch (\App\Shipping\Rates\Exceptions\RateRejected $e) {
        expect($e->getMessage())->not->toContain('0:')
            ->and($e->getMessage())->toContain('Il campo senderTown è obbligatorio')
            ->and($e->getMessage())->toContain('senderPostalCode: Il CAP non è valido.');
    }
});

it('rate manager standardizes sender cascading and pads Italian postal codes', function () {
    $manager = app(RateManager::class);

    // Baseline with store profile fallback
    settings()->set('store.name', 'My Boutique');
    settings()->set('store.address', 'Via Dante 10');
    settings()->set('contact.phone', '+39 070 123456');
    settings()->set('contact.support_email', 'info@boutique.test');
    settings()->set('shipping.default_country', 'IT');
    settings()->set('shipping.sender_postal_code', '9121');
    settings()->set('shipping.sender_city', 'Cagliari');

    $sender = $manager->senderFromSettings();
    expect($sender->name)->toBe('My Boutique')
        ->and($sender->street)->toBe('Via Dante 10')
        ->and($sender->phone)->toBe('+39 070 123456')
        ->and($sender->email)->toBe('info@boutique.test')
        ->and($sender->country)->toBe('IT')
        ->and($sender->postalCode)->toBe('09121')
        ->and($sender->city)->toBe('Cagliari');
});
