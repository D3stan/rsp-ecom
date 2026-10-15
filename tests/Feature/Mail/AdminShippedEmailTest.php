<?php

use App\Jobs\SendOrderEmail;
use App\Models\Order;
use App\Models\OrderEmail;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

/*
 * The shipped email: the ship form (ShippingService) sends one; later changes of the tracking number or carrier of a
 * shipped order send one more each (deduped on number + carrier). "shipped" is never set by the edit form, the status
 * endpoint or the bulk action (tests/Feature/Shipping/ShipFormTest.php).
 */

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    config(['shipping.carriers' => [
        'acme' => ['name' => 'Acme Courier', 'tracking_url' => 'https://track.example.test/?n={number}'],
        'zeta' => ['name' => 'Zeta Express'],
    ]]);
    $this->order = Order::factory()->create([
        'user_id' => null,
        'guest_email' => 'guest@example.test',
        'payment_status' => 'succeeded',
        'status' => 'processing',
        'tracking_number' => null,
        'tracking_carrier' => null,
    ]);
    Queue::fake();
});

function shipPayload(array $extra = []): array
{
    return array_merge(['tracking_carrier' => 'acme', 'tracking_number' => 'TRK1'], $extra);
}

it('processShip queues a single shipped email and a repeat does not duplicate it', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), shipPayload())
        ->assertSessionHas('success')->assertSessionMissing('warning');
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), shipPayload())
        ->assertSessionHas('error');

    Queue::assertPushed(SendOrderEmail::class, 1);
});

it('an update after processShip with unchanged data does not send a second email', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), shipPayload());
    $this->actingAs($this->admin)->put(route('admin.orders.update', $this->order), ['status' => 'shipped', 'tracking_number' => 'TRK1', 'tracking_carrier' => 'acme']);

    Queue::assertPushed(SendOrderEmail::class, 1);
});

it('re-sends the shipped email only when the tracking number changes', function () {
    $url = route('admin.orders.update', $this->order);

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), shipPayload());
    $this->actingAs($this->admin)->put($url, ['tracking_number' => 'TRK1']);   // unchanged
    $this->actingAs($this->admin)->put($url, ['tracking_number' => 'TRK2']);   // corrected

    Queue::assertPushed(SendOrderEmail::class, 2);
    expect(OrderEmail::pluck('fingerprint')->unique())->toHaveCount(2);
});

it('does not send a shipped email when only a non-shipped order is edited', function () {
    $this->order->update(['status' => 'delivered', 'tracking_number' => 'OLD']);

    $this->actingAs($this->admin)->put(route('admin.orders.update', $this->order), ['tracking_number' => 'NEW']);

    // (the delivered email queued by the status change above is a different type: Lifecycle tests)
    Queue::assertNotPushed(SendOrderEmail::class, fn ($job) => $job->type === OrderEmail::TYPE_SHIPPED);
});

it('sends nothing and shows no warning when the shipped toggle is off', function () {
    settings()->set('email.order_shipped', false);

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), shipPayload())
        ->assertSessionMissing('warning');
    $this->actingAs($this->admin)->put(route('admin.orders.update', $this->order), ['tracking_number' => 'TRK2'])
        ->assertSessionMissing('warning');

    Queue::assertNothingPushed();
});

it('warns the admin when the shipped email could not be queued', function () {
    $this->order->update(['guest_email' => null]);

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), shipPayload())
        ->assertSessionHas('success')
        ->assertSessionHas('warning');
    $this->actingAs($this->admin)->put(route('admin.orders.update', $this->order), ['tracking_number' => 'TRK2'])
        ->assertSessionHas('success')
        ->assertSessionHas('warning');
});

it('bulk status other than shipped queues no shipped email', function () {
    $shipped = Order::factory()->create(['user_id' => null, 'guest_email' => 'two@example.test', 'payment_status' => 'succeeded', 'status' => 'shipped']);

    $this->actingAs($this->admin)->patch(route('admin.orders.bulk-update-status'), ['order_ids' => [$shipped->id], 'status' => 'delivered'])
        ->assertSessionHas('success');

    expect($shipped->fresh()->status)->toBe('delivered');
    Queue::assertNotPushed(SendOrderEmail::class, fn ($job) => $job->type === OrderEmail::TYPE_SHIPPED);
});

describe('carrier in the shipped fingerprint', function () {
    beforeEach(function () {
        $this->url = route('admin.orders.update', $this->order);
        $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), shipPayload());
    });

    it('sends one new mail when the carrier changes, and none when it is saved again', function () {
        $this->actingAs($this->admin)->put($this->url, ['tracking_number' => 'TRK1', 'tracking_carrier' => 'zeta']);  // carrier changed
        $this->actingAs($this->admin)->put($this->url, ['tracking_number' => 'TRK1', 'tracking_carrier' => 'zeta']);  // unchanged

        Queue::assertPushed(SendOrderEmail::class, 2);
        expect(OrderEmail::pluck('fingerprint')->all())->toEqualCanonicalizing([
            OrderEmail::shippedFingerprint('TRK1', 'acme'),
            OrderEmail::shippedFingerprint('TRK1', 'zeta'),
        ]);
    });

    it('still sends for a changed tracking number on an order with a carrier', function () {
        $this->actingAs($this->admin)->put($this->url, ['tracking_number' => 'TRK2', 'tracking_carrier' => 'acme']);

        Queue::assertPushed(SendOrderEmail::class, 2);
        expect(OrderEmail::pluck('fingerprint')->unique())->toHaveCount(2);
    });

    it('processShip uses the carrier it just stored in the fingerprint', function () {
        expect(OrderEmail::pluck('fingerprint')->all())->toBe([OrderEmail::shippedFingerprint('TRK1', 'acme')]);
    });
});

it('keeps the legacy fingerprint for an order shipped before carriers existed', function () {
    $legacy = Order::factory()->create(['user_id' => null, 'guest_email' => 'old@example.test', 'payment_status' => 'succeeded', 'status' => 'shipped', 'tracking_carrier' => null, 'tracking_number' => null]);
    $url = route('admin.orders.update', $legacy);

    $this->actingAs($this->admin)->put($url, ['tracking_number' => 'TRK1']);
    $this->actingAs($this->admin)->put($url, ['tracking_number' => 'TRK1', 'tracking_carrier' => null]);
    $this->actingAs($this->admin)->put($url, ['tracking_number' => 'TRK1', 'tracking_carrier' => '']);

    Queue::assertPushed(SendOrderEmail::class, 1);
    expect(OrderEmail::pluck('fingerprint')->all())->toBe([OrderEmail::shippedFingerprint('TRK1')])
        ->and(OrderEmail::shippedFingerprint('TRK1'))->toBe(substr(sha1('shipped|TRK1'), 0, 40));
});

it('sends nothing when the carrier changes on an order that is not shipped', function () {
    $this->actingAs($this->admin)->put(route('admin.orders.update', $this->order), ['tracking_carrier' => 'acme'])->assertSessionHas('success');

    expect($this->order->fresh())->status->toBe('processing')->tracking_carrier->toBe('acme');
    Queue::assertNothingPushed();
});
