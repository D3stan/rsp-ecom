<?php

use App\Enums\OpsEventType;
use App\Models\OpsEvent;
use App\Models\Order;
use App\Services\Ops\DailyDigest;
use App\Settings\SystemInfo;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->yesterday = now()->subDay()->setTime(12, 0);
});

it('summarises yesterday: revenue, funnel and open work', function () {
    Order::factory()->count(2)->create(['payment_status' => 'succeeded', 'currency' => 'EUR', 'total_amount' => 25.00, 'status' => 'processing', 'created_at' => $this->yesterday]);
    Order::factory()->create(['payment_status' => 'succeeded', 'currency' => 'EUR', 'total_amount' => 99.00, 'status' => 'processing', 'created_at' => now()->subDays(3)]);
    Order::factory()->create(['payment_status' => 'succeeded', 'status' => 'pending', 'created_at' => now()->subDays(3)]);
    OpsEvent::factory()->count(5)->ofType(OpsEventType::AddToCart)->at($this->yesterday)->create();
    OpsEvent::factory()->count(3)->ofType(OpsEventType::CheckoutStarted)->at($this->yesterday)->create();
    OpsEvent::factory()->count(2)->ofType(OpsEventType::PaymentStarted)->at($this->yesterday)->create();
    OpsEvent::factory()->count(4)->ofType(OpsEventType::AddToCart)->at(now())->create();

    $digest = app(DailyDigest::class)->build();

    expect($digest)
        ->toContain($this->yesterday->toDateString())
        ->toContain('Orders: 2 paid, 50.00 EUR')
        ->toContain('Funnel: 5 add to cart → 3 checkout → 2 payment started')
        ->toContain('Orders waiting for you: 1');
});

it('says so plainly when nothing was sold', function () {
    expect(app(DailyDigest::class)->build())->toContain('Orders: 0 paid');
});

it('reports the result of the automatic checks', function () {
    Cache::put(SystemInfo::INVARIANTS_CACHE_KEY, ['status' => 'problem', 'checked_at' => now()->toIso8601String()]);

    expect(app(DailyDigest::class)->build())
        ->toContain('invariants problem')
        ->toContain('Stripe reconciliation not run yet');
});

describe('ops:daily-digest', function () {
    beforeEach(function () {
        Http::preventStrayRequests();
    });

    it('sends the digest to Telegram', function () {
        config(['services.telegram.token' => 'secret-token', 'services.telegram.chat_id' => '4242']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->artisan('ops:daily-digest')->assertSuccessful();

        Http::assertSent(fn (Request $request) => str_contains($request['text'], 'Orders: 0 paid'));
    });

    it('prints the digest without failing when Telegram is not configured', function () {
        Http::fake();

        $this->artisan('ops:daily-digest')->expectsOutputToContain('not configured')->assertSuccessful();

        Http::assertNothingSent();
    });

    it('fails when Telegram refuses the message', function () {
        config(['services.telegram.token' => 'secret-token', 'services.telegram.chat_id' => '4242']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 400)]);

        $this->artisan('ops:daily-digest')->assertFailed();
    });

    it('is scheduled every morning after the reconciliation', function () {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command, 'ops:daily-digest'));

        expect($event)->not->toBeNull()->and($event->expression)->toBe('0 8 * * *');
    });
});
