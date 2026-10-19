<?php

/*
|--------------------------------------------------------------------------
| Coexisting Shipping Providers (Per-Order & Per-Shipment Routing)
|--------------------------------------------------------------------------
|
| Verifies that multiple tracking and label providers ('spediamo', 'rehearsal', 'fake', 'manual',
| and custom adapters) coexist in the same installation and route label creation, label cancellation,
| background polling, tracking URLs, and webhooks by the provider stored on each order/shipment.
*/

use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentTrackingEvent;
use App\Models\User;
use App\Providers\LabelServiceProvider;
use App\Providers\TrackingServiceProvider;
use App\Shipping\Contracts\ShippingProviderManifest;
use App\Shipping\Labels\Contracts\CancelsShipments;
use App\Shipping\Labels\Contracts\LabelProvider;
use App\Shipping\Labels\DTO\LabelDocument;
use App\Shipping\Labels\DTO\ShipmentRequest;
use App\Shipping\Labels\DTO\ShipmentResult;
use App\Shipping\Labels\LabelManager;
use App\Shipping\Tracking\Contracts\TrackingProvider;
use App\Shipping\Tracking\Drivers\FakeProvider;
use App\Shipping\Tracking\TrackingManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Shipping\Support\TrackingFixtures as Fx;
use Tests\Support\Shipping\RehearsalProvider;

beforeEach(function () {
    Storage::fake('local');
    $this->admin = User::factory()->create(['role' => 'admin']);

    config([
        'shipping.carriers' => [
            'acme' => [
                'name' => 'Acme Courier',
                'tracking_url' => 'https://manual-track.example.test/?n={number}',
            ],
        ],
        'shipping.tracking.drivers.spediamo' => [
            'base_url' => 'https://spediamo.test',
            'token' => 'spd-bearer-token',
            'retries' => 0,
            'retry_sleep_ms' => 0,
        ],
        'shipping.tracking.drivers.rehearsal' => [
            'base_url' => 'https://rehearsal.test',
            'api_key' => 'reh-api-key',
            'retries' => 0,
            'retry_sleep_ms' => 0,
        ],
    ]);

    app(TrackingManager::class)->extend(
        'rehearsal',
        fn () => new RehearsalProvider(app(TrackingManager::class)->driverConfig('rehearsal')),
    );
});

it('polls coexisting orders with their own tracking_driver and skips manual orders even when default driver is manual', function () {
    Mail::fake();
    // Global default is passive ('manual')
    config(['shipping.tracking.driver' => 'manual']);

    Http::fake([
        'spediamo.test/*' => Http::response([
            'status' => 'CURRENT',
            'tracking' => [
                ['date' => '2026-09-01', 'time' => ['hour' => 9, 'minute' => 0, 'second' => 0], 'description' => 'Spediamo Hub', 'site' => 'Milano', 'status' => 'CURRENT'],
            ],
        ]),
        'rehearsal.test/*' => Http::response([
            'state' => 'CLOSED',
            'scans' => [
                ['at' => '01/09/2026 11:00', 'what' => 'Rehearsal Delivered', 'where' => 'Roma', 'state' => 'CLOSED'],
            ],
        ]),
    ]);

    $orderSpediamo = Fx::shippedOrder([
        'tracking_number' => 'TRK-SPD-1',
        'tracking_carrier' => 'acme',
        'tracking_driver' => 'spediamo',
    ]);
    $orderRehearsal = Fx::shippedOrder([
        'tracking_number' => 'TRK-REH-1',
        'tracking_carrier' => 'acme',
        'tracking_driver' => 'rehearsal',
    ]);
    $orderManual = Fx::shippedOrder([
        'tracking_number' => 'TRK-MAN-1',
        'tracking_carrier' => 'acme',
        'tracking_driver' => 'manual',
    ]);

    expect(app(TrackingManager::class)->canPoll())->toBeFalse()
        ->and(app(TrackingManager::class)->canPollAny())->toBeTrue();

    $this->artisan('tracking:sync')->assertSuccessful();

    expect($orderSpediamo->fresh()->tracking_status)->toBe('in_transit')
        ->and(ShipmentTrackingEvent::where('order_id', $orderSpediamo->id)->pluck('provider')->all())->toBe(['spediamo'])
        ->and($orderRehearsal->fresh()->status)->toBe('delivered')
        ->and($orderRehearsal->fresh()->tracking_status)->toBe('delivered')
        ->and(ShipmentTrackingEvent::where('order_id', $orderRehearsal->id)->pluck('provider')->all())->toBe(['rehearsal'])
        ->and($orderManual->fresh()->tracking_status)->toBeNull()
        ->and($orderManual->fresh()->tracking_last_synced_at)->toBeNull()
        ->and(ShipmentTrackingEvent::where('order_id', $orderManual->id)->count())->toBe(0);

    Http::assertSentCount(2);
});

