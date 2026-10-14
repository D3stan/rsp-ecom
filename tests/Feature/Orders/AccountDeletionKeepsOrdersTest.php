<?php

use App\Models\Address;
use App\Models\Order;
use App\Models\OrderEmail;
use App\Models\OrderItem;
use App\Models\ShipmentTrackingEvent;
use App\Models\User;

/**
 * Deleting an account must never delete the orders placed with it: they are the shop's accounting and
 * fulfilment records. The order is detached from the user and keeps who bought (email, name, phone).
 */
function paidOrderOf(User $user, ?int $addressOwnerId): Order
{
    $address = fn (string $type) => Address::factory()->{$type}()->create([
        'user_id' => $addressOwnerId,
        'first_name' => 'Giulia',
        'last_name' => 'Bianchi',
        'phone' => '+39 333 123 4567',
    ]);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'guest_email' => null,
        'billing_address_id' => $address('billing')->id,
        'shipping_address_id' => $address('shipping')->id,
        'status' => 'shipped',
        'payment_status' => 'succeeded',
        'tracking_number' => 'TRK-KEEP-1',
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => null,
        'product_name' => 'Anello Argento',
        'quantity' => 1,
        'price' => 25.00,
        'total' => 25.00,
    ]);

    OrderEmail::create([
        'order_id' => $order->id,
        'type' => OrderEmail::TYPE_CONFIRMATION,
        'fingerprint' => OrderEmail::FINGERPRINT_DEFAULT,
        'sent_at' => now(),
    ]);

    ShipmentTrackingEvent::create([
        'order_id' => $order->id,
        'provider' => 'manual',
        'tracking_number' => 'TRK-KEEP-1',
        'status' => 'in_transit',
        'occurred_at' => now(),
        'event_hash' => str_repeat('a', 64),
    ]);

    return $order;
}

function deleteAccount(User $user): void
{
    test()->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

    test()->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
}

function assertOrderKept(Order $order): void
{
    $kept = Order::find($order->id);

    expect($kept)->not->toBeNull('the order was deleted together with the account')
        ->and($kept->user_id)->toBeNull()
        ->and(OrderItem::where('order_id', $order->id)->count())->toBe(1)
        ->and(OrderEmail::where('order_id', $order->id)->count())->toBe(1)
        ->and(ShipmentTrackingEvent::where('order_id', $order->id)->count())->toBe(1)
        ->and(Address::find($order->billing_address_id))->not->toBeNull()
        ->and(Address::find($order->shipping_address_id))->not->toBeNull();
}

test('deleting an account keeps its orders (checkout addresses, as created by OrderCreationService)', function () {
    $user = User::factory()->create(['email' => 'giulia@example.test', 'name' => 'Giulia Bianchi', 'phone' => '+39 333 123 4567']);
    $order = paidOrderOf($user, null);

    deleteAccount($user);

    assertOrderKept($order);

    $kept = Order::find($order->id);
    expect($kept->getCustomerEmail())->toBe('giulia@example.test')
        ->and($kept->getCustomerName())->toBe('Giulia Bianchi')
        ->and($kept->guest_phone)->toBe('+39 333 123 4567');
});

test('deleting an account keeps its orders when the order addresses belong to the account', function () {
    $user = User::factory()->create(['email' => 'giulia@example.test', 'name' => 'Giulia Bianchi']);
    $order = paidOrderOf($user, $user->id);

    deleteAccount($user);

    assertOrderKept($order);
    expect(Order::find($order->id)->getCustomerEmail())->toBe('giulia@example.test');
});

test('an order of a deleted account still opens from its signed status link', function () {
    $user = User::factory()->create(['email' => 'giulia@example.test']);
    $order = paidOrderOf($user, null);

    deleteAccount($user);

    $this->get(Order::find($order->id)->publicUrl())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Orders/GuestShow')
            ->where('order.order_number', $order->order_number)
            ->where('order.tracking_number', 'TRK-KEEP-1')
        );
});

test('deleting an account removes its address-book entries that no order uses', function () {
    $user = User::factory()->create();
    $order = paidOrderOf($user, $user->id);
    $unused = Address::factory()->shipping()->create(['user_id' => $user->id]);
    $someoneElses = Address::factory()->shipping()->create();

    deleteAccount($user);

    expect(Address::find($unused->id))->toBeNull()
        ->and(Address::find($someoneElses->id))->not->toBeNull()
        ->and(Address::find($order->billing_address_id)->user_id)->toBeNull();
});

test('an email already stored on the order is not overwritten at deletion', function () {
    $user = User::factory()->create(['email' => 'account@example.test']);
    $order = paidOrderOf($user, null);
    $order->forceFill(['guest_email' => 'checkout@example.test'])->save();

    deleteAccount($user);

    expect(Order::find($order->id)->getCustomerEmail())->toBe('checkout@example.test');
});

test('the foreign-key migration is reversible on sqlite', function () {
    $migration = require database_path('migrations/2026_10_15_100000_keep_orders_when_user_is_deleted.php');

    $migration->down();
    $user = User::factory()->create();
    $order = paidOrderOf($user, null);
    $user->delete();
    expect(Order::find($order->id))->toBeNull();

    $migration->up();
    $user = User::factory()->create();
    $order = paidOrderOf($user, $user->id);
    $user->delete();
    expect(Order::find($order->id))->not->toBeNull();
});
