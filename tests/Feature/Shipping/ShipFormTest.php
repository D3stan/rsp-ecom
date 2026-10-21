<?php

use App\Jobs\SendOrderEmail;
use App\Models\Order;
use App\Models\OrderEmail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/*
 * G2 (F-06): the ship form (ShippingService::ship) is the only way into "shipped". It takes the carrier and the
 * tracking number, records a shipment, and sends ONE shipped email, with the tracking when there is one. Without a
 * tracking number (explicitly confirmed) the email goes out only when the admin asks for it; otherwise the first
 * email goes out when the number is added. Nothing is written into the order notes any more.
 */

beforeEach(function () {
    Queue::fake();
    $this->admin = User::factory()->create(['role' => 'admin']);
    config(['shipping.carriers' => [
        'acme' => ['name' => 'Acme Courier', 'tracking_url' => 'https://track.example.test/?n={number}'],
        'zeta' => ['name' => 'Zeta Express'],
    ]]);
    $this->order = Order::factory()->create([
        'user_id' => null,
        'guest_email' => 'guest@example.test',
        'status' => 'processing',
        'payment_status' => 'succeeded',
        'tracking_number' => null,
        'tracking_carrier' => null,
        'notes' => 'Customer asked for a gift wrap',
    ]);
});

function shippedEmails(): array
{
    return OrderEmail::where('type', OrderEmail::TYPE_SHIPPED)->orderBy('id')->pluck('fingerprint')->all();
}

it('ships with carrier and tracking number, records the shipment and sends one email with the tracking', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), [
        'tracking_carrier' => 'acme',
        'tracking_number' => ' TRK123 ',
    ])->assertRedirect(route('admin.orders.show', $this->order))->assertSessionHas('success');

    $order = $this->order->fresh();
    expect($order->status)->toBe('shipped')
        ->and($order->tracking_number)->toBe('TRK123')
        ->and($order->tracking_carrier)->toBe('acme')
        // the form is no longer dumped into the internal notes
        ->and($order->notes)->toBe('Customer asked for a gift wrap');

    $shipment = DB::table('shipments')->where('order_id', $order->id)->first();
    expect($shipment)->not->toBeNull()
        ->and($shipment->carrier_code)->toBe('acme')
        ->and($shipment->tracking_number)->toBe('TRK123')
        ->and($shipment->driver)->toBe('manual')
        ->and($shipment->status)->toBe('created')
        ->and((int) $shipment->created_by)->toBe($this->admin->id);

    expect(shippedEmails())->toBe([OrderEmail::shippedFingerprint('TRK123', 'acme')]);
    Queue::assertPushed(SendOrderEmail::class, 1);
    Queue::assertPushed(SendOrderEmail::class, fn ($job) => $job->type === OrderEmail::TYPE_SHIPPED && $job->trackingNumber === 'TRK123');
});

// The form as it was posted before G2 (sender, customer, address, box, extras) plus carrier and tracking number
function legacyShipForm(): array
{
    return [
        'sender_info' => ['company_name' => 'Shop', 'address' => 'Via Roma 1', 'phone' => '123', 'email' => 'shop@example.test'],
        'customer_info' => ['name' => 'Guest', 'email' => 'guest@example.test'],
        'shipping_address' => [
            'first_name' => 'A', 'last_name' => 'B', 'address_line_1' => 'Via 1', 'city' => 'Roma',
            'state' => 'RM', 'postal_code' => '00100', 'country' => 'IT',
        ],
        'box_info' => ['carrier' => 'GLS'],
        'additional_settings' => ['notes' => 'fragile'],
        'tracking_carrier' => 'acme',
        'tracking_number' => 'TRK1',
    ];
}

it('does not dump the ship form into the order notes', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), legacyShipForm())->assertSessionHas('success');

    expect($this->order->fresh()->notes)->toBe('Customer asked for a gift wrap');
});

it('sends one shipped email when the order ships with its number, none again when the number is saved later', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), legacyShipForm())->assertSessionHas('success');
    // the admin then types the same number on the order page
    $this->actingAs($this->admin)->put(route('admin.orders.update', $this->order), ['tracking_number' => 'TRK1']);

    expect(shippedEmails())->toBe([OrderEmail::shippedFingerprint('TRK1', 'acme')]);
});

it('requires a carrier and a tracking number', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), ['tracking_number' => 'TRK1'])
        ->assertSessionHasErrors('tracking_carrier');
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), ['tracking_carrier' => 'made-up', 'tracking_number' => 'TRK1'])
        ->assertSessionHasErrors('tracking_carrier');
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), ['tracking_carrier' => 'acme'])
        ->assertSessionHasErrors('tracking_number');

    expect($this->order->fresh()->status)->toBe('processing');
    Queue::assertNothingPushed();
});

it('ships without a tracking number only when that is confirmed, and then emails nobody until the number is added', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), [
        'tracking_carrier' => 'acme',
        'ship_without_tracking' => true,
    ])->assertSessionHas('success');

    expect($this->order->fresh()->status)->toBe('shipped')
        ->and(shippedEmails())->toBe([]);

    // the number added later sends the first (and only) shipped email, with the tracking
    $this->actingAs($this->admin)->put(route('admin.orders.update', $this->order), ['tracking_number' => 'TRK9']);
    $this->actingAs($this->admin)->put(route('admin.orders.update', $this->order), ['tracking_number' => 'TRK9', 'notes' => 'x']);

    expect(shippedEmails())->toBe([OrderEmail::shippedFingerprint('TRK9', 'acme')])
        ->and(DB::table('shipments')->where('order_id', $this->order->id)->value('tracking_number'))->toBe('TRK9');
    Queue::assertPushed(SendOrderEmail::class, fn ($job) => $job->trackingNumber === 'TRK9');
});

