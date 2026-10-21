<?php

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentTrackingEvent;
use App\Models\Size;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Queue::fake();
    $this->admin = User::factory()->create(['role' => 'admin']);

    config([
        'shipping.carriers' => [
            'poste_italiane' => [
                'name' => 'Poste Italiane',
                'tracking_url' => 'https://www.poste.it/cerca/index.html#/risultati-spedizioni/{number}',
            ],
            'dhl' => [
                'name' => 'DHL Express',
                'tracking_url' => 'https://www.dhl.com/it-it/home/tracking.html?tracking-id={number}',
            ],
        ],
    ]);
});

function createPaidProcessingOrder(): Order
{
    $product = Product::factory()->create([
        'category_id' => Category::query()->value('id') ?? Category::factory()->create()->id,
        'size_id' => Size::query()->value('id') ?? Size::factory()->create()->id,
        'stock_quantity' => 10,
    ]);
    $product->defaultVariant->update(['stock_quantity' => 10]);

    $shippingAddress = Address::factory()->shipping()->create(['user_id' => null]);

    $order = Order::factory()->create([
        'status' => 'processing',
        'payment_status' => 'succeeded',
        'user_id' => null,
        'guest_email' => 'customer@example.test',
        'shipping_address_id' => $shippingAddress->id,
    ]);

    $order->orderItems()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'price' => 25.00,
        'total' => 25.00,
    ]);

    Order::whereKey($order->id)->update(['stock_deducted_at' => now()]);

    return $order->fresh();
}

