<?php

use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Shipping\Labels\LabelManager;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * G3: optional label creation through the modular carrier layer. Off by default: with labels off no label provider is
 * resolved at all and the admin ships by typing carrier + tracking (G2). On: the active LabelProvider creates the
 * shipment, the PDF is stored on the private disk, the tracking number comes from the carrier, the order ships and one
 * email with tracking goes out. The admin can download the PDF (customers cannot) and cancel the shipment.
 */

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');
    $this->admin = User::factory()->create(['role' => 'admin']);
    config(['shipping.carriers' => ['acme' => ['name' => 'Acme Courier', 'tracking_url' => 'https://track.example.test/?n={number}']]]);
    // labels on, fake driver (deterministic, no network)
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

function labelShipBody(array $extra = []): array
{
    return array_merge([
        'tracking_carrier' => 'acme',
        'create_label' => true,
        'parcels' => [['weight' => 1.5]],
    ], $extra);
}

it('resolves no label provider at all when labels are off', function () {
    settings()->set('shipping.labels_enabled', false);

    // the manager must not be touched while labels are off
    $this->mock(LabelManager::class, function ($mock) {
        $mock->shouldNotReceive('driver');
    });

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), [
        'tracking_carrier' => 'acme',
        'tracking_number' => 'TYPED-1',
    ])->assertSessionHas('success');

    $order = $this->order->fresh();
    expect($order->status)->toBe('shipped')
        ->and($order->tracking_number)->toBe('TYPED-1')
        ->and($order->shipments()->first()->driver)->toBe(Shipment::DRIVER_MANUAL);
});

it('creates a label: shipment row, stored PDF, carrier tracking number, shipped and one email', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), labelShipBody())
        ->assertRedirect(route('admin.orders.show', $this->order))
        ->assertSessionHas('success');

    $order = $this->order->fresh();
    expect($order->status)->toBe('shipped')
        ->and($order->tracking_carrier)->toBe('acme')
        ->and($order->tracking_number)->not->toBeNull();

    $shipment = $order->shipments()->first();
    expect($shipment->driver)->toBe('fake')
        ->and($shipment->external_id)->not->toBeNull()
        ->and($shipment->tracking_number)->toBe($order->tracking_number)
        ->and($shipment->label_path)->not->toBeNull();

    Storage::disk('local')->assertExists($shipment->label_path);
    expect(Storage::disk('local')->get($shipment->label_path))->toStartWith('%PDF');

    Queue::assertPushed(\App\Jobs\SendOrderEmail::class, 1);
});

it('shows the carriers message on a carrier rejection and does not change the order', function () {
    // the fake driver rejects a parcel over its weight limit
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), labelShipBody([
        'parcels' => [['weight' => 999]],
    ]))->assertSessionHasErrors('label');

    expect($this->order->fresh()->status)->toBe('processing')
        ->and(Shipment::where('order_id', $this->order->id)->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('flashes an error and changes nothing when the provider is unavailable', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), labelShipBody([
        'parcels' => [['weight' => 0.001]], // the fake driver treats a near-zero weight as "provider down"
    ]))->assertSessionHas('error');

    expect($this->order->fresh()->status)->toBe('processing')
        ->and(Shipment::where('order_id', $this->order->id)->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('streams the label PDF to the admin and 403s a customer', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), labelShipBody());
    $shipment = $this->order->fresh()->shipments()->first();

    $adminDownload = $this->actingAs($this->admin)->get(route('admin.orders.shipments.label', [$this->order, $shipment]));
    $adminDownload->assertOk();
    expect($adminDownload->headers->get('content-type'))->toContain('application/pdf');

    $customer = User::factory()->create(['role' => 'customer']);
    $this->actingAs($customer)->get(route('admin.orders.shipments.label', [$this->order, $shipment]))->assertForbidden();
});

it('cancels a shipment through a driver that supports it, without changing the order status', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), labelShipBody());
    $shipment = $this->order->fresh()->shipments()->first();

    $this->actingAs($this->admin)->delete(route('admin.orders.shipments.cancel', [$this->order, $shipment]))
        ->assertSessionHas('success');

    expect($shipment->fresh()->status)->toBe(Shipment::STATUS_CANCELLED)
        ->and($this->order->fresh()->status)->toBe('shipped'); // the admin then uses "undo ship"
});

it('gives the ship page the labels flag, driver and prefilled parcels', function () {
    $this->actingAs($this->admin)->get(route('admin.orders.show-ship', $this->order))
        ->assertInertia(fn (Assert $page) => $page
            ->where('labels_enabled', true)
            ->has('parcels')
            ->where('carriers.0.code', 'acme'));
});

it('does not resolve a provider when labels are off even if create_label is sent', function () {
    settings()->set('shipping.labels_enabled', false);

    // create_label is ignored: it falls back to the manual path, which needs a tracking number
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), labelShipBody())
        ->assertSessionHasErrors('tracking_number');

    expect($this->order->fresh()->status)->toBe('processing');
});
