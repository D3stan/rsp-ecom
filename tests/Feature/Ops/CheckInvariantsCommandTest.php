<?php

use App\Settings\SystemInfo;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Http::preventStrayRequests();
    config([
        'services.telegram.token' => 'secret-token',
        'services.telegram.chat_id' => '4242',
        'ops.invariants.no_orders_hours' => 6,
    ]);
});

it('succeeds and records an ok result when every invariant holds', function () {
    config(['ops.invariants.no_orders_hours' => 0]);
    Http::fake();

    $this->artisan('ops:check-invariants')->expectsOutputToContain('hold')->assertSuccessful();

    expect(Cache::get(SystemInfo::INVARIANTS_CACHE_KEY)['status'])->toBe('ok');
    Http::assertNothingSent();
});

it('logs an error and alerts on Telegram for a violated invariant', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    Log::spy();

    $this->artisan('ops:check-invariants')->assertSuccessful();

    expect(Cache::get(SystemInfo::INVARIANTS_CACHE_KEY)['status'])->toBe('problem');
    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context) => str_contains($message, 'No paid order') && $context['invariant'] === 'no-orders')->once();
    Http::assertSent(fn ($request) => str_contains($request['text'], 'No paid order'));
});

it('alerts once per window while the problem lasts', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    $this->artisan('ops:check-invariants')->assertSuccessful();
    $this->artisan('ops:check-invariants')->assertSuccessful();

    Http::assertSentCount(1);
});

it('is scheduled every fifteen minutes', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command, 'ops:check-invariants'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('*/15 * * * *');
});
