<?php

use App\Jobs\SendOrderEmail;
use App\Models\Order;
use App\Models\OrderEmail;
use App\Models\Shipment;
use App\Models\User;
use App\Shipping\ShippingService;
use App\Shipping\Support\NextWorkingDay;
use App\Shipping\Tracking\Enums\TrackingStatus as S;
use App\Shipping\Tracking\ShipmentTrackingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Shipping\Support\TrackingFixtures as Fx;

/*
 * Buying a label ships the order at once but holds the customer's "shipped" email back until the carrier first scans
 * the parcel (tracking in_transit or out_for_delivery). Manual shipping (the admin types the number) still mails at once.
 */

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');
    $this->admin = User::factory()->create(['role' => 'admin']);
    config(['shipping.carriers' => ['acme' => ['name' => 'Acme Courier', 'tracking_url' => 'https://track.example.test/?n={number}']]]);
    settings()->set('shipping.labels_enabled', true);
    settings()->set('shipping.label_driver', 'fake');

    $this->order = Order::factory()->create([
        'user_id' => null, 'guest_email' => 'guest@example.test', 'status' => 'processing',
        'payment_status' => 'succeeded', 'tracking_number' => null, 'tracking_carrier' => null, 'locale' => 'en',
    ]);
    $this->order->orderItems()->create(['product_name' => 'Widget', 'quantity' => 1, 'price' => 10, 'total' => 10]);
});

function buyLabel(): Order
{
    test()->actingAs(test()->admin)->post(route('admin.orders.process-ship', test()->order), [
        'tracking_carrier' => 'acme', 'create_label' => true, 'parcels' => [['weight' => 1.5]],
        'pickup_date' => NextWorkingDay::after(CarbonImmutable::now())->toDateString(),
    ])->assertSessionHas('success')->assertSessionMissing('warning');

    return test()->order->fresh();
}

function carrierSays(Order $order, S $status): void
{
    app(ShipmentTrackingService::class)->apply($order, 'fake', Fx::result($status, Fx::event($status, '-1 hour', 'e-'.$status->value)));
}

function shippedEmailJobs(): int
{
    return OrderEmail::where('type', OrderEmail::TYPE_SHIPPED)->count();
}

it('ships the order on a label purchase without emailing the customer, and keeps the flag', function () {
    $order = buyLabel();

    expect($order->status)->toBe('shipped')
        ->and($order->shipments()->first()->notify_on_pickup)->toBeTrue()
        ->and(shippedEmailJobs())->toBe(0);
    Queue::assertNothingPushed();
});

it('still emails at once when the admin ships manually', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), ['tracking_carrier' => 'acme', 'tracking_number' => 'TYPED-1'])
        ->assertSessionHas('success');

    expect(shippedEmailJobs())->toBe(1)
        ->and($this->order->shipments()->first()->notify_on_pickup)->toBeFalse();
    Queue::assertPushed(SendOrderEmail::class, 1);
});

it('sends exactly one shipped email with the current tracking number on the first in-transit scan', function () {
    $order = buyLabel();

    carrierSays($order, S::InTransit);

    Queue::assertPushed(SendOrderEmail::class, 1);
    Queue::assertPushed(SendOrderEmail::class, fn ($job) => $job->type === OrderEmail::TYPE_SHIPPED && $job->trackingNumber === $order->tracking_number);
    expect($order->shipments()->first()->notify_on_pickup)->toBeFalse();

    carrierSays($order->fresh(), S::InTransit);
    carrierSays($order->fresh(), S::OutForDelivery);

    Queue::assertPushed(SendOrderEmail::class, 1);
});

it('also sends it when out_for_delivery is the first status seen', function () {
    $order = buyLabel();

    carrierSays($order, S::OutForDelivery);

    Queue::assertPushed(SendOrderEmail::class, 1);
    expect($order->shipments()->first()->notify_on_pickup)->toBeFalse();
});

it('sends nothing for info_received or exception and keeps the flag', function (S $status) {
    $order = buyLabel();

    carrierSays($order, $status);

    Queue::assertNothingPushed();
    expect($order->shipments()->first()->notify_on_pickup)->toBeTrue();
})->with([S::InfoReceived, S::Exception]);

it('sends no shipped email when the first status ever seen is delivered, and clears the flag', function () {
    $order = buyLabel();

    carrierSays($order, S::Delivered);

    expect(shippedEmailJobs())->toBe(0)
        ->and($order->shipments()->first()->notify_on_pickup)->toBeFalse()
        ->and($order->fresh()->status)->toBe('delivered');
});

it('does not mail the customer early when the admin edits the tracking number of a pending label', function () {
    $order = buyLabel();
    $order->tracking_number = 'EDITED-1';
    $order->save();

    expect(app(ShippingService::class)->trackingChanged($order))->toBeTrue();

    expect(shippedEmailJobs())->toBe(0);
    Queue::assertNothingPushed();
});

it('keeps the flag when the email cannot be queued, retries on the next sync, and the sync still succeeds', function () {
    $order = buyLabel();
    $order->update(['guest_email' => null]);

    carrierSays($order->fresh(), S::InTransit);

    expect($order->shipments()->first()->notify_on_pickup)->toBeTrue()
        ->and($order->fresh()->tracking_status)->toBe('in_transit');

    $order->update(['guest_email' => 'guest@example.test']);
    carrierSays($order->fresh(), S::InTransit);

    Queue::assertPushed(SendOrderEmail::class, 1);
    expect($order->shipments()->first()->notify_on_pickup)->toBeFalse();
});

it('sends nothing for a shipment that carries no pending flag', function () {
    $order = buyLabel();
    $order->shipments()->first()->update(['notify_on_pickup' => false]);

    carrierSays($order, S::InTransit);

    Queue::assertNothingPushed();
    expect(Shipment::where('order_id', $order->id)->count())->toBe(1);
});
