<?php

use App\Jobs\SendOrderEmail;
use App\Models\Order;
use App\Models\OrderEmail;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Mail\Lifecycle\Support\LifecycleFixtures as Fx;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    Queue::fake();
});

function lifecycleJobs(string $type): int
{
    return Queue::pushed(SendOrderEmail::class, fn ($job) => $job->type === $type)->count();
}

it('emails once when an admin cancels a paid order', function () {
    $order = Fx::paidOrder();

    $this->actingAs($this->admin)->patch(route('admin.orders.cancel', $order))->assertSessionHas('success');
    // the second attempt is refused by the controller (already cancelled)
    $this->actingAs($this->admin)->patch(route('admin.orders.cancel', $order));

    expect($order->fresh()->status)->toBe('cancelled')
        ->and(lifecycleJobs(OrderEmail::TYPE_CANCELLED))->toBe(1)
        ->and(OrderEmail::where('type', OrderEmail::TYPE_CANCELLED)->where('order_id', $order->id)->count())->toBe(1);
});

it('stays silent when an unpaid, never confirmed checkout is cancelled', function () {
    $order = Fx::unpaidOrder();

    $this->actingAs($this->admin)->patch(route('admin.orders.cancel', $order))->assertSessionHas('success');

    expect($order->fresh()->status)->toBe('cancelled')
        ->and($order->fresh()->payment_status)->toBe('cancelled');
    Queue::assertNothingPushed();
    expect(OrderEmail::count())->toBe(0);
});

it('emails a cancellation when the customer had received the confirmation even if payment_status is not succeeded', function () {
    $order = Fx::unpaidOrder();
    $order->forceFill(['confirmation_email_sent' => true])->save();

    $order->cancel();

    expect(lifecycleJobs(OrderEmail::TYPE_CANCELLED))->toBe(1);
});

it('emails delivered exactly once however often the order is saved or moved back and forth', function () {
    $order = Fx::paidOrder(['status' => 'shipped']);

    $order->update(['status' => 'delivered']);
    $order->update(['notes' => 'saved again']);
    $order->update(['status' => 'delivered']);          // no change at all
    $order->update(['status' => 'shipped']);
    $order->update(['status' => 'delivered']);          // delivered a second time

    expect(lifecycleJobs(OrderEmail::TYPE_DELIVERED))->toBe(1)
        ->and(OrderEmail::where('type', OrderEmail::TYPE_DELIVERED)->count())->toBe(1);
});

it('never emails for orders created already delivered or cancelled (factories, seeders)', function () {
    Order::factory()->count(3)->create(['status' => 'delivered', 'payment_status' => 'succeeded', 'user_id' => null, 'guest_email' => 'a@example.test']);
    Order::factory()->create(['status' => 'cancelled', 'payment_status' => 'succeeded', 'user_id' => null, 'guest_email' => 'b@example.test']);

    Queue::assertNothingPushed();
    expect(OrderEmail::count())->toBe(0);
});

it('does not email delivered for an order whose customer was never told about it', function () {
    $order = Fx::unpaidOrder();

    $order->markAsDelivered();

    Queue::assertNothingPushed();
});

it('emails delivered through the admin edit form and the status endpoint', function () {
    $viaForm = Fx::paidOrder(['status' => 'shipped']);
    $viaStatus = Fx::paidOrder(['status' => 'shipped', 'guest_email' => 'two@example.test']);

    $this->actingAs($this->admin)->put(route('admin.orders.update', $viaForm), ['status' => 'delivered'])->assertSessionHas('success');
    $this->actingAs($this->admin)->patch(route('admin.orders.update-status', $viaStatus), ['status' => 'delivered'])->assertSessionHas('success');

    expect(lifecycleJobs(OrderEmail::TYPE_DELIVERED))->toBe(2);
    expect(OrderEmail::pluck('order_id')->sort()->values()->all())
        ->toBe(collect([$viaForm->id, $viaStatus->id])->sort()->values()->all());
});