it('keeps shipment.driver as manual when typing a tracking number paired with an API tracking_driver', function () {
    Queue::fake();
    config(['shipping.tracking.driver' => 'manual']);

    $order = Order::factory()->create([
        'status' => 'processing',
        'payment_status' => 'succeeded',
    ]);
    $order->orderItems()->create(['product_name' => 'Widget', 'quantity' => 1, 'price' => 10, 'total' => 10]);

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $order), [
        'tracking_carrier' => 'acme',
        'tracking_number' => 'EXT-PORTAL-100',
        'tracking_driver' => 'spediamo',
        'create_label' => false,
    ])->assertRedirect(route('admin.orders.show', $order));

    $fresh = $order->fresh();
    $shipment = $fresh->shipments()->first();

    // Shipment was typed manually, so shipment.driver is 'manual' (isManual() === true),
    // while order.tracking_driver is 'spediamo' for API polling!
    expect($fresh->status)->toBe('shipped')
        ->and($fresh->tracking_number)->toBe('EXT-PORTAL-100')
        ->and($fresh->tracking_driver)->toBe('spediamo')
        ->and($shipment->driver)->toBe(Shipment::DRIVER_MANUAL)
        ->and($shipment->isManual())->toBeTrue();

    // Editing the tracking number on Edit.tsx updates the manual shipment and resets tracking state
    $fresh->forceFill(['tracking_status' => 'in_transit', 'tracking_next_sync_at' => now()->addHours(2)])->saveQuietly();

    $this->actingAs($this->admin)->patch(route('admin.orders.update', $fresh), [
        'status' => 'shipped',
        'tracking_carrier' => 'acme',
        'tracking_number' => 'EXT-PORTAL-200',
        'tracking_driver' => 'rehearsal',
    ])->assertSessionHas('success');

    $updated = $fresh->fresh();
    expect($updated->tracking_number)->toBe('EXT-PORTAL-200')
        ->and($updated->tracking_driver)->toBe('rehearsal')
        ->and($updated->tracking_status)->toBeNull()
        ->and($updated->tracking_next_sync_at)->toBeNull()
        ->and($shipment->fresh()->tracking_number)->toBe('EXT-PORTAL-200');
});

