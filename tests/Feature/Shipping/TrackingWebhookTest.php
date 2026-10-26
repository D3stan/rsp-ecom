<?php

use App\Jobs\SendOrderEmail;
use App\Models\Order;
use App\Models\ShipmentTrackingEvent;
use App\Shipping\Tracking\Drivers\FakeProvider;
use App\Shipping\Tracking\Exceptions\TrackingProviderUnavailable;
use App\Shipping\Tracking\Jobs\SyncOrderTracking;
use App\Shipping\Tracking\ShipmentTrackingService;
use App\Shipping\Tracking\TrackingManager;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Shipping\Support\TrackingFixtures as Fx;

const WEBHOOK_SECRET = 'test-secret';

function trkPayload(string $number = 'TRK-1001', string $status = 'in_transit', ?string $carrier = null, string $id = 'w1'): string
{
    return json_encode(['updates' => [[
        'tracking_number' => $number,
        'carrier' => $carrier,
        'status' => $status,
        'events' => [['id' => $id, 'status' => $status, 'description' => 'Scan', 'location' => 'Hub', 'occurred_at' => now()->subHour()->toIso8601String()]],
    ]]]);
}

function trkPost(string $driver, string $body, ?string $secret = WEBHOOK_SECRET)
{
    $headers = ['CONTENT_TYPE' => 'application/json'];

    if ($secret !== null) {
        $headers['HTTP_X_FAKE_SIGNATURE'] = 'sha256='.hash_hmac('sha256', $body, $secret);
    }

    return test()->call('POST', "/webhooks/tracking/{$driver}", [], [], [], $headers, $body);
}

beforeEach(function () {
    Queue::fake();
    config(['shipping.tracking.driver' => 'fake', 'shipping.tracking.drivers.fake.webhook_secret' => WEBHOOK_SECRET]);
});

it('stores the pushed events and updates the order', function () {
    $order = Fx::shippedOrder();

    trkPost('fake', trkPayload())->assertOk()->assertJson(['status' => 'ok', 'received' => 1, 'applied' => 1]);

    expect($order->fresh()->tracking_status)->toBe('in_transit')->and(ShipmentTrackingEvent::count())->toBe(1);
});

it('is idempotent when the carrier retries the same push', function () {
    $order = Fx::shippedOrder();

    trkPost('fake', trkPayload())->assertOk();
    trkPost('fake', trkPayload())->assertOk();

    expect(ShipmentTrackingEvent::count())->toBe(1);
});

it('delivers once when the same delivery arrives by webhook and by poll', function () {
    $order = Fx::shippedOrder(['tracking_number' => 'ZZ-DELIVERED']);

    trkPost('fake', trkPayload('ZZ-DELIVERED', 'delivered', null, 'fake-zz-delivered-2'))->assertOk();
    app()->call([new SyncOrderTracking($order->id), 'handle']); // poll afterwards: order no longer shipped

    expect($order->fresh()->status)->toBe('delivered');
    Queue::assertPushed(SendOrderEmail::class, 1);
});

it('answers 200 for unknown tracking numbers and ignores orders that are not shipped', function () {
    Fx::shippedOrder(['status' => 'delivered']);

    trkPost('fake', trkPayload('NOBODY'))->assertOk()->assertJson(['applied' => 0]);
    trkPost('fake', trkPayload('TRK-1001'))->assertOk()->assertJson(['applied' => 0]);
    expect(ShipmentTrackingEvent::count())->toBe(0);
});

it('answers 404 for drivers without webhook support and for unknown drivers', function () {
    trkPost('manual', '{}')->assertNotFound();
    trkPost('null', '{}')->assertNotFound();
    trkPost('nope', '{}')->assertNotFound();
});

it('answers 404 for the fake driver in production', function () {
    app()->detectEnvironment(fn () => 'production');

    trkPost('fake', trkPayload())->assertNotFound();
});

it('answers 403 for a missing or wrong signature and stores nothing', function () {
    Fx::shippedOrder();

    trkPost('fake', trkPayload(), null)->assertForbidden();
    trkPost('fake', trkPayload(), 'wrong-secret')->assertForbidden();
    expect(ShipmentTrackingEvent::count())->toBe(0);
});

it('fails closed when no secret is configured, even for a correctly signed request', function () {
    config(['shipping.tracking.drivers.fake.webhook_secret' => '']);
    Fx::shippedOrder();

    trkPost('fake', trkPayload(), '')->assertForbidden();
    trkPost('fake', trkPayload(), null)->assertForbidden();
});

