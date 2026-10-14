<?php

use App\Jobs\SendOrderEmail;
use App\Models\Order;
use App\Models\OrderEmail;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->order = Order::factory()->create([
        'user_id' => null,
        'guest_email' => 'guest@example.test',
        'payment_status' => 'succeeded',
        'status' => 'processing',
        'tracking_number' => null,
    ]);
    Queue::fake();
});

function shipPayload(): array
{
    return [
        'sender_info' => ['company_name' => 'Shop', 'address' => 'Via Roma 1', 'phone' => '123', 'email' => 'shop@example.test'],
        'customer_info' => ['name' => 'Guest', 'email' => 'guest@example.test'],
        'shipping_address' => [
            'first_name' => 'A', 'last_name' => 'B', 'address_line_1' => 'Via 1', 'city' => 'Roma',
            'state' => 'RM', 'postal_code' => '00100', 'country' => 'IT',
        ],
        'box_info' => ['carrier' => 'GLS'],
    ];
}

it('queues one shipped email when status changes to shipped and none on identical re-saves', function () {
    $this->actingAs($this->admin)->put(route('admin.orders.update', $this->order), ['status' => 'shipped'])
        ->assertSessionHas('success')->assertSessionMissing('warning');
    $this->actingAs($this->admin)->put(route('admin.orders.update', $this->order), ['status' => 'shipped']);
    $this->actingAs($this->admin)->put(route('admin.orders.update', $this->order), ['status' => 'shipped', 'notes' => 'x']);

    Queue::assertPushed(SendOrderEmail::class, 1);
});

it('re-sends the shipped email only when the tracking number changes', function () {
    $url = route('admin.orders.update', $this->order);

    $this->actingAs($this->admin)->put($url, ['status' => 'shipped']);
    $this->actingAs($this->admin)->put($url, ['tracking_number' => 'TRK1']);   // added -> new fingerprint
    $this->actingAs($this->admin)->put($url, ['tracking_number' => 'TRK1']);   // unchanged
    $this->actingAs($this->admin)->put($url, ['tracking_number' => 'TRK2']);   // corrected

    Queue::assertPushed(SendOrderEmail::class, 3);
    expect(OrderEmail::pluck('fingerprint')->unique())->toHaveCount(3);
});

it('adding a tracking number at once flips to shipped and sends a single email', function () {
    $this->actingAs($this->admin)->put(route('admin.orders.update', $this->order), ['tracking_number' => 'TRK9']);

    expect($this->order->fresh()->status)->toBe('shipped');
    Queue::assertPushed(SendOrderEmail::class, 1);
    Queue::assertPushed(SendOrderEmail::class, fn ($job) => $job->trackingNumber === 'TRK9');
});

it('does not send a shipped email when only a non-shipped order is edited', function () {
    $this->order->update(['status' => 'delivered', 'tracking_number' => 'OLD']);

    $this->actingAs($this->admin)->put(route('admin.orders.update', $this->order), ['tracking_number' => 'NEW']);

    // (the delivered email queued by the status change above is a different type: Lifecycle tests)
    Queue::assertNotPushed(SendOrderEmail::class, fn ($job) => $job->type === OrderEmail::TYPE_SHIPPED);
});

it('processShip queues a single shipped email and a repeat does not duplicate it', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), shipPayload())
        ->assertSessionHas('success')->assertSessionMissing('warning');
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), shipPayload());

    Queue::assertPushed(SendOrderEmail::class, 1);
});

it('an update after processShip with unchanged data does not send a second email', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), shipPayload());
    $this->actingAs($this->admin)->put(route('admin.orders.update', $this->order), ['status' => 'shipped']);

    Queue::assertPushed(SendOrderEmail::class, 1);
});

it('sends nothing and shows no warning when the shipped toggle is off', function () {
    settings()->set('email.order_shipped', false);

    $this->actingAs($this->admin)->put(route('admin.orders.update', $this->order), ['status' => 'shipped'])
        ->assertSessionMissing('warning');
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), shipPayload())
        ->assertSessionMissing('warning');

    Queue::assertNothingPushed();
});

it('warns the admin when the shipped email could not be queued', function () {
    $this->order->update(['guest_email' => null]);

    $this->actingAs($this->admin)->put(route('admin.orders.update', $this->order), ['status' => 'shipped'])
        ->assertSessionHas('success')
        ->assertSessionHas('warning');
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), shipPayload())
        ->assertSessionHas('warning');
});

// --- bulk / quick status changes go through the same deduped path ------------------------------

// The bulk route is shadowed by the resource route `orders/{order}` (PATCH admin/orders/bulk-status
// resolves to update() and 404s), so the controller method is called directly.
function callBulkStatus(array $ids, string $status): void
{
    $request = Illuminate\Http\Request::create('/admin/orders/bulk-status', 'PATCH', ['order_ids' => $ids, 'status' => $status]);
    $request->setLaravelSession(app('session.store'));

    app(App\Http\Controllers\Admin\OrdersController::class)->bulkUpdateStatus($request);
}

it('bulk status shipped queues one shipped email per newly shipped order, once', function () {
    $second = Order::factory()->create(['user_id' => null, 'guest_email' => 'two@example.test', 'payment_status' => 'succeeded', 'status' => 'processing']);
    $already = Order::factory()->create(['user_id' => null, 'guest_email' => 'three@example.test', 'payment_status' => 'succeeded', 'status' => 'shipped']);
    $ids = [$this->order->id, $second->id, $already->id];

    $this->actingAs($this->admin);
    callBulkStatus($ids, 'shipped');
    expect(session('success'))->not->toBeNull()->and(session('warning'))->toBeNull();
    callBulkStatus($ids, 'shipped');

    Queue::assertPushed(SendOrderEmail::class, 2);
    expect(OrderEmail::pluck('order_id')->sort()->values()->all())->toBe(collect([$this->order->id, $second->id])->sort()->values()->all());
    expect(OrderEmail::pluck('fingerprint')->unique()->all())->toBe([OrderEmail::shippedFingerprint(null)]);
});