it('simulates the full shipment undo and re-shipping lifecycle end-to-end', function () {
    // 1. Create paid processing order
    $order = createPaidProcessingOrder();
    expect($order->status)->toBe('processing')
        ->and($order->payment_status)->toBe('succeeded')
        ->and($order->tracking_number)->toBeNull();

    // 2. Ship order manually with carrier poste_italiane and tracking number A422-098-162-296
    $trackingA = 'A422-098-162-296';
    $response = $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $order), [
        'tracking_carrier' => 'poste_italiane',
        'tracking_number' => $trackingA,
        'notify_customer' => false,
    ]);
    $response->assertSessionHas('success');

    $order->refresh();
    expect($order->status)->toBe('shipped')
        ->and($order->tracking_carrier)->toBe('poste_italiane')
        ->and($order->tracking_number)->toBe($trackingA);

    $initialShipment = $order->shipments()->first();
    expect($initialShipment)->not->toBeNull()
        ->and($initialShipment->status)->toBe(Shipment::STATUS_CREATED)
        ->and($initialShipment->carrier_code)->toBe('poste_italiane')
        ->and($initialShipment->tracking_number)->toBe($trackingA);

    // Add a tracking scan event for tracking A
    ShipmentTrackingEvent::create([
        'order_id' => $order->id,
        'provider' => 'manual',
        'tracking_number' => $trackingA,
        'status' => 'in_transit',
        'description' => 'Preso in carico dall\'ufficio postale',
        'location' => 'Centro Operativo Postale Bologna',
        'occurred_at' => now()->subHours(3),
        'event_hash' => 'hash-scan-a-1',
    ]);

    // 3. Assert tracking appears in customerTrackingArray() and order has status 'shipped'
    $order->load('trackingEvents');
    $customerTracking = $order->customerTrackingArray();

    expect($order->status)->toBe('shipped')
        ->and($customerTracking['tracking_url'])->toBe('https://www.poste.it/cerca/index.html#/risultati-spedizioni/A422-098-162-296')
        ->and($customerTracking['carrier_name'])->toBe('Poste Italiane')
        ->and($customerTracking['tracking_events'])->toHaveCount(1)
        ->and($customerTracking['tracking_events'][0]['description'])->toBe('Preso in carico dall\'ufficio postale')
        ->and($customerTracking['tracking_events'][0]['location'])->toBe('Centro Operativo Postale Bologna');

    // Also assert on the guest order page response
    $this->get($order->publicUrl())
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('order.status', 'shipped')
            ->where('order.tracking_number', $trackingA)
            ->where('order.carrier_name', 'Poste Italiane')
            ->where('order.tracking_url', 'https://www.poste.it/cerca/index.html#/risultati-spedizioni/A422-098-162-296')
            ->has('order.tracking_events', 1));

    // 4. Trigger "Undo shipment" (patch to update-status with status 'processing' and a reason)
    $undoReason = 'Parcel returned by carrier - customer requested address correction';
    $undoResponse = $this->actingAs($this->admin)->patch(route('admin.orders.update-status', $order), [
        'status' => 'processing',
        'reason' => $undoReason,
    ]);
    $undoResponse->assertSessionHas('success');

    // 5. Assert in database:
    //    - status === 'processing'
    //    - tracking_number === null
    //    - tracking_carrier === null
    //    - tracking_driver === null
    //    - tracking_status === null
    //    - tracking_next_sync_at === null
    //    - shipments row has status === 'cancelled'
    //    - order_events row records revoked_tracking
    //    - $order->fresh()->customerTrackingArray() returns null tracking_url, null carrier_name, and empty tracking_events
    $freshOrder = $order->fresh();
    expect($freshOrder->status)->toBe('processing')
        ->and($freshOrder->tracking_number)->toBeNull()
        ->and($freshOrder->tracking_carrier)->toBeNull()
        ->and($freshOrder->tracking_driver)->toBeNull()
        ->and($freshOrder->tracking_status)->toBeNull()
        ->and($freshOrder->tracking_last_synced_at)->toBeNull()
        ->and($freshOrder->tracking_next_sync_at)->toBeNull()
        ->and($freshOrder->tracking_started_at)->toBeNull();

    // Check shipments row
    $freshShipment = $initialShipment->fresh();
    expect($freshShipment->status)->toBe(Shipment::STATUS_CANCELLED);

    // Check order_events row
    $auditEvent = OrderEvent::query()
        ->where('order_id', $order->id)
        ->where('type', OrderEvent::TYPE_STATUS_CHANGED)
        ->where('from_status', 'shipped')
        ->where('to_status', 'processing')
        ->latest('id')
        ->first();

    expect($auditEvent)->not->toBeNull()
        ->and($auditEvent->actor_id)->toBe($this->admin->id)
        ->and($auditEvent->payload['reason'])->toBe($undoReason)
        ->and($auditEvent->payload['meta']['revoked_tracking'])->toBe([
            'tracking_number' => $trackingA,
            'tracking_carrier' => 'poste_italiane',
            'tracking_driver' => null,
        ]);

    // Check customerTrackingArray returns empty values
    $freshOrder->load('trackingEvents');
    $revokedCustomerTracking = $freshOrder->customerTrackingArray();
    expect($revokedCustomerTracking)->toBe([
        'tracking_url' => null,
        'carrier_name' => null,
        'tracking_events' => [],
    ]);

    // Guest page after undo shows processing and no tracking details
    $this->get($freshOrder->publicUrl())
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('order.status', 'processing')
            ->where('order.tracking_number', null)
            ->where('order.carrier_name', null)
            ->where('order.tracking_url', null)
            ->where('order.tracking_events', []));

    // 6. Simulate re-shipping with a new tracking number (e.g. B999-111-222) and carrier scans:
    $trackingB = 'B999-111-222';
    $reshipResponse = $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $freshOrder), [
        'tracking_carrier' => 'poste_italiane',
        'tracking_number' => $trackingB,
        'notify_customer' => false,
    ]);
    $reshipResponse->assertSessionHas('success');

    $reshippedOrder = $freshOrder->fresh();
    expect($reshippedOrder->status)->toBe('shipped')
        ->and($reshippedOrder->tracking_carrier)->toBe('poste_italiane')
        ->and($reshippedOrder->tracking_number)->toBe($trackingB);

    $secondShipment = $reshippedOrder->shipments()->reorder()->latest('id')->first();
    expect($secondShipment->id)->not->toBe($initialShipment->id)
        ->and($secondShipment->status)->toBe(Shipment::STATUS_CREATED)
        ->and($secondShipment->tracking_number)->toBe($trackingB);

    // Add new scan for tracking B
    ShipmentTrackingEvent::create([
        'order_id' => $reshippedOrder->id,
        'provider' => 'manual',
        'tracking_number' => $trackingB,
        'status' => 'in_transit',
        'description' => 'Nuovo pacco smistato all\'hub di Roma',
        'location' => 'Roma Hub Smistamento',
        'occurred_at' => now()->subMinutes(30),
        'event_hash' => 'hash-scan-b-1',
    ]);

    // Verify new tracking number is saved and customerTrackingArray returns only the new tracking data
    $reshippedOrder->load('trackingEvents');
    $newCustomerTracking = $reshippedOrder->customerTrackingArray();

    expect($newCustomerTracking['tracking_url'])->toBe('https://www.poste.it/cerca/index.html#/risultati-spedizioni/B999-111-222')
        ->and($newCustomerTracking['carrier_name'])->toBe('Poste Italiane')
        ->and($newCustomerTracking['tracking_events'])->toHaveCount(1)
        ->and($newCustomerTracking['tracking_events'][0]['description'])->toBe('Nuovo pacco smistato all\'hub di Roma')
        ->and($newCustomerTracking['tracking_events'][0]['location'])->toBe('Roma Hub Smistamento');

    // Ensure the old event for tracking A was filtered out
    $eventDescriptions = array_column($newCustomerTracking['tracking_events'], 'description');
    expect($eventDescriptions)->not->toContain('Preso in carico dall\'ufficio postale');

    // Guest page reflects re-shipped order with new tracking info
    $this->get($reshippedOrder->publicUrl())
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('order.status', 'shipped')
            ->where('order.tracking_number', $trackingB)
            ->where('order.carrier_name', 'Poste Italiane')
            ->where('order.tracking_url', 'https://www.poste.it/cerca/index.html#/risultati-spedizioni/B999-111-222')
            ->has('order.tracking_events', 1)
            ->where('order.tracking_events.0.description', 'Nuovo pacco smistato all\'hub di Roma'));
});

