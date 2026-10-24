<?php

use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Shipping\Providers\Spediamo\SpediamoLabelProvider;
use App\Shipping\Support\NextWorkingDay;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
 * Spediamo has no operation that pays a draft: createShipment() leaves a DRAFT the owner pays on Spediamo, and the order
 * page then asks whether it is paid (ConfirmsPayment).
 *
 * WHAT IS RECORDED AND WHAT IS NOT. The DRAFT response is recorded from the live API (tests/Fixtures/spediamo/probes/
 * accept_pickup_date_iso.json). The response of a PAID shipment has never been observed: the paid-path tests below are
 * built from Spediamo's documented statuses (draft = not bought, booked = bought, ready = scheduled for pickup,
 * current, on_hold, underpaid, failed) and from the field names the draft response shows (id, status, trackingNumber).
 * Re-record this once a shipment has really been paid.
 */

function spediamoDraftBody(): array
{
    return json_decode(file_get_contents(__DIR__.'/../../Fixtures/spediamo/probes/accept_pickup_date_iso.json'), true)['response'];
}

function spediamoLabelProviderForTest(): SpediamoLabelProvider
{
    return new SpediamoLabelProvider(['base_url' => 'https://api.spediamo.test', 'token' => 't', 'purchase_url' => 'https://pay.example.test/draft']);
}

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');
    $this->admin = User::factory()->create(['role' => 'admin']);

    config([
        'shipping.carriers' => ['sda' => ['name' => 'SDA', 'tracking_url' => 'https://track.example.test/?n={number}']],
        'shipping.providers.spediamo.base_url' => 'https://api.spediamo.test',
        'shipping.providers.spediamo.token' => 'test-token',
        'shipping.providers.spediamo.carrier_map' => ['sda' => 'SDA'],
        'shipping.labels.sender' => ['name' => 'Shop', 'street' => 'Via Pola 2', 'city' => 'Cesena', 'postal_code' => '47522', 'province' => 'FC', 'country' => 'IT'],
    ]);
    settings()->set('shipping.labels_enabled', true);
    settings()->set('shipping.label_driver', 'spediamo');

    $this->order = Order::factory()->create([
        'user_id' => null, 'guest_email' => 'guest@example.test', 'status' => 'processing',
        'payment_status' => 'succeeded', 'tracking_number' => null, 'tracking_carrier' => null,
    ]);
    $this->order->orderItems()->create(['product_name' => 'Widget', 'quantity' => 1, 'price' => 10, 'total' => 10]);
    $this->order->shippingAddress->update(['address_line_1' => 'Via Roma 1', 'city' => 'Cervia', 'postal_code' => '48015', 'state' => 'RA', 'country' => 'IT']);
});

function awaitingShipment(Order $order): Shipment
{
    $order->forceFill(['status' => 'shipped', 'tracking_number' => 'shp_draft_1', 'tracking_carrier' => 'sda'])->saveQuietly();

    return $order->shipments()->create([
        'carrier_code' => 'sda', 'driver' => 'spediamo', 'external_id' => 'shp_draft_1', 'tracking_number' => 'shp_draft_1',
        'status' => Shipment::STATUS_AWAITING_PAYMENT, 'request_payload' => ['pay_url' => 'https://pay.example.test/draft'],
    ]);
}

it('keeps a created draft as awaiting_payment: no label, order shipped, no waybill requested', function () {
    Http::fake(['api.spediamo.test/v1/shipments' => Http::response(['id' => 'shp_draft_1', 'status' => 'DRAFT', 'courier' => 'SDA', 'waybillAvailable' => false], 201)]);

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), [
        'tracking_carrier' => 'sda', 'create_label' => true, 'parcels' => [['weight' => 1.5]],
        'pickup_date' => NextWorkingDay::after(CarbonImmutable::now())->toDateString(),
    ])->assertSessionHas('success');

    $shipment = $this->order->fresh()->shipments()->first();
    expect($shipment->status)->toBe(Shipment::STATUS_AWAITING_PAYMENT)
        ->and($shipment->label_path)->toBeNull()
        ->and($shipment->external_id)->toBe('shp_draft_1')
        ->and($shipment->request_payload['pay_url'])->toBe(config('shipping.providers.spediamo.purchase_url'))
        ->and($this->order->fresh()->status)->toBe('shipped');

    Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), '/v1/waybills'));
});

it('exposes the pay link and draft code of an awaiting shipment to the order page', function () {
    awaitingShipment($this->order);

    $this->actingAs($this->admin)->get(route('admin.orders.show', $this->order))
        ->assertInertia(fn ($page) => $page
            ->where('shipments.0.status', 'awaiting_payment')
            ->where('shipments.0.pay_url', 'https://pay.example.test/draft')
            ->where('shipments.0.draft_code', 'shp_draft_1')
            ->where('shipments.0.provider_name', 'Spediamo')
            ->where('shipments.0.can_cancel', true));
});

