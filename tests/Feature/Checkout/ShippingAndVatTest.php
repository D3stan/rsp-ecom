<?php

namespace Tests\Feature\Checkout;

use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Orders\Support\BuildsStripeOrderFixtures;
use Tests\TestCase;

/**
 * One price everywhere: shipping is the sum of the package sizes' shipping_cost (these carts stay under the free-shipping
 * threshold, see FreeShippingThresholdTest), and the
 * cart page, both details pages, the amount sent to Stripe and the stored order agree to the cent, VAT included.
 * Also the checkout-related settings: minimum amount, guest checkout switch, order note length, destinations.
 */
class ShippingAndVatTest extends TestCase
{
    use BuildsStripeOrderFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrderFixtures();
        $this->fakeEmailService();
        config(['cashier.secret' => 'sk_test_fake_for_tests']);

        $this->stripeHttp->responses['/v1/customers'] = ['id' => 'cus_test_1', 'object' => 'customer'];
        $this->stripeHttp->responses['/v1/checkout/sessions'] = [
            'id' => 'cs_test_created',
            'object' => 'checkout.session',
            'url' => 'https://checkout.stripe.test/c/pay/cs_test_created',
        ];
    }

    protected function tearDown(): void
    {
        $this->tearDownOrderFixtures();
        parent::tearDown();
    }

    // ------------------------------------------------------------------ helpers

    /** A 150.00 cart (one product, size shipping 5.00), registered or guest. */
    private function cartOf150(?User $user = null, ?string $guestSessionId = null): Cart
    {
        $cart = Cart::create(['user_id' => $user?->id, 'session_id' => $guestSessionId]);
        $product = $this->makeProduct(150.00, 'Expensive Product');
        $cart->cartItems()->create([
            'product_id' => $product->id,
            'product_variant_id' => $product->defaultVariant->id,
            'size_id' => $this->size->id,
            'quantity' => 1,
            'price' => 150.00,
        ]);

        return $cart->fresh();
    }

    private function checkoutForm(array $overrides = []): array
    {
        return array_replace_recursive([
            'email' => 'buyer@example.com',
            'shipping_address' => [
                'first_name' => 'Mario', 'last_name' => 'Rossi', 'address_line_1' => 'Via Roma 10', 'city' => 'Milano',
                'state' => 'MI', 'postal_code' => '20100', 'country' => 'IT', 'phone' => '+39 333 1234567',
            ],
            'order_notes' => '',
            'coupon_code' => '',
        ], $overrides);
    }

    /** The Stripe request that created the checkout session: [unit_amount in cents, metadata]. */
    private function createdSession(): array
    {
        foreach (array_reverse($this->stripeHttp->requests) as [$method, $url, $params]) {
            if ($method === 'post' && str_ends_with($url, '/v1/checkout/sessions')) {
                return [
                    (int) $params['line_items'][0]['price_data']['unit_amount'],
                    $params['metadata'],
                ];
            }
        }

        $this->fail('No Stripe checkout session was created');
    }

    private function sessionCookie(string $sessionId): static
    {
        return $this->withCookie(config('session.cookie'), $sessionId);
    }

    /** The order Stripe's paid session (built from what was sent to Stripe) turns into. */
    private function orderFromCharge(int $cents, array $metadata): Order
    {
        $payload = $this->sessionPayload([
            'amount_subtotal' => $cents,
            'amount_total' => $cents,
            'metadata' => $metadata + ['source' => OrderCreationService::SESSION_SOURCE],
        ]);

        return app(OrderCreationService::class)->createFromStripeSession($payload);
    }

    // ------------------------------------------------- one price, everywhere

    public function test_a_registered_customers_150_cart_has_the_same_shipping_in_cart_details_stripe_and_order(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->cartOf150($user);

        $this->actingAs($user)->get('/cart')->assertInertia(fn (Assert $page) => $page
            ->component('cart')
            ->where('subtotal', 150)->where('shippingCost', 5)->where('total', 155));

        $this->actingAs($user)->get('/checkout/details')->assertInertia(fn (Assert $page) => $page
            ->component('Checkout/Details')
            ->where('subtotal', 150)->where('shippingCost', 5)->where('total', 155)
            ->where('taxRate', 22)->where('taxAmount', 27.95));

        $this->actingAs($user)->post('/checkout/cart', $this->checkoutForm())->assertRedirect('https://checkout.stripe.test/c/pay/cs_test_created');

        [$cents, $metadata] = $this->createdSession();
        $this->assertSame(15500, $cents);

        $order = $this->orderFromCharge($cents, $metadata);
        $this->assertEquals(5.00, $order->shipping_amount);
        $this->assertEquals(150.00, $order->subtotal);
        $this->assertEquals(155.00, $order->total_amount);
        $this->assertEquals(27.95, $order->tax_amount);
        $this->assertEquals(22.00, $order->tax_rate);
    }

    public function test_a_guests_150_cart_has_the_same_shipping_in_cart_details_stripe_and_order(): void
    {
        $sid = Str::random(40);
        $this->cartOf150(null, $sid);

        $this->sessionCookie($sid)->get('/cart')->assertInertia(fn (Assert $page) => $page
            ->component('cart')
            ->where('subtotal', 150)->where('shippingCost', 5)->where('total', 155));

        // the guest details page used to zero the shipping of orders over 100
        $this->sessionCookie($sid)->get('/guest/checkout/details')->assertInertia(fn (Assert $page) => $page
            ->component('Checkout/Details')
            ->where('subtotal', 150)->where('shippingCost', 5)->where('total', 155)
            ->where('taxRate', 22)->where('taxAmount', 27.95));

        $this->sessionCookie($sid)->post('/guest/checkout/cart', $this->checkoutForm())->assertRedirect('https://checkout.stripe.test/c/pay/cs_test_created');

        [$cents, $metadata] = $this->createdSession();
        $this->assertSame(15500, $cents);

        $order = $this->orderFromCharge($cents, $metadata);
        $this->assertEquals(5.00, $order->shipping_amount);
        $this->assertEquals(155.00, $order->total_amount);
        $this->assertEquals(27.95, $order->tax_amount);
    }

    public function test_the_charge_and_the_details_total_follow_a_coupon_and_the_vat_is_taken_from_what_is_paid(): void
    {
        $sid = Str::random(40);
        $this->cartOf150(null, $sid);
        $coupon = ['code' => 'TEN', 'description' => '10% off', 'discount_type' => 'percentage', 'discount_percentage' => 10, 'discount_amount' => 0];

        // 150 - 15.00 + 5.00 shipping = 140.00; VAT 22% included = 25.25
        $this->withSession(['applied_coupon' => $coupon])->sessionCookie($sid)->get('/guest/checkout/details')
            ->assertInertia(fn (Assert $page) => $page
                ->where('discountAmount', 15)->where('shippingCost', 5)->where('total', 140)->where('taxAmount', 25.25));

        $this->withSession(['applied_coupon' => $coupon])->sessionCookie($sid)->post('/guest/checkout/cart', $this->checkoutForm());
        [$cents, $metadata] = $this->createdSession();
        $this->assertSame(14000, $cents);

        $order = $this->orderFromCharge($cents, $metadata);
        $this->assertEquals(140.00, $order->total_amount);
        $this->assertEquals(25.25, $order->tax_amount);
        $this->assertEquals(15.00, $order->discount_amount);
    }

    public function test_no_vat_breakdown_is_shown_when_prices_are_not_vat_inclusive(): void
    {
        settings()->set('tax.prices_include_tax', false);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->cartOf150($user);

        // nothing is added on top of the prices, so there is no portion to show
        $this->actingAs($user)->get('/checkout/details')->assertInertia(fn (Assert $page) => $page
            ->where('total', 155)->where('taxRate', null)->where('taxAmount', 0));

        $order = $this->orderFromCharge(15500, []);
        $this->assertEquals(0, $order->tax_amount);
        $this->assertNull($order->tax_rate);
    }

    public function test_the_order_pages_receive_the_stored_vat_and_a_plain_shipping_amount(): void
    {
        $order = Order::factory()->create([
            'user_id' => null, 'guest_email' => 'buyer@example.com', 'subtotal' => 150, 'shipping_amount' => 0,
            'discount_amount' => 0, 'total_amount' => 150, 'tax_amount' => 27.05, 'tax_rate' => 22,
        ]);

        $this->get($order->publicUrl())->assertInertia(fn (Assert $page) => $page
            ->component('Orders/GuestShow')
            ->where('order.shipping_amount', 0)->where('order.tax_amount', 27.05)->where('order.tax_rate', 22));

        // older orders have no stored rate
        $legacy = Order::factory()->create(['user_id' => null, 'guest_email' => 'b@example.com', 'tax_amount' => 0, 'tax_rate' => null]);
        $this->get($legacy->publicUrl())->assertInertia(fn (Assert $page) => $page->where('order.tax_rate', null));
    }

    // ------------------------------------------------------- minimum amount

    public function test_an_order_below_the_minimum_amount_is_refused_by_both_checkouts_before_reaching_stripe(): void
    {
        settings()->set('orders.min_amount', 200);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->cartOf150($user);
        $sid = Str::random(40);
        $this->cartOf150(null, $sid);

        $this->actingAs($user)->post('/checkout/cart', $this->checkoutForm())
            ->assertSessionHasErrors(['cart' => 'The minimum order amount is €200.00 (products only, shipping not included).']);
        $this->sessionCookie($sid)->post('/guest/checkout/cart', $this->checkoutForm())->assertSessionHasErrors('cart');

        $this->assertSame([], array_filter($this->stripeHttp->requests, fn ($request) => str_ends_with($request[1], '/v1/checkout/sessions')));
    }

    public function test_the_minimum_amount_is_off_at_zero_and_counts_products_only(): void
    {
        $sid = Str::random(40);
        $this->cartOf150(null, $sid);

        settings()->set('orders.min_amount', 150); // exactly the products: shipping does not count, nor is it required
        $this->sessionCookie($sid)->post('/guest/checkout/cart', $this->checkoutForm())->assertRedirect();
        $this->assertSame(15500, $this->createdSession()[0]);

        settings()->set('orders.min_amount', 0);
        $this->sessionCookie($sid)->post('/guest/checkout/cart', $this->checkoutForm())->assertSessionHasNoErrors();
    }

    // -------------------------------------------------- guest checkout switch

    public function test_with_guest_checkout_off_the_guest_pages_send_visitors_to_the_login_page(): void
    {
        settings()->set('orders.guest_checkout_enabled', false);
        $sid = Str::random(40);
        $this->cartOf150(null, $sid);

        $this->sessionCookie($sid)->get('/guest/checkout/details')
            ->assertRedirect(route('login'))->assertSessionHas('error', 'Guest checkout is not available. Please sign in or create an account to place your order.');
        $this->sessionCookie($sid)->post('/guest/checkout/cart', $this->checkoutForm())->assertRedirect(route('login'));
        $this->assertSame([], $this->stripeHttp->requests);

        // the switch reaches the frontend (the cart page hides the guest button) and does not affect registered customers
        $this->sessionCookie($sid)->get('/cart')->assertInertia(fn (Assert $page) => $page->where('settings', fn ($settings) => $settings['orders.guest_checkout_enabled'] === false));

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->cartOf150($user);
        $this->actingAs($user)->get('/checkout/details')->assertOk();
    }

    // ------------------------------------------- notes length and destinations

    public function test_the_order_note_length_and_the_destinations_come_from_the_settings(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->cartOf150($user);

        settings()->set('orders.order_notes_max', 60);
        $this->actingAs($user)->post('/checkout/cart', $this->checkoutForm(['order_notes' => str_repeat('a', 61)]))->assertSessionHasErrors('order_notes');
        $this->actingAs($user)->post('/checkout/cart', $this->checkoutForm(['order_notes' => str_repeat('a', 60)]))->assertSessionHasNoErrors();

        settings()->set('shipping.destinations', ['IT', 'FR']);
        $this->actingAs($user)->post('/checkout/cart', $this->checkoutForm(['shipping_address' => ['country' => 'DE']]))->assertSessionHasErrors('shipping_address.country');
        $this->actingAs($user)->post('/checkout/cart', $this->checkoutForm(['shipping_address' => ['country' => 'FR']]))->assertSessionHasNoErrors();

        // and the details page offers exactly those countries to the frontend
        $this->actingAs($user)->get('/checkout/details')->assertInertia(fn (Assert $page) => $page
            ->where('settings', fn ($settings) => $settings['shipping.destinations'] === ['IT', 'FR']
                && $settings['shipping.default_country'] === 'IT'
                && $settings['orders.order_notes_max'] === 60));
    }
}
