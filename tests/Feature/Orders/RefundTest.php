<?php

use App\Listeners\StripeWebhookListener;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
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
