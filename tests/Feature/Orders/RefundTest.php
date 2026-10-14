<?php

use App\Jobs\SendOrderEmail;
use App\Listeners\StripeWebhookListener;
use App\Models\Order;
use App\Models\OrderEmail;
use App\Models\Refund;
use App\Models\User;
use App\Services\RefundService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Cashier\Events\WebhookReceived;
use Tests\Feature\Orders\Support\BuildsStripeOrderFixtures;
use Tests\Feature\Orders\Support\FakeStripeHttpClient;

uses(BuildsStripeOrderFixtures::class);

beforeEach(function () {
    Queue::fake();
    $this->setUpOrderFixtures();
    $this->admin = User::factory()->create(['role' => 'admin']);
});

afterEach(function () {
    $this->tearDownOrderFixtures();
});

/** A paid Stripe order of 55.00 (50.00 items + 5.00 shipping). */
function refundOrder(array $attributes = []): Order
{
    return Order::factory()->create(array_merge([
        'status' => 'processing',
        'payment_status' => 'succeeded',
        'user_id' => null,
        'guest_email' => 'buyer@example.test',
        'currency' => 'EUR',
        'subtotal' => 50.00,
        'shipping_amount' => 5.00,
        'discount_amount' => 0,
        'total_amount' => 55.00,
        'stripe_payment_intent_id' => 'pi_test_refund',
    ], $attributes));
}

/** Requests sent to the Stripe refunds endpoint so far (method, URL, params). */
function refundRequests(FakeStripeHttpClient $stripe, string $method = 'post'): array
{
    return array_values(array_filter(
        $stripe->requests,
        fn ($request) => strtolower($request[0]) === $method && str_contains($request[1], '/v1/refunds'),
    ));
}

it('sends a full refund to Stripe and marks the order refunded', function () {
    $order = refundOrder();
    $this->stripeHttp->responses['/v1/refunds'] = ['id' => 're_full', 'object' => 'refund', 'amount' => 5500, 'currency' => 'eur', 'status' => 'succeeded'];

    $this->actingAs($this->admin)->post(route('admin.orders.refund', $order), ['amount' => 55.00, 'reason' => 'Returned']);

    $sent = refundRequests($this->stripeHttp);
    expect($sent)->toHaveCount(1)
        ->and($sent[0][2]['payment_intent'])->toBe('pi_test_refund')
        ->and((int) $sent[0][2]['amount'])->toBe(5500)
        ->and($order->fresh()->payment_status)->toBe('refunded');
});

it('sends a partial refund to Stripe and marks the order partially refunded', function () {
    $order = refundOrder();
    $this->stripeHttp->responses['/v1/refunds'] = ['id' => 're_part', 'object' => 'refund', 'amount' => 2000, 'currency' => 'eur', 'status' => 'succeeded'];

    $this->actingAs($this->admin)->post(route('admin.orders.refund', $order), ['amount' => 20.00, 'reason' => 'One item back']);

    $sent = refundRequests($this->stripeHttp);
    expect($sent)->toHaveCount(1)
        ->and((int) $sent[0][2]['amount'])->toBe(2000)
        ->and($order->fresh()->payment_status)->toBe('partially_refunded');
});

it('records a refund made in the Stripe dashboard exactly once across webhook retries', function () {
    $order = refundOrder();
    $event = new WebhookReceived([
        'id' => 'evt_refunded',
        'type' => 'charge.refunded',
        'data' => ['object' => [
            'id' => 'ch_1', 'object' => 'charge', 'payment_intent' => 'pi_test_refund', 'amount' => 5500, 'amount_refunded' => 1500,
            'refunds' => ['object' => 'list', 'data' => [
                ['id' => 're_dash', 'object' => 'refund', 'amount' => 1500, 'currency' => 'eur', 'status' => 'succeeded', 'payment_intent' => 'pi_test_refund', 'metadata' => []],
            ]],
        ]],
    ]);

    app(StripeWebhookListener::class)->handle($event);
    app(StripeWebhookListener::class)->handle($event); // Stripe retries the delivery

    expect(Schema::hasTable('refunds'))->toBeTrue('there is no refunds table');
    $rows = DB::table('refunds')->where('order_id', $order->id)->get();
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->stripe_refund_id)->toBe('re_dash')
        ->and((int) $rows[0]->amount_cents)->toBe(1500)
        ->and($rows[0]->source)->toBe('stripe_dashboard')
        ->and($order->fresh()->payment_status)->toBe('partially_refunded');
});

it('registers the Stripe webhook listener exactly once', function () {
    $listeners = collect(Event::getRawListeners()[WebhookReceived::class] ?? [])
        ->filter(fn ($listener) => is_string($listener) && str_starts_with($listener, StripeWebhookListener::class));

    expect($listeners)->toHaveCount(1);
});

function refundResponse(string $id, int $amount, string $status = 'succeeded'): array
{
    return ['id' => $id, 'object' => 'refund', 'amount' => $amount, 'currency' => 'eur', 'status' => $status];
}