it('notifies the customer at once without tracking when asked, then once more when the number is added', function () {
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), [
        'tracking_carrier' => 'acme',
        'ship_without_tracking' => true,
        'notify_customer' => true,
    ]);

    expect(shippedEmails())->toBe([OrderEmail::shippedFingerprint(null, 'acme')]);

    $this->actingAs($this->admin)->put(route('admin.orders.update', $this->order), ['tracking_number' => 'TRK9']);
    $this->actingAs($this->admin)->put(route('admin.orders.update', $this->order), ['tracking_number' => 'TRK9']);

    expect(shippedEmails())->toBe([
        OrderEmail::shippedFingerprint(null, 'acme'),
        OrderEmail::shippedFingerprint('TRK9', 'acme'),
    ]);
});

it('refuses "shipped" from the edit form, the status endpoint and the bulk action, pointing to the ship form', function () {
    $this->actingAs($this->admin)->put(route('admin.orders.update', $this->order), ['status' => 'shipped'])
        ->assertSessionHas('error', fn ($message) => is_string($message) && str_contains($message, 'ship form'));
    $this->actingAs($this->admin)->patch(route('admin.orders.update-status', $this->order), ['status' => 'shipped'])
        ->assertSessionHas('error', fn ($message) => is_string($message) && str_contains($message, 'ship form'));
    $this->actingAs($this->admin)->patch(route('admin.orders.bulk-update-status'), ['order_ids' => [$this->order->id], 'status' => 'shipped'])
        ->assertSessionHasErrors('status');

    expect($this->order->fresh()->status)->toBe('processing');
    Queue::assertNothingPushed();
});

it('does not ship an order when a tracking number is typed into the edit form', function () {
    $this->actingAs($this->admin)->put(route('admin.orders.update', $this->order), ['tracking_number' => 'TRK1', 'tracking_carrier' => 'acme'])
        ->assertSessionHas('success');

    expect($this->order->fresh()->status)->toBe('processing');
    Queue::assertNothingPushed();
});

it('prefills the ship form with a tracking number saved earlier and ships with it', function () {
    $this->order->update(['tracking_number' => 'EARLY1', 'tracking_carrier' => 'zeta']);

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), [
        'tracking_carrier' => 'zeta',
        'tracking_number' => 'EARLY1',
    ])->assertSessionHas('success');

    expect(shippedEmails())->toBe([OrderEmail::shippedFingerprint('EARLY1', 'zeta')]);
});

it('keeps the order shipped and sends nothing when the shipped email is switched off', function () {
    settings()->set('email.order_shipped', false);

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), ['tracking_carrier' => 'acme', 'tracking_number' => 'T1'])
        ->assertSessionHas('success')->assertSessionMissing('warning');

    expect($this->order->fresh()->status)->toBe('shipped');
    Queue::assertNothingPushed();
});

it('warns the admin when the shipped email could not be queued', function () {
    $this->order->update(['guest_email' => null]);

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), ['tracking_carrier' => 'acme', 'tracking_number' => 'T1'])
        ->assertSessionHas('success')
        ->assertSessionHas('warning');

    expect($this->order->fresh()->status)->toBe('shipped');
});

it('a second submit of the ship form changes nothing', function () {
    $body = ['tracking_carrier' => 'acme', 'tracking_number' => 'T1'];

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), $body)->assertSessionHas('success');
    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), $body)->assertSessionHas('error');

    expect(DB::table('shipments')->where('order_id', $this->order->id)->count())->toBe(1)
        ->and(shippedEmails())->toHaveCount(1);
});

it('resolves real courier waybill immediately on manual ship form when tracking driver returns carrierTrackingNumber', function () {
    config([
        'shipping.tracking.driver' => 'manual',
        'shipping.tracking.drivers.spediamo' => [
            'base_url' => 'https://api.spediamo.test',
            'token' => 'test-token',
            'timeout' => 5,
            'retries' => 1,
            'retry_sleep_ms' => 0,
            'timezone' => 'Europe/Rome',
            'carrier_map' => [],
            'status_map' => [],
        ],
    ]);

    \Illuminate\Support\Facades\Http::fake([
        'https://api.spediamo.test/v1/shipments/tracking?code=A422-098-162-296' => \Illuminate\Support\Facades\Http::response([
            'code' => 'A422-098-162-296',
            'trackingNumber' => '288364I031083',
            'courier' => 'SDA',
            'type' => 'DOMESTIC',
            'status' => 'CURRENT',
            'tracking' => [
                ['date' => '2026-10-21', 'time' => ['hour' => 10, 'minute' => 0, 'second' => 0, 'nano' => 0], 'description' => 'Picked up', 'site' => 'Milano', 'status' => 'OPEN'],
            ],
        ], 200),
    ]);

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $this->order), [
        'tracking_carrier' => 'acme',
        'tracking_driver' => 'spediamo',
        'tracking_number' => 'A422-098-162-296',
    ])->assertRedirect(route('admin.orders.show', $this->order))->assertSessionHas('success');

    $order = $this->order->fresh();
    expect($order->status)->toBe('shipped')
        ->and($order->tracking_number)->toBe('288364I031083')
        ->and($order->tracking_driver)->toBe('spediamo')
        ->and($order->tracking_status)->toBe('in_transit');

    $shipment = DB::table('shipments')->where('order_id', $order->id)->first();
    expect($shipment->tracking_number)->toBe('288364I031083')
        ->and($shipment->external_id)->toBe('A422-098-162-296')
        ->and($shipment->driver)->toBe('manual');

    // Email was sent with the promoted carrier waybill number!
    Queue::assertPushed(SendOrderEmail::class, function ($job) {
        return $job->type === OrderEmail::TYPE_SHIPPED && $job->trackingNumber === '288364I031083';
    });
});

