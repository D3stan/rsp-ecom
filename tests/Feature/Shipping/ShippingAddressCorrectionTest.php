<?php

use App\Models\Address;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->address = Address::factory()->create(['user_id' => null, 'type' => 'shipping', 'postal_code' => '48105', 'city' => 'Cervia']);
    $this->order = Order::factory()->create([
        'status' => 'processing', 'payment_status' => 'succeeded', 'shipping_address_id' => $this->address->id,
    ]);
    $this->payload = [
        'first_name' => 'Ada', 'last_name' => 'Rossi', 'company' => null,
        'address_line_1' => 'Via Roma 1', 'address_line_2' => null,
        'city' => 'Cervia', 'state' => 'RA', 'postal_code' => '48015', 'country' => 'IT', 'phone' => '3331234567',
    ];
});

it('corrects the shipping address in place and logs the change', function () {
    $this->actingAs($this->admin)
        ->patch("/admin/orders/{$this->order->id}/shipping-address", $this->payload)
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($this->address->fresh()->postal_code)->toBe('48015');
    expect(OrderEvent::where('order_id', $this->order->id)->latest('id')->first()->payload['reason'])
        ->toContain('postal_code: 48105 → 48015');
});

it('copies an address-book address instead of editing the customer\'s saved one', function () {
    $customer = User::factory()->create();
    $this->address->update(['user_id' => $customer->id]);

    $this->actingAs($this->admin)->patch("/admin/orders/{$this->order->id}/shipping-address", $this->payload)->assertRedirect();

    expect($this->address->fresh()->postal_code)->toBe('48105')
        ->and($this->order->fresh()->shippingAddress->postal_code)->toBe('48015')
        ->and($this->order->fresh()->shipping_address_id)->not->toBe($this->address->id)
        ->and($this->order->fresh()->shippingAddress->user_id)->toBeNull();
});

it('validates the address and refuses a shipped order', function () {
    $this->actingAs($this->admin)->patch("/admin/orders/{$this->order->id}/shipping-address", [...$this->payload, 'postal_code' => ''])
        ->assertSessionHasErrors('postal_code');

    $this->order->update(['status' => 'shipped']);
    $this->actingAs($this->admin)->patch("/admin/orders/{$this->order->id}/shipping-address", $this->payload)
        ->assertSessionHas('error');
    expect($this->address->fresh()->postal_code)->toBe('48105');
});

it('is admin only', function () {
    $this->actingAs(User::factory()->create())->patch("/admin/orders/{$this->order->id}/shipping-address", $this->payload)->assertForbidden();
});
