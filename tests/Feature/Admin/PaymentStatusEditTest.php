<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->admin = User::factory()->create(['role' => 'admin']);
});

function paymentEditOrder(string $paymentStatus, array $attributes = []): Order
{
    $order = Order::factory()->create(array_merge([
        'status' => 'processing',
        'payment_status' => $paymentStatus,
        'user_id' => null,
        'guest_email' => 'buyer@example.test',
        'stripe_payment_intent_id' => null,
    ], $attributes));
    $order->orderItems()->create(['product_id' => null, 'product_name' => 'Line A', 'quantity' => 2, 'price' => 10.00, 'total' => 20.00]);

    return $order->fresh('orderItems');
}

it('does not put an old payment status back when an unrelated save comes from a stale form', function () {
    // the form was loaded while the payment was pending; the Stripe webhook has since captured it
    $order = paymentEditOrder('succeeded', ['stripe_payment_intent_id' => 'pi_test_1']);

    $this->actingAs($this->admin)->put(route('admin.orders.update', $order), [
        'payment_status' => 'pending',
        'payment_status_was' => 'pending',
        'notes' => 'called the customer',
    ])->assertSessionHas('success');

    expect($order->fresh()->payment_status)->toBe('succeeded')
        ->and($order->fresh()->notes)->toBe('called the customer');
});

it('refuses a payment status change made on a form that is out of date', function () {
    $order = paymentEditOrder('succeeded');

    $this->actingAs($this->admin)->put(route('admin.orders.update', $order), [
        'payment_status' => 'failed',
        'payment_status_was' => 'pending',
    ])->assertSessionHasErrors('order');

    expect($order->fresh()->payment_status)->toBe('succeeded');
});

it('still lets an admin mark an offline order as paid', function () {
    $order = paymentEditOrder('pending');

    $this->actingAs($this->admin)->put(route('admin.orders.update', $order), [
        'payment_status' => 'succeeded',
        'payment_status_was' => 'pending',
    ])->assertSessionHas('success');

    expect($order->fresh()->payment_status)->toBe('succeeded');
});

it('does not let a Stripe-captured payment be taken back by an edit', function () {
    $order = paymentEditOrder('succeeded', ['stripe_payment_intent_id' => 'pi_test_1']);

    $this->actingAs($this->admin)->put(route('admin.orders.update', $order), [
        'payment_status' => 'pending',
        'payment_status_was' => 'succeeded',
    ])->assertSessionHasErrors('order');

    expect($order->fresh()->payment_status)->toBe('succeeded');
});

it('does not let anyone set a refunded status by hand on a Stripe order', function (string $status) {
    $order = paymentEditOrder('pending', ['stripe_payment_intent_id' => 'pi_test_1']);

    $this->actingAs($this->admin)->put(route('admin.orders.update', $order), [
        'payment_status' => $status,
        'payment_status_was' => 'pending',
    ])->assertSessionHasErrors('order');

    expect($order->fresh()->payment_status)->toBe('pending');
})->with(['refunded', 'partially_refunded']);

it('locks lines and totals once the order has shipped, even when the payment status was edited', function (string $status) {
    $order = paymentEditOrder('pending', ['status' => $status]);
    $item = $order->orderItems->first();

    $this->actingAs($this->admin)->put(route('admin.orders.update', $order), [
        'order_items' => json_encode([['id' => $item->id, 'quantity' => 5, 'price' => 10]]),
    ])->assertSessionHasErrors('order');

    expect($item->fresh()->quantity)->toBe(2);
})->with(['shipped', 'delivered']);

it('records who changed the payment status, from what and to what', function () {
    $order = paymentEditOrder('pending');

    $this->actingAs($this->admin)->put(route('admin.orders.update', $order), [
        'payment_status' => 'succeeded',
        'payment_status_was' => 'pending',
        'reason' => 'bank transfer received',
    ])->assertSessionHas('success');

    $event = $order->events()->where('type', 'payment_status_changed')->sole();

    expect($event->actor_id)->toBe($this->admin->id)
        ->and($event->from_status)->toBe('pending')
        ->and($event->to_status)->toBe('succeeded')
        ->and($event->payload['reason'])->toBe('bank transfer received');
});

it('records nothing when the payment status was left alone', function () {
    $order = paymentEditOrder('pending');

    $this->actingAs($this->admin)->put(route('admin.orders.update', $order), [
        'payment_status' => 'pending',
        'payment_status_was' => 'pending',
        'notes' => 'just a note',
    ])->assertSessionHas('success');

    expect($order->events()->where('type', 'payment_status_changed')->exists())->toBeFalse();
});
