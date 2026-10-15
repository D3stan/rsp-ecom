<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Shipping\Support\TrackingFixtures as Fx;

beforeEach(function () {
    Queue::fake();
    $this->admin = User::factory()->create(['role' => 'admin']);
    config(['shipping.carriers' => [
        'acme' => ['name' => 'Acme Courier', 'tracking_url' => 'https://track.example.test/?n={number}'],
        'zeta' => ['name' => 'Zeta Express'],
    ]]);
    $this->order = Order::factory()->create(['user_id' => null, 'guest_email' => 'g@example.test', 'payment_status' => 'succeeded', 'status' => 'processing']);
});

function trkShipBody(array $extra = []): array
{
    return array_merge(['tracking_carrier' => 'acme', 'tracking_number' => 'TRK1'], $extra);
}

it('stores the chosen carrier when shipping the order', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), trkShipBody(['tracking_carrier' => 'acme']))
        ->assertSessionHas('success');

    expect($this->order->fresh())->status->toBe('shipped')->tracking_carrier->toBe('acme');
});

it('requires a carrier when shipping', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), trkShipBody(['tracking_carrier' => null]))
        ->assertSessionHasErrors('tracking_carrier');

    expect($this->order->fresh()->status)->toBe('processing');
});

it('rejects an unconfigured carrier when shipping', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), trkShipBody(['tracking_carrier' => 'made-up']))
        ->assertSessionHasErrors('tracking_carrier');

    expect($this->order->fresh()->status)->toBe('processing');
});

it('rejects any carrier when none is configured', function () {
    config(['shipping.carriers' => []]);

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), trkShipBody(['tracking_carrier' => 'acme']))
        ->assertSessionHasErrors('tracking_carrier');
});

it('saves, changes and clears the carrier from the edit form, and leaves it alone when not sent', function () {
    $url = route('admin.orders.update', $this->order);

    // the edit form stores tracking data; it never ships the order (the ship form does)
    $this->actingAs($this->admin)->put($url, ['tracking_number' => 'TRK9', 'tracking_carrier' => 'acme'])->assertSessionHas('success');
    expect($this->order->fresh())->tracking_carrier->toBe('acme')->tracking_number->toBe('TRK9')->status->toBe('processing');

    $this->actingAs($this->admin)->put($url, ['notes' => 'only notes']);
    expect($this->order->fresh()->tracking_carrier)->toBe('acme');

    $this->actingAs($this->admin)->put($url, ['tracking_carrier' => 'zeta']);
    expect($this->order->fresh()->tracking_carrier)->toBe('zeta');

    $this->actingAs($this->admin)->put($url, ['tracking_carrier' => '']);   // "other / unknown"
    expect($this->order->fresh()->tracking_carrier)->toBeNull();
});

it('does not save an unconfigured carrier from the edit form', function () {
    $this->actingAs($this->admin)->put(route('admin.orders.update', $this->order), ['tracking_carrier' => 'made-up'])
        ->assertSessionHas('error');

    expect($this->order->fresh()->tracking_carrier)->toBeNull();
});

it('feeds the carrier list to the ship, edit and show pages', function () {
    foreach (['admin.orders.show-ship', 'admin.orders.edit', 'admin.orders.show'] as $route) {
        $this->actingAs($this->admin)->get(route($route, $this->order))->assertInertia(fn (Assert $page) => $page
            ->where('carriers', [['code' => 'acme', 'name' => 'Acme Courier'], ['code' => 'zeta', 'name' => 'Zeta Express']]));
    }
});

it('shows carrier, tracking status and the latest events on the show page', function () {
    $order = Fx::shippedOrder(['tracking_carrier' => 'acme']);
    app(\App\Shipping\Tracking\ShipmentTrackingService::class)->apply($order, 'fake', Fx::result(
        \App\Shipping\Tracking\Enums\TrackingStatus::InTransit,
        Fx::event(\App\Shipping\Tracking\Enums\TrackingStatus::InTransit, '-1 hour', 'e1', 'Departed hub'),
    ));

    $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertInertia(fn (Assert $page) => $page
        ->where('order.tracking_carrier', 'acme')
        ->where('order.tracking_status', 'in_transit')
        ->has('order.tracking_events', 1)
        ->where('order.tracking_events.0.description', 'Departed hub'));
});

it('uses the chosen carrier for the link in the shipped email', function () {
    $order = Fx::shippedOrder(['tracking_number' => 'TRK-55', 'tracking_carrier' => 'acme']);

    $html = (new \App\Mail\OrderShipped($order, 'TRK-55'))->render();

    expect($html)->toContain('https://track.example.test/?n=TRK-55')->toContain('Acme Courier');
});
