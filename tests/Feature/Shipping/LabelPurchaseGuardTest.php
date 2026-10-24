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
