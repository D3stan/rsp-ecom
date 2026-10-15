<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * G1 (F-05): an order may ship only when it is "processing" AND its payment was captured and not fully refunded
 * (succeeded or partially_refunded). The lifecycle map alone allows processing -> shipped for an order the admin moved
 * to processing by hand while unpaid, or one that was refunded in full: the server must refuse those on every path.
 */

beforeEach(function () {
    Queue::fake();
    $this->admin = User::factory()->create(['role' => 'admin']);
    config(['shipping.carriers' => ['acme' => ['name' => 'Acme Courier']]]);
});

function shipGuardOrder(string $status, string $paymentStatus): Order
{
    return Order::factory()->create([
        'status' => $status,
        'payment_status' => $paymentStatus,
        'user_id' => null,
        'guest_email' => 'buyer@example.test',
        'tracking_number' => null,
    ]);
}

function shipGuardForm(): array
{
    return ['tracking_carrier' => 'acme', 'tracking_number' => 'TRK1'];
}

dataset('payments that may not ship', ['pending', 'processing', 'failed', 'refunded']);

it('refuses the ship form for a processing order whose payment is not captured', function (string $payment) {
    $order = shipGuardOrder('processing', $payment);

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $order), shipGuardForm())
        ->assertSessionHas('error');

    expect($order->fresh()->status)->toBe('processing')
        ->and($order->events()->where('to_status', 'shipped')->exists())->toBeFalse();
})->with('payments that may not ship');

it('does not open the ship page for an order that may not ship', function (string $payment) {
    $order = shipGuardOrder('processing', $payment);

    $this->actingAs($this->admin)->get(route('admin.orders.show-ship', $order))
        ->assertRedirect(route('admin.orders.show', $order))
        ->assertSessionHas('error');
})->with('payments that may not ship');

it('refuses to ship an unpaid processing order through the status endpoint and the edit form', function () {
    $order = shipGuardOrder('processing', 'pending');

    $this->actingAs($this->admin)->patch(route('admin.orders.update-status', $order), ['status' => 'shipped'])
        ->assertSessionHas('error');
    $this->actingAs($this->admin)->put(route('admin.orders.update', $order), ['status' => 'shipped'])
        ->assertSessionHas('error');

    expect($order->fresh()->status)->toBe('processing');
    Queue::assertNothingPushed();
});

it('does not auto-ship an unpaid processing order when a tracking number is added', function () {
    $order = shipGuardOrder('processing', 'pending');

    $this->actingAs($this->admin)->put(route('admin.orders.update', $order), ['tracking_number' => 'TRK1']);

    expect($order->fresh()->status)->toBe('processing');
});

it('ships a paid or partially refunded processing order', function (string $payment) {
    $order = shipGuardOrder('processing', $payment);

    $this->actingAs($this->admin)->get(route('admin.orders.show-ship', $order))->assertOk();
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $order), shipGuardForm())
        ->assertSessionHas('success');

    expect($order->fresh()->status)->toBe('shipped');
})->with(['succeeded', 'partially_refunded']);

it('tells the admin pages which orders may ship', function () {
    $paid = shipGuardOrder('processing', 'succeeded');
    $unpaid = shipGuardOrder('processing', 'pending');
    $pending = shipGuardOrder('pending', 'pending');

    $this->actingAs($this->admin)->get(route('admin.orders.index'))
        ->assertInertia(function (Assert $page) use ($paid, $unpaid, $pending) {
            $canShip = collect($page->toArray()['props']['orders']['data'])->pluck('can_ship', 'id')->all();

            expect($canShip)->toHaveCount(3)
                ->and($canShip[$paid->id] ?? null)->toBeTrue()
                ->and($canShip[$unpaid->id] ?? null)->toBeFalse()
                ->and($canShip[$pending->id] ?? null)->toBeFalse();
        });

    $this->actingAs($this->admin)->get(route('admin.orders.show', $paid))
        ->assertInertia(fn (Assert $page) => $page->where('can_ship', true)->where('allowed_transitions', ['shipped', 'cancelled']));
    // the status actions offer only what the server accepts: no "shipped" for the unpaid order
    $this->actingAs($this->admin)->get(route('admin.orders.show', $unpaid))
        ->assertInertia(fn (Assert $page) => $page->where('can_ship', false)->where('allowed_transitions', ['cancelled']));
});