it('auto-transitions order to processing when label shipment is cancelled and no other active shipment exists', function () {
    $order = createPaidProcessingOrder();

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $order), [
        'tracking_carrier' => 'poste_italiane',
        'tracking_number' => 'A422-098-162-296',
        'notify_customer' => false,
    ]);

    $order->refresh();
    expect($order->status)->toBe('shipped');
    $shipment = $order->shipments()->first();

    // Cancel shipment via admin cancel endpoint
    $cancelResponse = $this->actingAs($this->admin)
        ->delete(route('admin.orders.shipments.cancel', [$order, $shipment]));

    $cancelResponse->assertSessionHas('success', 'Shipment cancelled at the carrier and order moved back to processing.');

    $freshOrder = $order->fresh();
    expect($shipment->fresh()->status)->toBe(Shipment::STATUS_CANCELLED)
        ->and($freshOrder->status)->toBe('processing')
        ->and($freshOrder->tracking_number)->toBeNull()
        ->and($freshOrder->tracking_carrier)->toBeNull();

    // Audit event was recorded for cancellation
    $event = OrderEvent::query()
        ->where('order_id', $order->id)
        ->where('type', OrderEvent::TYPE_STATUS_CHANGED)
        ->where('from_status', 'shipped')
        ->where('to_status', 'processing')
        ->latest('id')
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->payload['reason'])->toBe('Shipment label cancelled at carrier')
        ->and($event->payload['meta']['revoked_tracking']['tracking_number'])->toBe('A422-098-162-296');
});

it('keeps order in shipped status when cancelled shipment is not the only active shipment', function () {
    $order = createPaidProcessingOrder();

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $order), [
        'tracking_carrier' => 'poste_italiane',
        'tracking_number' => 'A422-098-162-296',
        'notify_customer' => false,
    ]);

    $shipment1 = $order->fresh()->shipments()->first();

    // Create a second active shipment
    $shipment2 = $order->shipments()->create([
        'carrier_code' => 'dhl',
        'driver' => Shipment::DRIVER_MANUAL,
        'tracking_number' => 'DHL-PARCEL-2',
        'status' => Shipment::STATUS_CREATED,
        'created_by' => $this->admin->id,
    ]);

    $cancelResponse = $this->actingAs($this->admin)
        ->delete(route('admin.orders.shipments.cancel', [$order, $shipment1]));

    $cancelResponse->assertSessionHas('success', 'Shipment cancelled at the carrier.');

    expect($shipment1->fresh()->status)->toBe(Shipment::STATUS_CANCELLED)
        ->and($shipment2->fresh()->status)->toBe(Shipment::STATUS_CREATED)
        ->and($order->fresh()->status)->toBe('shipped');
});