it('bulk status other than shipped, or with the toggle off, queues nothing', function () {
    $this->actingAs($this->admin);
    callBulkStatus([$this->order->id], 'delivered');
    Queue::assertNotPushed(SendOrderEmail::class, fn ($job) => $job->type === OrderEmail::TYPE_SHIPPED);

    $this->order->update(['status' => 'processing']);
    settings()->set('email.order_shipped', false);
    callBulkStatus([$this->order->id], 'shipped');
    expect(session('warning'))->toBeNull();
    Queue::assertNotPushed(SendOrderEmail::class, fn ($job) => $job->type === OrderEmail::TYPE_SHIPPED);
});

it('bulk status collects mail failures into one warning', function () {
    $noEmail = Order::factory()->create(['user_id' => null, 'guest_email' => null, 'payment_status' => 'succeeded', 'status' => 'processing']);

    $this->actingAs($this->admin);
    callBulkStatus([$this->order->id, $noEmail->id], 'shipped');

    expect(session('warning'))->toContain('1 shipping email');
    Queue::assertPushed(SendOrderEmail::class, 1);
});

it('the single status endpoint also queues the shipped email once', function () {
    $url = route('admin.orders.update-status', $this->order);

    $this->actingAs($this->admin)->patch($url, ['status' => 'shipped'])->assertSessionMissing('warning');
    $this->actingAs($this->admin)->patch($url, ['status' => 'shipped']);

    Queue::assertPushed(SendOrderEmail::class, 1);
});

describe('carrier in the shipped fingerprint', function () {
    beforeEach(function () {
        config(['shipping.carriers' => [
            'acme' => ['name' => 'Acme Courier', 'tracking_url' => 'https://track.example.test/?n={number}'],
            'zeta' => ['name' => 'Zeta Express'],
        ]]);
        $this->url = route('admin.orders.update', $this->order);
    });

    it('sends one new mail when a carrier is added to an already shipped order, and none when it is saved again', function () {
        $this->actingAs($this->admin)->put($this->url, ['tracking_number' => 'TRK1']);       // first mail, no carrier
        $this->actingAs($this->admin)->put($this->url, ['tracking_number' => 'TRK1', 'tracking_carrier' => 'acme']);  // carrier added
        $this->actingAs($this->admin)->put($this->url, ['tracking_number' => 'TRK1', 'tracking_carrier' => 'acme']);  // unchanged

        Queue::assertPushed(SendOrderEmail::class, 2);
        expect(OrderEmail::pluck('fingerprint')->all())->toEqualCanonicalizing([
            OrderEmail::shippedFingerprint('TRK1'),
            OrderEmail::shippedFingerprint('TRK1', 'acme'),
        ]);
    });

    it('sends a new mail when the carrier changes to another one', function () {
        $this->actingAs($this->admin)->put($this->url, ['tracking_number' => 'TRK1', 'tracking_carrier' => 'acme']);
        $this->actingAs($this->admin)->put($this->url, ['tracking_number' => 'TRK1', 'tracking_carrier' => 'zeta']);

        Queue::assertPushed(SendOrderEmail::class, 2);
    });

    it('keeps the legacy fingerprint for an order without a carrier and sends nothing extra', function () {
        $this->actingAs($this->admin)->put($this->url, ['tracking_number' => 'TRK1']);
        $this->actingAs($this->admin)->put($this->url, ['tracking_number' => 'TRK1', 'tracking_carrier' => null]);
        $this->actingAs($this->admin)->put($this->url, ['tracking_number' => 'TRK1', 'tracking_carrier' => '']);

        Queue::assertPushed(SendOrderEmail::class, 1);
        expect(OrderEmail::pluck('fingerprint')->all())->toBe([OrderEmail::shippedFingerprint('TRK1')])
            ->and(OrderEmail::shippedFingerprint('TRK1'))->toBe(substr(sha1('shipped|TRK1'), 0, 40));
    });

    it('still sends for a changed tracking number on an order with a carrier', function () {
        $this->actingAs($this->admin)->put($this->url, ['tracking_number' => 'TRK1', 'tracking_carrier' => 'acme']);
        $this->actingAs($this->admin)->put($this->url, ['tracking_number' => 'TRK2', 'tracking_carrier' => 'acme']);

        Queue::assertPushed(SendOrderEmail::class, 2);
        expect(OrderEmail::pluck('fingerprint')->unique())->toHaveCount(2);
    });

    it('sends nothing when the carrier changes on an order that is not shipped', function () {
        $this->actingAs($this->admin)->put($this->url, ['tracking_carrier' => 'acme'])->assertSessionHas('success');

        expect($this->order->fresh())->status->toBe('processing')->tracking_carrier->toBe('acme');
        Queue::assertNothingPushed();
    });

    it('processShip uses the carrier it just stored in the fingerprint', function () {
        $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), array_merge(shipPayload(), ['tracking_carrier' => 'zeta']));

        expect(OrderEmail::pluck('fingerprint')->all())->toBe([OrderEmail::shippedFingerprint(null, 'zeta')]);
    });
});