it('respects the per-type switch and the master switch, writing no order_emails row', function () {
    $order = Fx::paidOrder(['status' => 'shipped']);
    Setting::set('order_delivered_enabled', false, 'boolean');
    Setting::set('order_cancelled_enabled', false, 'boolean');

    $order->update(['status' => 'delivered']);
    Fx::paidOrder()->cancel();

    Queue::assertNothingPushed();
    expect(OrderEmail::count())->toBe(0);

    Setting::set('order_delivered_enabled', true, 'boolean');
    Setting::set('order_cancelled_enabled', true, 'boolean');
    Setting::set('email_notifications_enabled', false, 'boolean');
    Fx::paidOrder(['status' => 'shipped'])->update(['status' => 'delivered']);

    Queue::assertNothingPushed();
    expect(OrderEmail::count())->toBe(0);
});

it('does not break the status change for an order without any recipient', function () {
    $order = Fx::paidOrder(['status' => 'shipped', 'guest_email' => null]);

    expect($order->update(['status' => 'delivered']))->toBeTrue();

    expect($order->fresh()->status)->toBe('delivered');
    Queue::assertNothingPushed();
});

it('does not break the status change when the mail layer fails', function () {
    $order = Fx::paidOrder(['status' => 'shipped']);
    $this->mock(\App\Services\EmailService::class)
        ->shouldReceive('sendOrderDelivered')->once()->andThrow(new RuntimeException('queue is down'));

    expect($order->update(['status' => 'delivered']))->toBeTrue();
    expect($order->fresh()->status)->toBe('delivered');
});

it('bulk-delivers and bulk-cancels through the real route, one email per eligible order', function () {
    $a = Fx::paidOrder(['status' => 'shipped', 'guest_email' => 'a@example.test']);
    $b = Fx::paidOrder(['status' => 'shipped', 'guest_email' => 'b@example.test']);
    $c = Fx::paidOrder(['status' => 'processing', 'guest_email' => 'c@example.test']);
    $unpaid = Fx::unpaidOrder(['guest_email' => 'd@example.test']);

    $this->actingAs($this->admin)
        ->patch('/admin/orders/bulk-status', ['order_ids' => [$a->id, $b->id], 'status' => 'delivered'])
        ->assertRedirect()->assertSessionHas('success');
    $this->actingAs($this->admin)
        ->patch('/admin/orders/bulk-status', ['order_ids' => [$c->id, $unpaid->id], 'status' => 'cancelled'])
        ->assertRedirect()->assertSessionHas('success');
    // repeating the same bulk action sends nothing new
    $this->actingAs($this->admin)
        ->patch('/admin/orders/bulk-status', ['order_ids' => [$a->id, $b->id], 'status' => 'delivered'])
        ->assertRedirect();

    expect(Order::whereIn('id', [$a->id, $b->id])->pluck('status')->unique()->all())->toBe(['delivered'])
        ->and($c->fresh()->status)->toBe('cancelled')
        ->and($unpaid->fresh()->status)->toBe('cancelled');
    expect(lifecycleJobs(OrderEmail::TYPE_CANCELLED))->toBe(1)       // only the paid $c, not the abandoned checkout
        ->and(lifecycleJobs(OrderEmail::TYPE_DELIVERED))->toBe(2);   // $a and $b, the repeat adds none
});

it('resolves PATCH admin/orders/bulk-status to the bulk action, not to orders/{order}', function () {
    $route = app('router')->getRoutes()->match(Illuminate\Http\Request::create('/admin/orders/bulk-status', 'PATCH'));

    expect($route->getName())->toBe('admin.orders.bulk-update-status');

    $order = Fx::paidOrder(['guest_email' => null]);
    expect(app('router')->getRoutes()->match(Illuminate\Http\Request::create('/admin/orders/'.$order->id, 'PATCH'))->getName())
        ->toBe('admin.orders.update');
});

it('does not email a refund from the placeholder refund action', function () {
    $order = Fx::paidOrder();

    $this->actingAs($this->admin)
        ->post(route('admin.orders.refund', $order), ['amount' => 10, 'reason' => 'test'])
        ->assertSessionHas('success');

    Queue::assertNothingPushed();
    expect(OrderEmail::where('type', OrderEmail::TYPE_REFUNDED)->count())->toBe(0);
});