it('creates and cancels labels across coexisting label providers regardless of global default label_driver', function () {
    Queue::fake();
    settings()->set('shipping.labels_enabled', true);
    settings()->set('shipping.label_driver', 'fake');

    $cancelledByAlpha = [];

    $alphaProvider = new class($cancelledByAlpha) implements CancelsShipments, LabelProvider
    {
        public function __construct(public array &$cancelled) {}

        public function name(): string
        {
            return 'alpha_labels';
        }

        public function supports(string $carrierCode): bool
        {
            return true;
        }

        public function createShipment(ShipmentRequest $request): ShipmentResult
        {
            return new ShipmentResult(
                externalId: 'ALPHA-EXT-1',
                trackingNumber: 'ALPHA-TRK-99',
                carrierCode: $request->carrierCode,
            );
        }

        public function label(ShipmentResult|string $externalId): LabelDocument
        {
            return new LabelDocument('%PDF-1.4 alpha label', 'alpha.pdf');
        }

        public function cancelShipment(string $externalId): void
        {
            $this->cancelled[] = $externalId;
        }
    };

    app(LabelManager::class)->extend('alpha_labels', fn () => $alphaProvider);

    $orderFake = Order::factory()->create(['status' => 'processing', 'payment_status' => 'succeeded']);
    $orderFake->orderItems()->create(['product_name' => 'A', 'quantity' => 1, 'price' => 10, 'total' => 10]);

    $orderAlpha = Order::factory()->create(['status' => 'processing', 'payment_status' => 'succeeded']);
    $orderAlpha->orderItems()->create(['product_name' => 'B', 'quantity' => 1, 'price' => 10, 'total' => 10]);

    // Ship Order 1 with 'fake' and Order 2 with 'alpha_labels'
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $orderFake), [
        'tracking_carrier' => 'acme',
        'create_label' => true,
        'label_driver' => 'fake',
        'parcels' => [['weight' => 1.0]],
    ])->assertSessionHas('success');

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $orderAlpha), [
        'tracking_carrier' => 'acme',
        'create_label' => true,
        'label_driver' => 'alpha_labels',
        'parcels' => [['weight' => 2.0]],
    ])->assertSessionHas('success');

    $shipmentFake = $orderFake->fresh()->shipments()->first();
    $shipmentAlpha = $orderAlpha->fresh()->shipments()->first();

    expect($shipmentFake->driver)->toBe('fake')
        ->and($shipmentAlpha->driver)->toBe('alpha_labels')
        ->and($orderAlpha->fresh()->tracking_number)->toBe('ALPHA-TRK-99');

    // Even while global default label_driver is 'fake', cancelling $shipmentAlpha routes to 'alpha_labels'
    $this->actingAs($this->admin)
        ->delete(route('admin.orders.shipments.cancel', [$orderAlpha, $shipmentAlpha]))
        ->assertSessionHas('success');

    expect($shipmentAlpha->fresh()->status)->toBe(Shipment::STATUS_CANCELLED)
        ->and($alphaProvider->cancelled)->toBe(['ALPHA-EXT-1']);
});

it('resolves Order::trackingUrl() using each orders own tracking_driver', function () {
    config(['shipping.tracking.driver' => 'manual']);

    $orderManual = Fx::shippedOrder([
        'tracking_number' => 'NUM-1',
        'tracking_carrier' => 'acme',
        'tracking_driver' => 'manual',
    ]);
    $orderFake = Fx::shippedOrder([
        'tracking_number' => 'NUM-1',
        'tracking_carrier' => 'acme',
        'tracking_driver' => 'fake',
    ]);
    $orderNull = Fx::shippedOrder([
        'tracking_number' => 'NUM-1',
        'tracking_carrier' => 'acme',
        'tracking_driver' => 'null',
    ]);

    expect($orderManual->trackingUrl())->toBe('https://manual-track.example.test/?n=NUM-1')
        ->and($orderFake->trackingUrl())->toBe('https://tracking.example.test/track/NUM-1')
        ->and($orderNull->trackingUrl())->toBeNull();
});

