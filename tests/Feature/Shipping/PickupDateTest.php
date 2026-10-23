<?php

use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Shipping\Auth\BearerTokenAuthenticator;
use App\Shipping\Labels\Drivers\FakeLabelProvider;
use App\Shipping\Labels\DTO\Address;
use App\Shipping\Labels\DTO\Parcel;
use App\Shipping\Labels\DTO\ShipmentRequest;
use App\Shipping\Labels\DTO\ShipmentResult;
use App\Shipping\Labels\LabelManager;
use App\Shipping\Providers\Dvaexpress\DvaexpressLabelRequestMapper;
use App\Shipping\Providers\Spediamo\SpediamoLabelRequestMapper;
use App\Shipping\Providers\Spediamo\SpediamoRateProvider;
use App\Shipping\Rates\DTO\RateAddress;
use App\Shipping\Rates\DTO\RateParcel;
use App\Shipping\Rates\DTO\RateRequest;
use App\Shipping\Rates\RateManager;
use App\Shipping\Support\NextWorkingDay;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');
    // a Friday: the next working day is Monday 2026-10-26
    $this->travelTo(CarbonImmutable::parse('2026-10-23 10:00:00'));

    $this->admin = User::factory()->create(['role' => 'admin']);
    config(['shipping.carriers' => ['acme' => ['name' => 'Acme Courier', 'tracking_url' => 'https://track.example.test/?n={number}']]]);

    // a fake driver that remembers what it was asked to create
    $this->spy = new class extends FakeLabelProvider
    {
        public ?ShipmentRequest $request = null;

        public function createShipment(ShipmentRequest $request): ShipmentResult
        {
            $this->request = $request;

            return parent::createShipment($request);
        }
    };
    $spy = $this->spy; // a driver closure is bound to the manager, not to the test
    app(LabelManager::class)->extend('spy', fn () => $spy);

    settings()->set('shipping.labels_enabled', true);
    settings()->set('shipping.label_driver', 'spy');

    $this->order = Order::factory()->create([
        'user_id' => null, 'guest_email' => 'guest@example.test', 'status' => 'processing',
        'payment_status' => 'succeeded', 'tracking_number' => null, 'tracking_carrier' => null,
    ]);
    $this->order->orderItems()->create(['product_name' => 'Widget', 'quantity' => 1, 'price' => 10, 'total' => 10]);
});

function shipWithPickup(array $extra = []): array
{
    return array_merge(['tracking_carrier' => 'acme', 'create_label' => true, 'parcels' => [['weight' => 1.5]]], $extra);
}

it('stores the pickup date, the deferred-email flag and the awaiting_payment status on a shipment', function () {
    $shipment = Order::factory()->create()->shipments()->create([
        'carrier_code' => 'pdb', 'driver' => 'spediamo', 'status' => Shipment::STATUS_AWAITING_PAYMENT,
        'pickup_date' => '2026-10-26', 'notify_on_pickup' => true,
    ]);

    expect($shipment->fresh()->pickup_date->toDateString())->toBe('2026-10-26')
        ->and($shipment->fresh()->notify_on_pickup)->toBeTrue()
        ->and($shipment->fresh()->status)->toBe('awaiting_payment');
});

it('requires a pickup date to buy a label', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), shipWithPickup())
        ->assertSessionHasErrors('pickup_date');

    expect($this->spy->request)->toBeNull();
});

it('refuses a pickup date that is not a future working day', function (string $date) {
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), shipWithPickup(['pickup_date' => $date]))
        ->assertSessionHasErrors('pickup_date');

    expect($this->spy->request)->toBeNull()
        ->and($this->order->fresh()->status)->toBe('processing');
})->with([
    'saturday' => '2026-10-24',
    'sunday' => '2026-10-25',
    'national holiday' => '2026-11-01',
    'today' => '2026-10-23',
    'past' => '2026-10-20',
    'malformed' => '26/10/2026',
]);

it('passes the chosen working day to the label driver and stores it on the shipment', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), shipWithPickup(['pickup_date' => '2026-10-27']))
        ->assertSessionHasNoErrors();

    expect($this->spy->request->pickupDate)->toBe('2026-10-27')
        ->and($this->order->shipments()->first()->pickup_date->toDateString())->toBe('2026-10-27');
});

it('does not ask for a pickup date when shipping manually', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), [
        'tracking_carrier' => 'acme', 'tracking_number' => 'TYPED-1',
    ])->assertSessionHasNoErrors();

    expect($this->order->shipments()->first()->pickup_date)->toBeNull();
});

