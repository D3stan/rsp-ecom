<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('pings the heartbeat URL', function () {
    config(['ops.heartbeat_url' => 'https://hc-ping.com/abc']);
    Http::fake(['hc-ping.com/*' => Http::response('OK')]);

    $this->artisan('ops:heartbeat')->assertSuccessful();

    Http::assertSent(fn ($request) => $request->url() === 'https://hc-ping.com/abc');
});

it('does nothing without a heartbeat URL', function () {
    Http::fake();

    $this->artisan('ops:heartbeat')->expectsOutputToContain('nothing to ping')->assertSuccessful();

    Http::assertNothingSent();
});

it('fails quietly when the ping cannot be delivered', function () {
    config(['ops.heartbeat_url' => 'https://hc-ping.com/abc']);
    Http::fake(['hc-ping.com/*' => fn () => throw new ConnectionException('unreachable')]);

    $this->artisan('ops:heartbeat')->assertFailed();
});

it('is scheduled every five minutes', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command, 'ops:heartbeat'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('*/5 * * * *');
});
