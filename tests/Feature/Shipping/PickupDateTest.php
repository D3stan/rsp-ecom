<?php

use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Shipping\Labels\Drivers\FakeLabelProvider;
use App\Shipping\Labels\DTO\ShipmentRequest;
use App\Shipping\Labels\DTO\ShipmentResult;
use App\Shipping\Labels\LabelManager;
use App\Shipping\Support\NextWorkingDay;
use Carbon\CarbonImmutable;
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