function refundEmails(): int
{
    return Queue::pushed(SendOrderEmail::class, fn ($job) => $job->type === OrderEmail::TYPE_REFUNDED)->count();
}

it('takes two partial refunds up to the total, then refuses more', function () {
    $order = refundOrder();
    $this->stripeHttp->responses['/v1/refunds'] = refundResponse('re_a', 3000);
    $this->actingAs($this->admin)->post(route('admin.orders.refund', $order), ['amount' => 30.00])->assertSessionHas('success');
    expect($order->fresh()->payment_status)->toBe('partially_refunded');

    $this->stripeHttp->responses['/v1/refunds'] = refundResponse('re_b', 2500);
    $this->actingAs($this->admin)->post(route('admin.orders.refund', $order), ['amount' => 25.00])->assertSessionHas('success');
    expect($order->fresh()->payment_status)->toBe('refunded');

    $this->actingAs($this->admin)->post(route('admin.orders.refund', $order), ['amount' => 0.01])->assertSessionHas('error');

    expect(refundRequests($this->stripeHttp))->toHaveCount(2)
        ->and(Refund::where('order_id', $order->id)->where('status', 'succeeded')->sum('amount_cents'))->toBe(5500)
        ->and(app(RefundService::class)->refundableCents($order->fresh()))->toBe(0)
        ->and(refundEmails())->toBe(2);
});

it('refuses to refund more than what is left, without calling Stripe', function () {
    $order = refundOrder();

    $this->actingAs($this->admin)->post(route('admin.orders.refund', $order), ['amount' => 55.01])
        ->assertSessionHas('error')
        ->assertSessionHasErrors('amount');

    expect(refundRequests($this->stripeHttp))->toBe([])
        ->and(Refund::count())->toBe(0)
        ->and($order->fresh()->payment_status)->toBe('succeeded');
});

it('refuses to refund an order whose payment was not captured', function () {
    $order = refundOrder(['payment_status' => 'processing']);

    $this->actingAs($this->admin)->post(route('admin.orders.refund', $order), ['amount' => 10])->assertSessionHas('error');

    expect(refundRequests($this->stripeHttp))->toBe([]);
});

it('records a Stripe error as a failed refund, emails nothing and frees the amount again', function () {
    $order = refundOrder();
    $this->stripeHttp->responses['/v1/refunds'] = ['__status' => 400, 'error' => ['type' => 'invalid_request_error', 'message' => 'Charge ch_1 has already been refunded.']];

    $this->actingAs($this->admin)->post(route('admin.orders.refund', $order), ['amount' => 55.00])
        ->assertSessionHas('error', fn ($message) => str_contains($message, 'already been refunded'));

    $refund = Refund::sole();
    expect($refund->status)->toBe('failed')
        ->and($refund->failure_message)->toContain('already been refunded')
        ->and($order->fresh()->payment_status)->toBe('succeeded')
        ->and(refundEmails())->toBe(0)
        ->and($order->events()->where('type', 'refund_failed')->count())->toBe(1)
        ->and(app(RefundService::class)->refundableCents($order->fresh()))->toBe(5500);
});

it('sends each refund to Stripe once, under its own idempotency key', function () {
    $order = refundOrder();
    $this->stripeHttp->responses['/v1/refunds'] = refundResponse('re_once', 5500);

    // a double submit of the same full refund
    $this->actingAs($this->admin)->post(route('admin.orders.refund', $order), ['amount' => 55.00]);
    $this->actingAs($this->admin)->post(route('admin.orders.refund', $order), ['amount' => 55.00])->assertSessionHas('error');

    $refund = Refund::sole();
    $index = array_key_last(array_filter($this->stripeHttp->requests, fn ($request) => str_contains($request[1], '/v1/refunds')));
    expect(refundRequests($this->stripeHttp))->toHaveCount(1)
        ->and($this->stripeHttp->headers[$index])->toContain('Idempotency-Key: refund:'.$refund->id)
        ->and(refundRequests($this->stripeHttp)[0][2]['metadata'])->toBe(['order_id' => (string) $order->id, 'refund_id' => (string) $refund->id]);
});

it('records the refund, its event and one email', function () {
    $order = refundOrder();
    $this->stripeHttp->responses['/v1/refunds'] = refundResponse('re_ok', 2000);

    $this->actingAs($this->admin)->post(route('admin.orders.refund', $order), ['amount' => 20.00, 'reason' => 'Damaged']);

    $refund = Refund::sole();
    $event = $order->events()->where('type', 'refund')->sole();
    expect($refund->only(['status', 'stripe_refund_id', 'amount_cents', 'currency', 'reason', 'source', 'created_by']))->toBe([
        'status' => 'succeeded', 'stripe_refund_id' => 're_ok', 'amount_cents' => 2000, 'currency' => 'EUR',
        'reason' => 'Damaged', 'source' => 'admin', 'created_by' => $this->admin->id,
    ])
        ->and($event->actor_id)->toBe($this->admin->id)
        ->and($event->payload['amount_cents'])->toBe(2000)
        ->and(refundEmails())->toBe(1)
        ->and(str_contains((string) $order->fresh()->notes, 'REFUND'))->toBeFalse();
});