it('defaults the ship page to the next working day', function () {
    $this->actingAs($this->admin)->get(route('admin.orders.show-ship', $this->order))
        ->assertInertia(fn (Assert $page) => $page
            ->where('default_pickup_date', NextWorkingDay::after(CarbonImmutable::now())->toDateString()));
});

// Providers: the chosen date reaches each carrier's own request format

function pickupShipmentRequest(?string $pickupDate): ShipmentRequest
{
    return new ShipmentRequest(
        carrierCode: 'sda',
        sender: new Address('Mittente', 'Via Pola 2', 'Cesena', '47522', 'FC', 'IT', '3331234567', 'shop@example.test'),
        recipient: new Address('Destinatario', 'Via Roma 1', 'Cervia', '48015', 'RA', 'IT'),
        parcels: [new Parcel(weightKg: 1.0, lengthCm: 35, widthCm: 25, heightCm: 8)],
        reference: 'ORD-1',
        pickupDate: $pickupDate,
    );
}

it('sends requestedPickupDate on the Spediamo shipment only when a date was chosen', function () {
    $mapper = new SpediamoLabelRequestMapper;

    expect($mapper->map(pickupShipmentRequest('2026-10-26')))->toHaveKey('requestedPickupDate', '2026-10-26')
        ->and($mapper->map(pickupShipmentRequest(null)))->not->toHaveKey('requestedPickupDate');
});

it('sends the chosen pickup date with the Spediamo quote and still reads the recorded live answer', function () {
    $probe = json_decode(file_get_contents(__DIR__.'/../../Fixtures/spediamo/probes/accept_pickup_date_iso.json'), true);
    Http::fake(['api.spediamo.it/v1/shipments' => Http::response($probe['response'], $probe['status'])]);

    $quotes = (new SpediamoRateProvider(['base_url' => 'https://api.spediamo.it', 'cleanup_quote_drafts' => false], new BearerTokenAuthenticator('t')))->quote(new RateRequest(
        sender: new RateAddress('47522', 'Cesena', 'FC', 'IT', 'Test Sender', 'Via Pola 2', '3331234567', 'sender@example.test'),
        recipient: new RateAddress('48015', 'Cervia', 'RA', 'IT', 'Test Customer', 'Via Roma 1'),
        parcels: [new RateParcel(weightKg: 1, lengthCm: 35, widthCm: 25, heightCm: 8)],
        pickupDate: '2026-10-26',
    ));

    Http::assertSent(fn (HttpRequest $request) => ($request->data()['requestedPickupDate'] ?? null) === '2026-10-26');
    expect($quotes)->toHaveCount(1)->and($quotes[0]->estimatedDelivery)->toBe('2026-10-29');
});

it('leaves requestedPickupDate out of the Spediamo quote without a date', function () {
    Http::fake(['api.spediamo.it/v1/shipments' => Http::response(['id' => 'x', 'courier' => 'PDB', 'totalPrice' => ['value' => 1]], 201)]);

    (new SpediamoRateProvider(['base_url' => 'https://api.spediamo.it', 'cleanup_quote_drafts' => false], new BearerTokenAuthenticator('t')))->quote(new RateRequest(
        sender: new RateAddress('47522', 'Cesena', 'FC', 'IT', 'Test Sender', 'Via Pola 2'),
        recipient: new RateAddress('48015', 'Cervia', 'RA', 'IT', 'Test Customer', 'Via Roma 1'),
        parcels: [new RateParcel(weightKg: 1)],
    ));

    Http::assertSent(fn (HttpRequest $request) => ! array_key_exists('requestedPickupDate', $request->data()));
});

it('uses the chosen pickup date as Dvaexpress disponibile_dal, else the previous default', function () {
    $mapper = new DvaexpressLabelRequestMapper(['carrier_map' => ['sda' => 'SDAM']]);

    $chosen = $mapper->mapOrderFromQuote(pickupShipmentRequest('2026-10-27'), 'token');
    $default = $mapper->mapOrderFromQuote(pickupShipmentRequest(null), 'token');

    expect($chosen['ritiro']['dettagli']['disponibile_dal'])->toBe('2026-10-27')
        // the Friday frozen in beforeEach: next weekday is Monday
        ->and($default['ritiro']['dettagli']['disponibile_dal'])->toBe('2026-10-26');
});

it('lets the order quote endpoint take a pickup date, refusing one the carrier cannot collect on', function () {
    $this->actingAs($this->admin)->postJson(route('admin.orders.quotes', $this->order), ['pickup_date' => '2026-10-24'])
        ->assertJsonValidationErrors('pickup_date');

    expect(app(RateManager::class)->requestFromOrder($this->order, [], null, null, null, null, '2026-10-26')->pickupDate)->toBe('2026-10-26');
});
