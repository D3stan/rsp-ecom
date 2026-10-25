<?php

use App\Models\Order;
use App\Services\Ops\StripeReconciler;
use App\Services\OrderCreationService;
use App\Settings\SystemInfo;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Stripe\ApiRequestor;
use Tests\Feature\Orders\Support\FakeStripeHttpClient;

beforeEach(function () {
    $this->stripe = new FakeStripeHttpClient;
    ApiRequestor::setHttpClient($this->stripe);
    config(['cashier.secret' => 'sk_test_fake_for_tests']);
});

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

function stripeSession(string $id, array $overrides = []): array
{
    return array_replace_recursive([
        'id' => $id,
        'object' => 'checkout.session',
        'status' => 'complete',
        'payment_status' => 'paid',
        'amount_total' => 1000,
        'metadata' => ['source' => OrderCreationService::SESSION_SOURCE],
    ], $overrides);
}

function stripeReturns(array ...$sessions): void
{
    test()->stripe->responses['GET /v1/checkout/sessions'] = [
        'object' => 'list', 'data' => $sessions, 'has_more' => false, 'url' => '/v1/checkout/sessions',
    ];
}

function orderForSession(string $sessionId, array $attributes = []): Order
{
    return Order::factory()->create(array_merge([
        'stripe_checkout_session_id' => $sessionId,
        'payment_status' => 'succeeded',
        'total_amount' => 10.00,
    ], $attributes));
}

function reconciliation(): array
{
    return app(StripeReconciler::class)->problems();
}

it('finds nothing when Stripe and the orders agree', function () {
    orderForSession('cs_1');
    stripeReturns(stripeSession('cs_1'));

    expect(reconciliation())->toBe([]);
});

it('flags a paid Stripe session that has no order', function () {
    stripeReturns(stripeSession('cs_lost'));

    expect(reconciliation()['reconciliation-missing-orders'])->toContain('1 paid Stripe session')->toContain('cs_lost');
});

it('lists at most five of many missing sessions', function () {
    stripeReturns(...array_map(fn ($i) => stripeSession("cs_lost_{$i}"), range(1, 8)));

    expect(reconciliation()['reconciliation-missing-orders'])->toContain('8 paid Stripe session')->toContain('cs_lost_5')->not->toContain('cs_lost_6')->toEndWith('…');
});

it('ignores sessions that are not paid checkouts of this shop', function () {
    stripeReturns(
        stripeSession('cs_expired', ['status' => 'expired', 'payment_status' => 'unpaid']),
        stripeSession('cs_other_app', ['metadata' => ['source' => 'another-app']]),
        stripeSession('cs_waiting_for_bank', ['payment_status' => 'unpaid']),
    );

    expect(reconciliation())->toBe([]);
});

it('flags an order paid at Stripe that is not marked paid here', function () {
    $order = orderForSession('cs_1', ['payment_status' => 'pending']);
    stripeReturns(stripeSession('cs_1'));

    expect(reconciliation()['reconciliation-not-captured'])->toContain($order->order_number);
});

it('accepts a refunded order whose payment was captured', function () {
    orderForSession('cs_1', ['payment_status' => 'refunded']);
    stripeReturns(stripeSession('cs_1'));

    expect(reconciliation())->toBe([]);
});

it('flags an order marked paid here that Stripe never collected', function () {
    $order = orderForSession('cs_1');
    stripeReturns(stripeSession('cs_1', ['payment_status' => 'unpaid']));

    expect(reconciliation()['reconciliation-paid-locally-only'])->toContain($order->order_number);
});

it('flags an order whose amount differs from what Stripe charged', function () {
    $order = orderForSession('cs_1', ['total_amount' => 10.00]);
    stripeReturns(stripeSession('cs_1', ['amount_total' => 1250]));

    expect(reconciliation()['reconciliation-amount'])->toContain($order->order_number);
});

it('reports a difference of one cent as rounding, not as a mismatch', function () {
    orderForSession('cs_1', ['total_amount' => 10.00]);
    stripeReturns(stripeSession('cs_1', ['amount_total' => 1001]));

    expect(reconciliation())->toBe([]);
});

it('reports that it could not run when Stripe is unreachable', function () {
    $this->stripe->responses['GET /v1/checkout/sessions'] = ['__throw' => 'connection'];

    expect(reconciliation())->toHaveKey('reconciliation-unavailable');
});

it('reports that it could not run without a Stripe key', function () {
    config(['cashier.secret' => null]);

    expect(reconciliation())->toHaveKey('reconciliation-unavailable');
});

describe('ops:reconcile-stripe', function () {
    beforeEach(function () {
        Http::preventStrayRequests();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        config(['services.telegram.token' => 'secret-token', 'services.telegram.chat_id' => '4242']);
    });

    it('succeeds quietly and records an ok result', function () {
        stripeReturns();

        $this->artisan('ops:reconcile-stripe')->expectsOutputToContain('agree')->assertSuccessful();

        expect(Cache::get(SystemInfo::RECONCILIATION_CACHE_KEY)['status'])->toBe('ok');
        Http::assertNothingSent();
    });

    it('alerts on Telegram once a day for a standing problem', function () {
        stripeReturns(stripeSession('cs_lost'));

        $this->artisan('ops:reconcile-stripe')->assertFailed();
        $this->artisan('ops:reconcile-stripe')->assertFailed();

        expect(Cache::get(SystemInfo::RECONCILIATION_CACHE_KEY)['status'])->toBe('problem');
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => str_contains($request['text'], 'cs_lost'));
    });

    it('is scheduled every morning', function () {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command, 'ops:reconcile-stripe'));

        expect($event)->not->toBeNull()->and($event->expression)->toBe('0 7 * * *');
    });
});
