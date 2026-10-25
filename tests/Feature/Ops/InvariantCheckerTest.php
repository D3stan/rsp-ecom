<?php

use App\Enums\OpsEventType;
use App\Models\OpsEvent;
use App\Models\Order;
use App\Models\OrderEmail;
use App\Services\Ops\InvariantChecker;

function paidOrder(array $attributes = []): Order
{
    return Order::factory()->create(array_merge([
        'status' => 'processing',
        'payment_status' => 'succeeded',
        'stripe_checkout_session_id' => 'cs_'.fake()->unique()->numerify('########'),
        'created_at' => now()->subHour(),
    ], $attributes));
}

function opsEvents(OpsEventType $type, int $count, ?DateTimeInterface $at = null): void
{
    $factory = OpsEvent::factory()->count($count)->ofType($type);

    ($at ? $factory->at($at) : $factory)->create();
}

function problems(): array
{
    return app(InvariantChecker::class)->problems();
}

it('reports nothing for a healthy shop', function () {
    paidOrder();

    expect(problems())->toBe([]);
});

describe('orders awaiting payment', function () {
    it('flags an order still unpaid after the limit', function () {
        Order::factory()->create(['status' => 'pending', 'payment_status' => 'pending', 'created_at' => now()->subHours(73)]);

        expect(problems())->toHaveKey('payment-pending');
    });

    it('leaves a recent unpaid order alone', function () {
        Order::factory()->create(['status' => 'pending', 'payment_status' => 'pending', 'created_at' => now()->subHours(2)]);

        expect(problems())->not->toHaveKey('payment-pending');
    });
});

describe('paid orders waiting to ship', function () {
    it('flags a paid order that has not shipped after the limit', function () {
        paidOrder(['created_at' => now()->subDays(4)]);

        expect(problems())->toHaveKey('paid-waiting');
    });

    it('does not flag one that already has a tracking number', function () {
        paidOrder(['created_at' => now()->subDays(4), 'tracking_number' => 'TRK123']);

        expect(problems())->not->toHaveKey('paid-waiting');
    });

    it('does not flag a recent one', function () {
        paidOrder(['created_at' => now()->subDay()]);

        expect(problems())->not->toHaveKey('paid-waiting');
    });
});

describe('order emails', function () {
    it('flags an email that failed recently and was never sent', function () {
        OrderEmail::create(['order_id' => paidOrder()->id, 'type' => 'confirmation', 'fingerprint' => 'a', 'failed_at' => now()->subHour(), 'error' => 'boom']);

        expect(problems())->toHaveKey('failed-emails');
    });

    it('ignores a failure that a retry later delivered', function () {
        OrderEmail::create(['order_id' => paidOrder()->id, 'type' => 'confirmation', 'fingerprint' => 'a', 'failed_at' => now()->subHour(), 'sent_at' => now()]);

        expect(problems())->not->toHaveKey('failed-emails');
    });

    it('ignores a failure older than the window', function () {
        OrderEmail::create(['order_id' => paidOrder()->id, 'type' => 'confirmation', 'fingerprint' => 'a', 'failed_at' => now()->subHours(30)]);

        expect(problems())->not->toHaveKey('failed-emails');
    });
});

describe('Stripe webhook silence', function () {
    beforeEach(function () {
        // Tracking has been recording for two days
        OpsEvent::factory()->ofType(OpsEventType::AddToCart)->at(now()->subDays(2))->create();
    });

    it('flags a paid order that no webhook has followed', function () {
        $order = paidOrder(['created_at' => now()->subMinutes(30)]);

        expect(problems()['webhook-silence'])->toContain($order->order_number);
    });

    it('is satisfied by a webhook received around the time of the order', function () {
        paidOrder(['created_at' => now()->subMinutes(30)]);
        OpsEvent::factory()->ofType(OpsEventType::StripeWebhook)->withoutVisitor()->at(now()->subMinutes(29))->create();

        expect(problems())->not->toHaveKey('webhook-silence');
    });

    it('gives a fresh order time for its webhook to arrive', function () {
        paidOrder(['created_at' => now()->subMinutes(5)]);

        expect(problems())->not->toHaveKey('webhook-silence');
    });

    it('ignores orders that predate the tracking', function () {
        paidOrder(['created_at' => now()->subDays(3)->addMinutes(10)]);

        expect(problems())->not->toHaveKey('webhook-silence');
    });
});

it('does not judge the webhook before any event has been recorded', function () {
    paidOrder(['created_at' => now()->subMinutes(30)]);

    expect(problems())->not->toHaveKey('webhook-silence');
});

describe('payments without orders', function () {
    it('flags many visitors reaching Stripe while nobody paid', function () {
        opsEvents(OpsEventType::PaymentStarted, 10);

        expect(problems())->toHaveKey('payments-without-orders');
    });

    it('stays quiet once an order was paid', function () {
        opsEvents(OpsEventType::PaymentStarted, 10);
        paidOrder();

        expect(problems())->not->toHaveKey('payments-without-orders');
    });

    it('stays quiet below the visitor threshold', function () {
        opsEvents(OpsEventType::PaymentStarted, 9);

        expect(problems())->not->toHaveKey('payments-without-orders');
    });
});

describe('no recent orders', function () {
    it('is switched off by default', function () {
        expect(problems())->not->toHaveKey('no-orders');
    });

    it('flags a quiet shop once a limit is configured', function () {
        config(['ops.invariants.no_orders_hours' => 6]);

        expect(problems())->toHaveKey('no-orders');
    });

    it('stays quiet when an order arrived inside the limit', function () {
        config(['ops.invariants.no_orders_hours' => 6]);
        paidOrder(['created_at' => now()->subHours(2)]);

        expect(problems())->not->toHaveKey('no-orders');
    });
});

describe('funnel drops', function () {
    function weeklyFunnel(int $from, int $to): void
    {
        opsEvents(OpsEventType::AddToCart, $from, now()->subDays(4));
        opsEvents(OpsEventType::CheckoutStarted, $to, now()->subDays(4));
    }

    it('flags a step whose conversion collapsed against the previous week', function () {
        weeklyFunnel(from: 30, to: 20);
        opsEvents(OpsEventType::AddToCart, 12);
        opsEvents(OpsEventType::CheckoutStarted, 1);

        expect(problems()['funnel-add_to_cart-checkout_started'])->toContain('8%')->toContain('67%');
    });

    it('stays quiet when the conversion holds', function () {
        weeklyFunnel(from: 30, to: 20);
        opsEvents(OpsEventType::AddToCart, 12);
        opsEvents(OpsEventType::CheckoutStarted, 8);

        expect(problems())->not->toHaveKey('funnel-add_to_cart-checkout_started');
    });

    it('does not judge on too little traffic', function () {
        weeklyFunnel(from: 30, to: 20);
        opsEvents(OpsEventType::AddToCart, 5);

        expect(problems())->not->toHaveKey('funnel-add_to_cart-checkout_started');
    });
});