it('reports a DRAFT, UNDERPAID, FAILED or unknown status as not paid', function (string $status) {
    $body = array_merge(spediamoDraftBody(), ['status' => $status]);
    Http::fake(['api.spediamo.test/v1/shipments/shp_draft_1' => Http::response($body)]);

    expect(spediamoLabelProviderForTest()->confirmPurchase('shp_draft_1'))->toBeNull();
})->with(['DRAFT', 'UNDERPAID', 'FAILED', 'SOMETHING_NEW', '']);

it('reports the recorded live draft body as not paid', function () {
    Http::fake(['api.spediamo.test/v1/shipments/shp_draft_1' => Http::response(spediamoDraftBody())]);

    expect(spediamoLabelProviderForTest()->confirmPurchase('shp_draft_1'))->toBeNull();
});

it('returns the shipment with its courier tracking number once it is BOOKED (shape assumed from the docs)', function (string $status) {
    Http::fake(['api.spediamo.test/v1/shipments/shp_draft_1' => Http::response(['id' => 'shp_draft_1', 'status' => $status, 'trackingNumber' => 'COURIER-77', 'courier' => 'SDA'])]);

    $result = spediamoLabelProviderForTest()->confirmPurchase('shp_draft_1');

    expect($result->externalId)->toBe('shp_draft_1')
        ->and($result->trackingNumber)->toBe('COURIER-77')
        ->and($result->awaitingPayment)->toBeFalse();
})->with(['BOOKED', 'READY', 'CURRENT', 'ON_HOLD']);

it('leaves the shipment unchanged and warns while the draft is still unpaid', function () {
    $shipment = awaitingShipment($this->order);
    Http::fake(['api.spediamo.test/v1/shipments/shp_draft_1' => Http::response(spediamoDraftBody())]);

    $this->actingAs($this->admin)->post(route('admin.orders.shipments.complete', [$this->order, $shipment]))
        ->assertSessionHas('warning');

    expect($shipment->fresh()->status)->toBe(Shipment::STATUS_AWAITING_PAYMENT)
        ->and($shipment->fresh()->label_path)->toBeNull()
        ->and($this->order->fresh()->tracking_number)->toBe('shp_draft_1');
});

it('fetches and stores the label once the shipment is paid', function () {
    $shipment = awaitingShipment($this->order);
    Http::fake([
        'api.spediamo.test/v1/shipments/shp_draft_1' => Http::response(['id' => 'shp_draft_1', 'status' => 'BOOKED', 'trackingNumber' => 'COURIER-77', 'courier' => 'SDA']),
        'api.spediamo.test/v1/waybills/shp_draft_1/pdf*' => Http::response("%PDF-1.4\n%%EOF", 200, ['Content-Type' => 'application/pdf']),
    ]);

    $this->actingAs($this->admin)->post(route('admin.orders.shipments.complete', [$this->order, $shipment]))
        ->assertSessionHas('success');

    $shipment = $shipment->fresh();
    expect($shipment->status)->toBe(Shipment::STATUS_CREATED)
        ->and($shipment->tracking_number)->toBe('COURIER-77')
        ->and($shipment->label_path)->not->toBeNull()
        ->and($this->order->fresh()->tracking_number)->toBe('COURIER-77')
        ->and($this->order->fresh()->status)->toBe('shipped');

    expect(Storage::disk('local')->get($shipment->label_path))->toStartWith('%PDF');
});

it('refuses to check a shipment that is not awaiting payment', function () {
    $shipment = awaitingShipment($this->order);
    $shipment->update(['status' => Shipment::STATUS_CREATED]);

    $this->actingAs($this->admin)->post(route('admin.orders.shipments.complete', [$this->order, $shipment]))
        ->assertSessionHas('error');
});

it('cancels a draft at the carrier and moves the order back to processing', function () {
    $shipment = awaitingShipment($this->order);
    Http::fake(['api.spediamo.test/v1/shipments/shp_draft_1' => Http::response('', 204)]);

    $this->actingAs($this->admin)->delete(route('admin.orders.shipments.cancel', [$this->order, $shipment]))
        ->assertSessionHas('success');

    expect($shipment->fresh()->status)->toBe(Shipment::STATUS_CANCELLED)
        ->and($this->order->fresh()->status)->toBe('processing');
});

it('forbids a customer from checking a payment', function () {
    $shipment = awaitingShipment($this->order);

    $this->actingAs(User::factory()->create(['role' => 'customer']))
        ->post(route('admin.orders.shipments.complete', [$this->order, $shipment]))
        ->assertForbidden();
});

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
