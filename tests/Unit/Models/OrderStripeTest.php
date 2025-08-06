<?php

namespace Tests\Unit\Models;

use App\Models\Order;
use App\Models\User;
use App\Models\Address;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderStripeTest extends TestCase
{
    use RefreshDatabase;

    private function createTestAddress(User $user, string $type = 'shipping'): Address
    {
        return Address::create([
            'user_id' => $user->id,
            'type' => $type,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'address_line_1' => '123 Test St',
            'city' => 'Test City',
            'state' => 'TS',
            'postal_code' => '12345',
            'country' => 'US',
            'is_default' => true,
        ]);
    }

    public function test_order_has_stripe_fields()
    {
        $user = User::factory()->create();
        $billingAddress = $this->createTestAddress($user, 'billing');
        $shippingAddress = $this->createTestAddress($user, 'shipping');
        
        $order = Order::create([
            'order_number' => 'TEST-001',
            'user_id' => $user->id,
            'billing_address_id' => $billingAddress->id,
            'shipping_address_id' => $shippingAddress->id,
            'status' => 'pending',
            'subtotal' => 100.00,
            'tax_amount' => 8.00,
            'shipping_amount' => 10.00,
            'total_amount' => 118.00,
            'currency' => 'EUR',
            'stripe_payment_intent_id' => 'pi_test123',
            'stripe_checkout_session_id' => 'cs_test123',
            'payment_status' => 'pending',
            'payment_method' => 'card',
            'stripe_customer_id' => 'cus_test123',
        ]);

        $this->assertEquals('pi_test123', $order->stripe_payment_intent_id);
        $this->assertEquals('cs_test123', $order->stripe_checkout_session_id);
        $this->assertEquals('pending', $order->payment_status);
        $this->assertEquals('card', $order->payment_method);
        $this->assertEquals('cus_test123', $order->stripe_customer_id);
    }

    public function test_order_payment_helper_methods()
    {
        $user = User::factory()->create();
        $billingAddress = $this->createTestAddress($user, 'billing');
        $shippingAddress = $this->createTestAddress($user, 'shipping');
        
        $order = Order::create([
            'order_number' => 'TEST-001',
            'user_id' => $user->id,
            'billing_address_id' => $billingAddress->id,
            'shipping_address_id' => $shippingAddress->id,
            'status' => 'pending',
            'subtotal' => 100.00,
            'tax_amount' => 8.00,
            'shipping_amount' => 10.00,
            'total_amount' => 118.00,
            'currency' => 'EUR',
            'stripe_payment_intent_id' => 'pi_test123',
            'payment_status' => 'succeeded',
        ]);

        $this->assertTrue($order->hasStripePaymentIntent());
        $this->assertTrue($order->isPaymentCompleted());
        $this->assertFalse($order->isPaymentPending());
        $this->assertFalse($order->isPaymentFailed());
    }

    public function test_order_payment_status_scopes()
    {
        $user = User::factory()->create();
        $billingAddress = $this->createTestAddress($user, 'billing');
        $shippingAddress = $this->createTestAddress($user, 'shipping');
        
        // Create orders with different payment statuses
        Order::create([
            'order_number' => 'TEST-001',
            'user_id' => $user->id,
            'billing_address_id' => $billingAddress->id,
            'shipping_address_id' => $shippingAddress->id,
            'status' => 'pending',
            'subtotal' => 100.00,
            'tax_amount' => 8.00,
            'shipping_amount' => 10.00,
            'total_amount' => 118.00,
            'currency' => 'EUR',
            'payment_status' => 'succeeded',
        ]);

        Order::create([
            'order_number' => 'TEST-002',
            'user_id' => $user->id,
            'billing_address_id' => $billingAddress->id,
            'shipping_address_id' => $shippingAddress->id,
            'status' => 'pending',
            'subtotal' => 50.00,
            'tax_amount' => 4.00,
            'shipping_amount' => 5.00,
            'total_amount' => 59.00,
            'currency' => 'EUR',
            'payment_status' => 'failed',
        ]);

        $this->assertEquals(1, Order::paymentSucceeded()->count());
        $this->assertEquals(1, Order::paymentFailed()->count());
        $this->assertEquals(0, Order::paymentPending()->count());
    }
}
