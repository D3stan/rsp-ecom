<?php

use App\Models\Address;
use App\Models\Order;
use App\Models\ShipmentTrackingEvent;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * G4 (H7): customers can see their shipment tracking on the account and guest order pages: a "Track with {carrier}"
 * link, the carrier status, the last update time and a compact, customer-safe event list. The payloads are the
 * WS-A whitelists (extended here), never raw carrier codes or the stored raw payload.
 */

beforeEach(function () {
    config(['shipping.carriers' => ['acme' => ['name' => 'Acme Courier', 'tracking_url' => 'https://track.example.test/?n={number}']]]);
});

function trackedOrder(?User $user): Order
{
    $order = Order::factory()->create([
        'user_id' => $user?->id,
        'guest_email' => $user ? null : 'guest@example.test',
        'guest_session_id' => $user ? null : 'sess-guest',
        'status' => 'shipped',
        'payment_status' => 'succeeded',
        'tracking_number' => 'TRK-PUBLIC-9',
        'tracking_carrier' => 'acme',
        'tracking_status' => 'in_transit',
        'tracking_last_synced_at' => now()->subHour(),
        'shipping_address_id' => Address::factory()->shipping()->create(['user_id' => null])->id,
    ]);
    $order->orderItems()->create(['product_name' => 'Widget', 'quantity' => 1, 'price' => 10, 'total' => 10]);

    ShipmentTrackingEvent::create([
        'order_id' => $order->id,
        'provider' => 'spediamo',
        'tracking_number' => 'TRK-PUBLIC-9',
        'status' => 'in_transit',
        'description' => 'In transit to the hub',
        'location' => 'Milano',
        'occurred_at' => now()->subHours(2),
        'event_hash' => 'hash-secret-1',
        'raw' => ['courier_status' => 'RAW-CARRIER-CODE', 'secret' => 'RAW-SECRET'],
    ]);

    return $order;
}

it('gives the My Orders page the customer-facing tracking fields and a safe event list', function () {
    $user = User::factory()->create();
    $order = trackedOrder($user);

    $response = $this->actingAs($user)->get(route('orders.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('orders.0.tracking_number', 'TRK-PUBLIC-9')
        ->where('orders.0.tracking_status', 'in_transit')
        ->where('orders.0.carrier_name', 'Acme Courier')
        ->where('orders.0.tracking_url', 'https://track.example.test/?n=TRK-PUBLIC-9')
        ->has('orders.0.tracking_last_synced_at')
        ->has('orders.0.tracking_events', 1)
        ->where('orders.0.tracking_events.0.status', 'in_transit')
        ->where('orders.0.tracking_events.0.description', 'In transit to the hub')
        ->where('orders.0.tracking_events.0.location', 'Milano')
        ->has('orders.0.tracking_events.0.occurred_at'));

    $props = json_encode($response->viewData('page')['props']);
    expect($props)->not->toContain('RAW-CARRIER-CODE')->not->toContain('RAW-SECRET')
        ->not->toContain('hash-secret-1')->not->toContain('spediamo');
});

it('gives the account dashboard recent orders the tracking fields', function () {
    $user = User::factory()->create();
    trackedOrder($user);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recentOrders.0.carrier_name', 'Acme Courier')
            ->where('recentOrders.0.tracking_url', 'https://track.example.test/?n=TRK-PUBLIC-9')
            ->where('recentOrders.0.tracking_status', 'in_transit')
            ->has('recentOrders.0.tracking_events', 1));
});

it('gives the signed guest order page the tracking fields and a safe event list', function () {
    $order = trackedOrder(null);

    $response = $this->get($order->publicUrl());

    $response->assertInertia(fn (Assert $page) => $page
        ->where('order.tracking_number', 'TRK-PUBLIC-9')
        ->where('order.carrier_name', 'Acme Courier')
        ->where('order.tracking_url', 'https://track.example.test/?n=TRK-PUBLIC-9')
        ->where('order.tracking_status', 'in_transit')
        ->has('order.tracking_last_synced_at')
        ->has('order.tracking_events', 1)
        ->where('order.tracking_events.0.location', 'Milano'));

    $props = json_encode($response->viewData('page')['props']);
    expect($props)->not->toContain('RAW-CARRIER-CODE')->not->toContain('hash-secret-1')->not->toContain('spediamo');
});

it('returns no tracking link or events for an order without tracking', function () {
    $user = User::factory()->create();
    Order::factory()->create([
        'user_id' => $user->id,
        'status' => 'processing',
        'payment_status' => 'succeeded',
        'tracking_number' => null,
        'tracking_carrier' => null,
    ]);

    $this->actingAs($user)->get(route('orders.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('orders.0.tracking_url', null)
            ->where('orders.0.carrier_name', null)
            ->has('orders.0.tracking_events', 0));
});
