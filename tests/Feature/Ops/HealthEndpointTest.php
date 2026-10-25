<?php

use App\Settings\SystemInfo;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config(['cashier.secret' => 'sk_test_123']);
});

function recordQueueCheck(string $status, int $minutesAgo = 0): void
{
    Cache::put(SystemInfo::QUEUE_HEALTH_CACHE_KEY, [
        'status' => $status,
        'checked_at' => now()->subMinutes($minutesAgo)->toIso8601String(),
    ], now()->addDay());
}

it('reports ok when every dependency is healthy', function () {
    recordQueueCheck('ok');

    $this->getJson(route('api.health'))
        ->assertOk()
        ->assertExactJson(['status' => 'ok', 'checks' => [
            'database' => 'ok', 'cache' => 'ok', 'storage' => 'ok', 'queue' => 'ok', 'scheduler' => 'ok', 'stripe' => 'ok',
        ]]);
});

it('does not hold a missing queue check against the app right after a deploy', function () {
    $this->getJson(route('api.health'))->assertOk()->assertJsonPath('status', 'ok');
});

it('returns 503 when the last queue check found a problem', function () {
    recordQueueCheck('problem');

    $this->getJson(route('api.health'))
        ->assertStatus(503)
        ->assertJsonPath('status', 'fail')
        ->assertJsonPath('checks.queue', 'fail')
        ->assertJsonPath('checks.scheduler', 'ok');
});

it('returns 503 when the scheduler has not recorded a queue check for too long', function () {
    recordQueueCheck('ok', minutesAgo: 20);

    $this->getJson(route('api.health'))
        ->assertStatus(503)
        ->assertJsonPath('checks.scheduler', 'fail')
        ->assertJsonPath('checks.queue', 'ok');
});

it('returns 503 when the Stripe secret is missing', function () {
    config(['cashier.secret' => null]);

    $this->getJson(route('api.health'))
        ->assertStatus(503)
        ->assertJsonPath('checks.stripe', 'fail');
});
