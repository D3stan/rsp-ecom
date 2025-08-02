<?php

namespace Tests\Feature\Checkout;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Stripe\Checkout\Session;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Product $product;
    protected Size $size;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->category = Category::factory()->create();
        $this->size = Size::factory()->create();
        $this->product = Product::factory()->create([
            'category_id' => $this->category->id,
            'size_id' => $this->size->id,
            'price' => 25.00,
        ]);
    }

    public function test_checkout_page_renders_with_cart_items(): void
    {
        // Create cart for user
        $cart = Cart::create([
            'user_id' => $this->user->id,
        ]);

        // Create cart items for user
        $cart->cartItems()->create([
            'product_id' => $this->product->id,
            'size_id' => $this->size->id,
            'quantity' => 2,
            'price' => $this->product->price,
        ]);

        $response = $this->actingAs($this->user)->get('/checkout');

        $response->assertOk();
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Checkout/Index')
                ->has('cartItems', 1)
                ->has('totals')
                ->where('totals.subtotal', 50)
                ->where('totals.total_quantity', 2)
        );
    }

    public function test_checkout_redirects_when_cart_is_empty(): void
    {
        $response = $this->actingAs($this->user)->get('/checkout');

        $response->assertRedirect('/cart');
        $response->assertSessionHas('error', 'Your cart is empty.');
    }

    public function test_checkout_requires_authentication(): void
    {
        $response = $this->get('/checkout');

        $response->assertRedirect(route('login'));
    }

    public function test_checkout_session_creation_requires_authentication(): void
    {
        $response = $this->postJson('/checkout/session', [
            'billing_address' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'address_line_1' => '123 Main St',
                'city' => 'Anytown',
                'state' => 'CA',
                'postal_code' => '12345',
                'country' => 'US',
            ],
        ]);

        $response->assertUnauthorized();
    }

    public function test_checkout_session_creation_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)->postJson('/checkout/session', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'billing_address.first_name',
            'billing_address.last_name',
            'billing_address.email',
            'billing_address.address_line_1',
            'billing_address.city',
            'billing_address.state',
            'billing_address.postal_code',
            'billing_address.country',
        ]);
    }

    public function test_checkout_session_fails_with_empty_cart(): void
    {
        $response = $this->actingAs($this->user)->postJson('/checkout/session', [
            'billing_address' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'address_line_1' => '123 Main St',
                'city' => 'Anytown',
                'state' => 'CA',
                'postal_code' => '12345',
                'country' => 'US',
            ],
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Cart is empty']);
    }

    public function test_checkout_success_page_requires_session_id(): void
    {
        $response = $this->actingAs($this->user)->get('/checkout/success');

        $response->assertRedirect('/cart');
        $response->assertSessionHas('error', 'Invalid checkout session');
    }

    public function test_checkout_cancel_updates_order_status(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'stripe_checkout_session_id' => 'cs_test_123',
            'payment_status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)->get('/checkout/cancel?session_id=cs_test_123');

        $response->assertOk();
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Checkout/Cancel')
                ->has('message')
        );

        $order->refresh();
        $this->assertEquals('cancelled', $order->payment_status);
    }

    public function test_guest_checkout_index_renders(): void
    {
        // Create a test session
        $sessionId = 'test_guest_session_12345';
        
        // Set up a proper session
        $this->startSession();
        session()->put('_token', 'test-token');
        session()->save();
        
        // Mock Session::getId() to return our test session ID
        \Illuminate\Support\Facades\Session::shouldReceive('getId')
            ->andReturn($sessionId);

        // Create a guest cart using the test session ID
        $cart = Cart::create([
            'session_id' => $sessionId,
        ]);

        $cart->cartItems()->create([
            'product_id' => $this->product->id,
            'size_id' => $this->size->id,
            'quantity' => 1,
            'price' => $this->product->price,
        ]);

        $response = $this->get('/checkout/guest');

        $response->assertOk();
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Checkout/GuestIndex')
                ->has('cartItems', 1)
        );
    }

    public function test_checkout_totals_calculation(): void
    {
        // Create cart for user
        $cart = Cart::create([
            'user_id' => $this->user->id,
        ]);

        // Create multiple cart items
        $cart->cartItems()->create([
            'product_id' => $this->product->id,
            'size_id' => $this->size->id,
            'quantity' => 2,
            'price' => $this->product->price,
        ]);

        $product2 = Product::factory()->create([
            'category_id' => $this->category->id,
            'price' => 15.00,
        ]);

        $cart->cartItems()->create([
            'product_id' => $product2->id,
            'size_id' => $this->size->id,
            'quantity' => 1,
            'price' => $product2->price,
        ]);

        $response = $this->actingAs($this->user)->get('/checkout');

        $response->assertInertia(
            fn (Assert $page) => $page
                ->where('totals.subtotal', 65) // (25 * 2) + (15 * 1)
                ->where('totals.total_quantity', 3)
                ->where('totals.shipping_cost', 10) // Under $100 threshold
                ->where('totals.tax_amount', 5.69) // 8.75% of subtotal
                ->where('totals.total', 80.69) // subtotal + tax + shipping
        );
    }

    public function test_free_shipping_over_threshold(): void
    {
        // Create cart for user
        $cart = Cart::create([
            'user_id' => $this->user->id,
        ]);

        // Create a high-value product to trigger free shipping
        $expensiveProduct = Product::factory()->create([
            'category_id' => $this->category->id,
            'price' => 150.00,
        ]);

        $cart->cartItems()->create([
            'product_id' => $expensiveProduct->id,
            'size_id' => $this->size->id,
            'quantity' => 1,
            'price' => $expensiveProduct->price,
        ]);

        $response = $this->actingAs($this->user)->get('/checkout');

        $response->assertInertia(
            fn (Assert $page) => $page
                ->where('totals.subtotal', 150)
                ->where('totals.shipping_cost', 0) // Free shipping over $100
        );
    }

    public function test_checkout_show_page_renders(): void
    {
        // Create addresses for the user
        $billingAddress = \App\Models\Address::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'billing',
            'is_default' => true,
        ]);
        
        $shippingAddress = \App\Models\Address::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'shipping',
            'is_default' => true,
        ]);

        // Create an order with a fake session ID (simulating completed checkout)
        $order = Order::create([
            'user_id' => $this->user->id,
            'billing_address_id' => $billingAddress->id,
            'shipping_address_id' => $shippingAddress->id,
            'order_number' => 'TEST-12345',
            'status' => 'processing',
            'subtotal' => 25.00,
            'tax_amount' => 2.19,
            'shipping_amount' => 10.00,
            'total_amount' => 37.19,
            'currency' => 'usd',
            'stripe_checkout_session_id' => 'cs_test_valid_session',
            'payment_status' => 'paid',
            'payment_method' => 'stripe_checkout',
        ]);

        // Mock the CheckoutService to return a fake session
        $this->mock(\App\Services\CheckoutService::class, function ($mock) {
            // Create a simple object that matches what the controller expects
            $mockSession = new \stdClass();
            $mockSession->id = 'cs_test_valid_session';
            $mockSession->status = 'complete';
            $mockSession->payment_status = 'paid';
            $mockSession->customer_details = (object) ['email' => 'test@example.com'];

            $mock->shouldReceive('retrieveCheckoutSession')
                ->with('cs_test_valid_session')
                ->andReturn($mockSession);
        });

        $response = $this->actingAs($this->user)->get('/checkout/show?session_id=cs_test_valid_session');

        $response->assertOk();
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Checkout/Show')
                ->has('session')
                ->has('order')
        );
    }

    public function test_checkout_show_requires_session_id(): void
    {
        $response = $this->actingAs($this->user)->get('/checkout/show');

        $response->assertRedirect('/checkout');
    }
}