it('sends one email per refund, also for two refunds of the same amount', function () {
    $order = refundOrder();
    $this->stripeHttp->responses['/v1/refunds'] = refundResponse('re_x', 1000);
    $this->actingAs($this->admin)->post(route('admin.orders.refund', $order), ['amount' => 10.00]);
    $this->stripeHttp->responses['/v1/refunds'] = refundResponse('re_y', 1000);
    $this->actingAs($this->admin)->post(route('admin.orders.refund', $order), ['amount' => 10.00]);

    expect(refundEmails())->toBe(2);
});

it('puts returned items back in stock once, and a later cancellation does not return them again', function () {
    $product = $this->makeProduct(25.00);
    $product->defaultVariant->update(['stock_quantity' => 10]);
    $order = refundOrder();
    $item = $order->orderItems()->create(['product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 2, 'price' => 25, 'total' => 50]);
    Order::whereKey($order->id)->update(['stock_deducted_at' => now()]);
    $this->stripeHttp->responses['/v1/refunds'] = refundResponse('re_stock', 2500);

    $this->actingAs($this->admin)->post(route('admin.orders.refund', $order), ['amount' => 25.00, 'restock' => [$item->id => 1]])
        ->assertSessionHas('success');
    $refund = Refund::sole();
    app(StockService::class)->restoreItems($order, [$item->id => 1], 'refund:'.$refund->id); // retried: no effect

    expect($product->defaultVariant->fresh()->stock_quantity)->toBe(11)
        ->and($item->fresh()->restocked_qty)->toBe(1);

    $order->fresh()->transitionTo('cancelled');

    expect($product->defaultVariant->fresh()->stock_quantity)->toBe(12); // only the unit not yet returned
});

it('completes an own refund whose outcome was never stored, from the webhook, once', function () {
    $order = refundOrder();
    $pending = Refund::create(['order_id' => $order->id, 'amount_cents' => 1500, 'currency' => 'EUR', 'status' => 'pending', 'source' => 'admin', 'created_by' => $this->admin->id]);
    $event = new WebhookReceived(['id' => 'evt_own', 'type' => 'charge.refunded', 'data' => ['object' => [
        'id' => 'ch_1', 'object' => 'charge', 'payment_intent' => 'pi_test_refund',
        'refunds' => ['object' => 'list', 'has_more' => false, 'data' => [
            ['id' => 're_own', 'object' => 'refund', 'amount' => 1500, 'currency' => 'eur', 'status' => 'succeeded', 'metadata' => ['order_id' => (string) $order->id, 'refund_id' => (string) $pending->id]],
        ]],
    ]]]);

    app(StripeWebhookListener::class)->handle($event);
    app(StripeWebhookListener::class)->handle($event);

    expect(Refund::count())->toBe(1)
        ->and($pending->fresh()->status)->toBe('succeeded')
        ->and($pending->fresh()->stripe_refund_id)->toBe('re_own')
        ->and($order->fresh()->payment_status)->toBe('partially_refunded')
        ->and(refundEmails())->toBe(1);
});

it('lists the refunds of the charge from Stripe when the event does not carry them', function () {
    $order = refundOrder();
    $this->stripeHttp->responses['/v1/refunds'] = ['object' => 'list', 'has_more' => false, 'data' => [refundResponse('re_listed', 5500) + ['metadata' => []]]];

    app(StripeWebhookListener::class)->handle(new WebhookReceived(['id' => 'evt_l', 'type' => 'charge.refunded', 'data' => ['object' => [
        'id' => 'ch_1', 'object' => 'charge', 'payment_intent' => 'pi_test_refund', 'amount_refunded' => 5500,
    ]]]));

    expect(Refund::sole()->stripe_refund_id)->toBe('re_listed')
        ->and($order->fresh()->payment_status)->toBe('refunded')
        ->and(refundRequests($this->stripeHttp, 'get'))->toHaveCount(1);
});

it('gives the admin order page the refunds and what is still refundable', function () {
    $order = refundOrder();
    $this->stripeHttp->responses['/v1/refunds'] = refundResponse('re_page', 2000);
    $this->actingAs($this->admin)->post(route('admin.orders.refund', $order), ['amount' => 20.00]);

    $this->actingAs($this->admin)->get(route('admin.orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page
            ->where('refund.refundable_cents', 3500)
            ->where('refunds.0.amount_cents', 2000)
            ->where('refunds.0.status', 'succeeded')
            ->where('refunds.0.source', 'admin'));
});

it('forbids customers to refund', function () {
    $order = refundOrder();
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)->post(route('admin.orders.refund', $order), ['amount' => 10])->assertForbidden();

    expect(refundRequests($this->stripeHttp))->toBe([]);
});
