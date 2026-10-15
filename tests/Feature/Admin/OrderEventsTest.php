<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
    $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Giulia Admin']);
});

function eventsOrder(string $status): Order
{
    return Order::factory()->create(['status' => $status, 'payment_status' => 'succeeded', 'user_id' => null, 'guest_email' => 'buyer@example.test']);
}

it('records one status_changed event with the actor, both statuses and the reason', function () {
    $order = eventsOrder('shipped');

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.update-status', $order), ['status' => 'processing', 'reason' => 'Parcel came back'])
        ->assertSessionHas('success');

    expect(Schema::hasTable('order_events'))->toBeTrue('there is no order_events audit table');

    $events = DB::table('order_events')->where('order_id', $order->id)->get();
    expect($events)->toHaveCount(1);

    $event = $events->first();
    expect($event->type)->toBe('status_changed')
        ->and($event->from_status)->toBe('shipped')
        ->and($event->to_status)->toBe('processing')
        ->and((int) $event->actor_id)->toBe($this->admin->id)
        ->and(json_decode($event->payload, true)['reason'])->toBe('Parcel came back')
        ->and($event->created_at)->not->toBeNull();
});

it('records system transitions without an actor, keeping their source', function () {
    $order = eventsOrder('shipped');

    $order->transitionTo('delivered', null, null, ['source' => 'tracking', 'provider' => 'fake']);

    expect(Schema::hasTable('order_events'))->toBeTrue('there is no order_events audit table');

    $event = DB::table('order_events')->where('order_id', $order->id)->sole();
    expect($event->actor_id)->toBeNull()
        ->and($event->to_status)->toBe('delivered')
        ->and(json_decode($event->payload, true)['meta'])->toBe(['source' => 'tracking', 'provider' => 'fake']);
});

it('records nothing for a refused move or a move to the current status', function () {
    $order = eventsOrder('delivered');

    $this->actingAs($this->admin)->patch(route('admin.orders.update-status', $order), ['status' => 'cancelled']);
    $this->actingAs($this->admin)->patch(route('admin.orders.update-status', $order), ['status' => 'delivered']);

    expect(Schema::hasTable('order_events'))->toBeTrue('there is no order_events audit table')
        ->and(DB::table('order_events')->count())->toBe(0);
});

it('gives the admin order page the events oldest first, with actor name and reason', function () {
    $order = eventsOrder('processing');

    $this->actingAs($this->admin)->patch(route('admin.orders.update-status', $order), ['status' => 'shipped']);
    $this->travel(5)->minutes();
    $this->actingAs($this->admin)->patch(route('admin.orders.update-status', $order), ['status' => 'processing', 'reason' => 'Wrong parcel']);
    $order->fresh()->transitionTo('shipped', null, null, ['source' => 'tracking']);

    $this->actingAs($this->admin)->get(route('admin.orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page
            ->has('events', 3)
            ->where('events.0.type', 'status_changed')
            ->where('events.0.from_status', 'processing')
            ->where('events.0.to_status', 'shipped')
            ->where('events.0.actor.name', 'Giulia Admin')
            ->where('events.1.to_status', 'processing')
            ->where('events.1.reason', 'Wrong parcel')
            ->where('events.2.actor', null)
            ->where('events.2.source', 'tracking')
            ->has('events.0.created_at'));
});

it('gives an order without history an empty event list', function () {
    $order = eventsOrder('delivered');

    $this->actingAs($this->admin)->get(route('admin.orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('events', []));
});