it('answers 403 in production when the driver has no webhook_secret, whatever the adapter says', function () {
    $permissive = new class extends FakeProvider
    {
        public function verifySignature(Request $request): bool
        {
            return true;
        }
    };
    app(TrackingManager::class)->extend('permissive', fn () => $permissive);
    app()->detectEnvironment(fn () => 'production');

    trkPost('permissive', trkPayload(), null)->assertForbidden();

    config(['shipping.tracking.drivers.permissive.webhook_secret' => 'set']);
    trkPost('permissive', trkPayload(), null)->assertOk();
});

it('answers 422 for a malformed payload and 503 when the adapter is temporarily unavailable', function () {
    trkPost('fake', 'not json')->assertStatus(422);
    trkPost('fake', json_encode(['updates' => [['tracking_number' => '']]]))->assertStatus(422);

    $flaky = new class extends FakeProvider
    {
        public function parse(Request $request): array
        {
            throw new TrackingProviderUnavailable('later');
        }

        public function verifySignature(Request $request): bool
        {
            return true;
        }
    };
    app(TrackingManager::class)->extend('flaky', fn () => $flaky);
    trkPost('flaky', '{}')->assertStatus(503);
});

it('answers 5xx when persisting fails so the carrier retries, and logs no payload', function () {
    Fx::shippedOrder();
    Log::spy();
    $service = Mockery::mock(ShipmentTrackingService::class)->makePartial();
    $service->shouldReceive('apply')->andThrow(new RuntimeException('db down'));
    app()->instance(ShipmentTrackingService::class, $service);

    trkPost('fake', trkPayload('TRK-1001'))->assertStatus(500);

    Log::shouldHaveReceived('error')->withArgs(fn ($m, $ctx = []) => ! str_contains(json_encode($ctx), 'TRK-1001') && ! str_contains(json_encode($ctx), WEBHOOK_SECRET))->once();
});

it('rejects oversized bodies', function () {
    trkPost('fake', str_repeat('x', 1_048_577))->assertStatus(413);
});

it('is rate limited and registered as a CSRF-exempt route', function () {
    $route = app('router')->getRoutes()->getByName('webhooks.tracking');

    expect($route->gatherMiddleware())->toContain('throttle:120,1')
        ->and($route->uri())->toBe('webhooks/tracking/{driver}')
        ->and($route->methods())->toBe(['POST']);
});

it('accepts a POST without CSRF token (middleware really enforced, as in production)', function () {
    // Laravel skips CSRF while running unit tests; force the real check to prove the exemption works.
    $enforcing = new class(app(), app('encrypter')) extends PreventRequestForgery
    {
        protected function runningUnitTests()
        {
            return false;
        }
    };
    app()->bind(PreventRequestForgery::class, fn () => $enforcing);
    Fx::shippedOrder();

    trkPost('fake', trkPayload())->assertOk();

    // control: a normal POST route is still protected by the same middleware
    $this->post('/contact', [])->assertStatus(419);
});

it('does not echo secrets, headers or payloads in error responses', function () {
    trkPost('fake', trkPayload(), 'wrong')->assertForbidden()->assertExactJson(['error' => 'forbidden']);
});

it('isolates webhooks onto orders with tracking_driver null based on the default driver', function () {
    $order = Fx::shippedOrder(['tracking_number' => 'TRK-WEBHOOK-ISO', 'tracking_driver' => null]);

    // 1. Matches webhook from the default driver ('fake')
    config(['shipping.tracking.driver' => 'fake']);
    trkPost('fake', trkPayload('TRK-WEBHOOK-ISO'))->assertOk()->assertJson(['status' => 'ok', 'applied' => 1]);
    expect($order->fresh()->tracking_status)->toBe('in_transit');

    // Reset tracking status for next check
    $order->fresh()->update(['tracking_status' => null]);

    // 2. Does NOT match webhook when the default driver is 'manual'
    config(['shipping.tracking.driver' => 'manual']);
    trkPost('fake', trkPayload('TRK-WEBHOOK-ISO'))->assertOk()->assertJson(['status' => 'ok', 'applied' => 0]);
    expect($order->fresh()->tracking_status)->toBeNull();

    // 3. Does NOT match webhook from a non-default provider
    config(['shipping.tracking.driver' => 'fake']);
    $altProvider = new class extends FakeProvider
    {
        public function name(): string
        {
            return 'alt';
        }

        public function verifySignature(Request $request): bool
        {
            return true;
        }
    };
    app(TrackingManager::class)->extend('alt', fn () => $altProvider);
    config(['shipping.tracking.drivers.alt.webhook_secret' => 'test-secret']);

    trkPost('alt', trkPayload('TRK-WEBHOOK-ISO'), null)->assertOk()->assertJson(['status' => 'ok', 'applied' => 0]);
    expect($order->fresh()->tracking_status)->toBeNull();
});
