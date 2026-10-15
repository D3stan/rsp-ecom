<?php

use App\Jobs\SendOrderEmail;
use App\Mail\OrderCancelled;
use App\Models\Order;
use App\Models\OrderEmail;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Mail\Content\Support\EmailFixtures;
use Tests\Feature\Orders\Support\BuildsStripeOrderFixtures;

uses(BuildsStripeOrderFixtures::class);

beforeEach(function () {
    Queue::fake();
    $this->setUpOrderFixtures();
    $this->admin = User::factory()->create(['role' => 'admin']);
});

afterEach(function () {
    $this->tearDownOrderFixtures();
});

function cancellationOrder(array $attributes = []): Order
{
    return Order::factory()->create(array_merge([
        'status' => 'processing',
        'payment_status' => 'succeeded',
        'user_id' => null,
        'guest_email' => 'buyer@example.test',
        'locale' => 'en',
        'currency' => 'EUR',
        'subtotal' => 50.00,
        'shipping_amount' => 5.00,
        'discount_amount' => 0,
        'total_amount' => 55.00,
        'stripe_payment_intent_id' => 'pi_cancel',
    ], $attributes));
}

it('lists a cancelled paid order under "Refund due", and only that one', function () {
    $due = cancellationOrder();
    $unpaid = cancellationOrder(['status' => 'pending', 'payment_status' => 'pending', 'stripe_payment_intent_id' => null]);
    cancellationOrder(); // paid, still open: nothing owed

    $this->actingAs($this->admin)->patch(route('admin.orders.cancel', $due))->assertSessionHas('success');
    $this->actingAs($this->admin)->patch(route('admin.orders.cancel', $unpaid))->assertSessionHas('success');

    $this->actingAs($this->admin)->get(route('admin.orders.index', ['refund_due' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $due->id));
});

it('flags a cancelled paid order as refund due on its page', function () {
    $order = cancellationOrder();
    $this->actingAs($this->admin)->patch(route('admin.orders.cancel', $order));

    $this->actingAs($this->admin)->get(route('admin.orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('refund_due', true));
});

it('cancels a paid order and refunds it in full in one step', function () {
    $order = cancellationOrder();
    $this->stripeHttp->responses['/v1/refunds'] = ['id' => 're_cancel', 'object' => 'refund', 'amount' => 5500, 'currency' => 'eur', 'status' => 'succeeded'];

    $this->actingAs($this->admin)->patch(route('admin.orders.cancel', $order), ['refund' => 'now'])->assertSessionHas('success');

    $refundCalls = array_filter($this->stripeHttp->requests, fn ($request) => str_contains($request[1], '/v1/refunds'));
    expect($refundCalls)->toHaveCount(1)
        ->and((int) array_values($refundCalls)[0][2]['amount'])->toBe(5500)
        ->and($order->fresh()->status)->toBe('cancelled')
        ->and($order->fresh()->payment_status)->toBe('refunded');
});

it('counts net revenue: cancelled orders out, refunds subtracted', function () {
    cancellationOrder(['total_amount' => 100.00]);
    cancellationOrder(['total_amount' => 40.00, 'status' => 'cancelled']);              // cancelled, money not returned yet
    $partly = cancellationOrder(['total_amount' => 80.00, 'payment_status' => 'partially_refunded']);
    Refund::create(['order_id' => $partly->id, 'amount_cents' => 3000, 'currency' => 'EUR', 'status' => 'succeeded', 'stripe_refund_id' => 're_kpi', 'source' => 'admin']);

    // 100 + (80 - 30) = 150
    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.today.revenue', fn ($revenue) => (float) $revenue === 150.0)
            ->where('stats.month.revenue', fn ($revenue) => (float) $revenue === 150.0));

    $this->actingAs($this->admin)->get(route('admin.orders.index'))
        ->assertInertia(fn (Assert $page) => $page->where('kpis.today.revenue', fn ($revenue) => (float) $revenue === 150.0));
});

it('tells the customer the money was refunded when it was, before the cancellation mail goes out', function () {
    $order = cancellationOrder(['status' => 'cancelled', 'payment_status' => 'refunded']);
    Refund::create(['order_id' => $order->id, 'amount_cents' => 5500, 'currency' => 'EUR', 'status' => 'succeeded', 'stripe_refund_id' => 're_mail', 'source' => 'admin']);

    $text = EmailFixtures::deliver(new OrderCancelled($order), 'en')->getTextBody();

    expect($text)->toContain('We have refunded €55.00')
        ->not->toContain('If you already paid');
});

it('promises a refund of what is still owed on a partly refunded cancelled order', function () {
    $order = cancellationOrder(['status' => 'cancelled', 'payment_status' => 'partially_refunded']);
    Refund::create(['order_id' => $order->id, 'amount_cents' => 2000, 'currency' => 'EUR', 'status' => 'succeeded', 'stripe_refund_id' => 're_part_mail', 'source' => 'admin']);

    $text = EmailFixtures::deliver(new OrderCancelled($order), 'en')->getTextBody();

    expect($text)->toContain('we will refund €35.00');
});

it('cancels a paid order without touching the money when the refund comes later', function () {
    $order = cancellationOrder();

    $this->actingAs($this->admin)->patch(route('admin.orders.cancel', $order), ['refund' => 'later'])->assertSessionHas('success');

    expect($this->stripeHttp->requests)->toBe([])
        ->and($order->fresh()->status)->toBe('cancelled')
        ->and($order->fresh()->isRefundDue())->toBeTrue();
});

it('does not cancel when the refund of "cancel and refund now" fails', function () {
    $order = cancellationOrder();
    $this->stripeHttp->responses['/v1/refunds'] = ['__status' => 400, 'error' => ['type' => 'invalid_request_error', 'message' => 'No such payment_intent']];

    $this->actingAs($this->admin)->patch(route('admin.orders.cancel', $order), ['refund' => 'now'])
        ->assertSessionHas('error', fn ($message) => str_contains($message, 'not cancelled'));

    expect($order->fresh()->status)->toBe('processing')
        ->and($order->fresh()->payment_status)->toBe('succeeded');
});

it('still cancels when Stripe does not confirm the refund, keeping it pending', function () {
    $order = cancellationOrder();
    $this->stripeHttp->responses['/v1/refunds'] = ['__throw' => 'connection'];

    $this->actingAs($this->admin)->patch(route('admin.orders.cancel', $order), ['refund' => 'now'])
        ->assertSessionHas('success')
        ->assertSessionHas('warning');

    expect($order->fresh()->status)->toBe('cancelled')
        ->and(Refund::sole()->status)->toBe('pending');
});

it('emails the cancellation after a cancel-and-refund (the order is refunded, the customer still hears of it)', function () {
    $order = cancellationOrder();
    $this->stripeHttp->responses['/v1/refunds'] = ['id' => 're_c2', 'object' => 'refund', 'amount' => 5500, 'currency' => 'eur', 'status' => 'succeeded'];

    $this->actingAs($this->admin)->patch(route('admin.orders.cancel', $order), ['refund' => 'now']);

    Queue::assertPushed(SendOrderEmail::class, fn ($job) => $job->type === OrderEmail::TYPE_CANCELLED);
    Queue::assertPushed(SendOrderEmail::class, fn ($job) => $job->type === OrderEmail::TYPE_REFUNDED);
});

it('does not call a fully refunded or an unpaid cancelled order "refund due"', function () {
    expect(cancellationOrder(['status' => 'cancelled', 'payment_status' => 'refunded'])->isRefundDue())->toBeFalse()
        ->and(cancellationOrder(['status' => 'cancelled', 'payment_status' => 'cancelled'])->isRefundDue())->toBeFalse()
        ->and(cancellationOrder(['status' => 'cancelled', 'payment_status' => 'partially_refunded'])->isRefundDue())->toBeTrue()
        ->and(Order::refundDue()->count())->toBe(1);
});