it('isolates webhooks by provider so colliding tracking numbers on manual or other providers are not touched', function () {
    Queue::fake();
    config([
        'shipping.tracking.driver' => 'manual',
        'shipping.tracking.drivers.fake.webhook_secret' => 'fake-secret',
        'shipping.tracking.drivers.alpha_webhook.webhook_secret' => 'alpha-secret',
    ]);

    $alphaWebhook = new class(['webhook_secret' => 'alpha-secret']) extends FakeProvider
    {
        public function name(): string
        {
            return 'alpha_webhook';
        }

        public function verifySignature(Request $request): bool
        {
            return true;
        }
    };
    app(TrackingManager::class)->extend('alpha_webhook', fn () => $alphaWebhook);

    $orderFake = Fx::shippedOrder(['tracking_number' => 'SHARED-999', 'tracking_driver' => 'fake']);
    $orderManual = Fx::shippedOrder(['tracking_number' => 'SHARED-999', 'tracking_driver' => 'manual']);
    $orderAlpha = Fx::shippedOrder(['tracking_number' => 'SHARED-999', 'tracking_driver' => 'alpha_webhook']);

    $body = json_encode(['updates' => [[
        'tracking_number' => 'SHARED-999',
        'status' => 'in_transit',
        'events' => [['id' => 'ev-1', 'status' => 'in_transit', 'description' => 'Fake scan', 'location' => 'Hub', 'occurred_at' => now()->subHour()->toIso8601String()]],
    ]]]);
    $sig = 'sha256='.hash_hmac('sha256', $body, 'fake-secret');

    $this->call('POST', '/webhooks/tracking/fake', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_FAKE_SIGNATURE' => $sig,
    ], $body)->assertOk()->assertJson(['received' => 1, 'applied' => 1]);

    expect($orderFake->fresh()->tracking_status)->toBe('in_transit')
        ->and($orderManual->fresh()->tracking_status)->toBeNull()
        ->and($orderAlpha->fresh()->tracking_status)->toBeNull();
});

it('never leaks tracking_driver or provider names in customer-facing order payloads', function () {
    $order = Fx::shippedOrder([
        'tracking_number' => 'PRIV-100',
        'tracking_carrier' => 'acme',
        'tracking_driver' => 'spediamo',
    ]);

    $payload = json_encode(array_merge($order->toCustomerArray(), $order->customerTrackingArray()));

    expect($payload)->not->toContain('tracking_driver')
        ->and(strtolower($payload))->not->toContain('spediamo');
});

it('registers partial manifests only for their declared capabilities (tracking or labels)', function () {
    $trackingOnlyManifest = new class implements ShippingProviderManifest
    {
        public static function name(): string
        {
            return 'tracking_only_carrier';
        }

        public static function hasTracking(): bool
        {
            return true;
        }

        public static function hasLabels(): bool
        {
            return false;
        }

        public static function createTrackingDriver(array $config): ?TrackingProvider
        {
            return null;
        }

        public static function createLabelDriver(array $config): ?LabelProvider
        {
            return null;
        }
    };

    $labelOnlyManifest = new class implements ShippingProviderManifest
    {
        public static function name(): string
        {
            return 'label_only_carrier';
        }

        public static function hasTracking(): bool
        {
            return false;
        }

        public static function hasLabels(): bool
        {
            return true;
        }

        public static function createTrackingDriver(array $config): ?TrackingProvider
        {
            return null;
        }

        public static function createLabelDriver(array $config): ?LabelProvider
        {
            return null;
        }
    };

    config([
        'shipping.manifests' => [
            get_class($trackingOnlyManifest),
            get_class($labelOnlyManifest),
        ],
    ]);

    (new TrackingServiceProvider(app()))->register();
    (new LabelServiceProvider(app()))->register();

    $trackingManager = app(TrackingManager::class);
    $labelManager = app(LabelManager::class);

    // Tracking-only manifest is registered in TrackingManager, NOT in LabelManager
    expect($trackingManager->driverNames())->toContain('tracking_only_carrier')
        ->and($trackingManager->selectableDriverNames())->toContain('tracking_only_carrier')
        ->and($labelManager->driverNames())->not->toContain('tracking_only_carrier');

    // Label-only manifest is registered in LabelManager, NOT in TrackingManager
    expect($labelManager->driverNames())->toContain('label_only_carrier')
        ->and($trackingManager->driverNames())->not->toContain('label_only_carrier')
        ->and($trackingManager->selectableDriverNames())->not->toContain('label_only_carrier');
});
