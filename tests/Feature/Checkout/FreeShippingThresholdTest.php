<?php

namespace Tests\Feature\Checkout;

use App\Models\Cart;
use App\Models\CheckoutSnapshot;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Orders\Support\BuildsStripeOrderFixtures;
use Tests\TestCase;

/**
 * Setting `shipping.free_threshold` (default 1000 EUR, 0 = off): when the items total after the coupon discount
 * reaches it, shipping is free. The cart page, both details pages, the snapshot, the Stripe amount and the stored
 * order all apply the same rule. `freeShippingRemaining` tells the pages how much is missing (null: no threshold).
 */
class FreeShippingThresholdTest extends TestCase
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

    /** A cart with one line at $price (size shipping 5.00), registered or guest. */
    private function cartOf(float $price, ?User $user = null, ?string $guestSessionId = null): Cart
    {
        $cart = Cart::create(['user_id' => $user?->id, 'session_id' => $guestSessionId]);
        $product = $this->makeProduct($price, 'Headlight');
        $cart->cartItems()->create([
            'product_id' => $product->id,
            'product_variant_id' => $product->defaultVariant->id,
            'size_id' => $this->size->id,
            'quantity' => 1,
            'price' => $price,
        ]);

        return $cart->fresh();
    }

    private function checkoutForm(): array
    {
        return [
            'email' => 'buyer@example.com',
            'shipping_address' => [
                'first_name' => 'Mario', 'last_name' => 'Rossi', 'address_line_1' => 'Via Roma 10', 'city' => 'Milano',
                'state' => 'MI', 'postal_code' => '20100', 'country' => 'IT', 'phone' => '+39 333 1234567',
            ],
            'order_notes' => '',
            'coupon_code' => '',
        ];
    }

    /** The Stripe request that created the checkout session: [unit_amount in cents, metadata]. */
    private function createdSession(): array
    {
        foreach (array_reverse($this->stripeHttp->requests) as [$method, $url, $params]) {
            if ($method === 'post' && str_ends_with($url, '/v1/checkout/sessions')) {
                return [(int) ($params['line_items'][0]['price_data']['unit_amount'] ?? $params['line_items'][0]['price_data']['unit_amount_decimal']), $params['metadata']];
            }
        }

        $this->fail('No Stripe checkout session was created');
    }

    private function sessionCookie(string $sessionId): static
    {
        return $this->withCookie(config('session.cookie'), $sessionId);
    }

    /** The order the paid session created above turns into (same session id, so the snapshot is used). */
    private function orderFromCharge(int $cents, array $metadata): Order
    {
        $payload = $this->sessionPayload([
            'id' => 'cs_test_created',
            'amount_subtotal' => $cents,
            'amount_total' => $cents,
            'metadata' => $metadata + ['source' => OrderCreationService::SESSION_SOURCE],
        ]);

        return app(OrderCreationService::class)->createFromStripeSession($payload);
    }

    // ------------------------------------------------------------------ the setting

    public function test_the_threshold_is_a_public_setting_defaulting_to_1000_with_labels_in_every_locale(): void
    {
        $this->assertEquals(1000, settings('shipping.free_threshold'));

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('settings', fn ($settings) => (float) $settings['shipping.free_threshold'] === 1000.0));

        foreach (['en', 'it', 'de', 'fr', 'es'] as $locale) {
            $strings = json_decode(file_get_contents(lang_path("{$locale}.json")), true);
            foreach (['settings.shipping.free_threshold.label', 'settings.shipping.free_threshold.help', 'shipping.free', 'shipping.free_remaining'] as $key) {
                $this->assertNotEmpty($strings[$key] ?? null, "{$key} is missing in lang/{$locale}.json");
            }
        }
    }

    // ------------------------------------------------------------------ the rule

    public function test_under_the_threshold_shipping_is_charged_everywhere(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->cartOf(999.99, $user);

        $this->actingAs($user)->get('/cart')->assertInertia(fn (Assert $page) => $page
            ->where('shippingCost', 5)->where('total', 1004.99)->where('freeShippingRemaining', 0.01));

        $this->actingAs($user)->get('/checkout/details')->assertInertia(fn (Assert $page) => $page
            ->where('shippingCost', 5)->where('total', 1004.99)->where('freeShippingRemaining', 0.01));

        $this->actingAs($user)->post('/checkout/cart', $this->checkoutForm())->assertRedirect();
        [$cents, $metadata] = $this->createdSession();
        $this->assertSame(100499, $cents);

        $this->assertEquals(5.00, $this->orderFromCharge($cents, $metadata)->shipping_amount);
    }

    public function test_exactly_at_the_threshold_shipping_is_free_in_cart_details_snapshot_stripe_and_order(): void
    {
        $sid = Str::random(40);
        $this->cartOf(1000.00, null, $sid);

        $this->sessionCookie($sid)->get('/cart')->assertInertia(fn (Assert $page) => $page
            ->where('shippingCost', 0)->where('total', 1000)->where('freeShippingRemaining', 0));

        $this->sessionCookie($sid)->get('/guest/checkout/details')->assertInertia(fn (Assert $page) => $page
            ->where('shippingCost', 0)->where('total', 1000)->where('taxAmount', 180.33)->where('freeShippingRemaining', 0));

        $this->sessionCookie($sid)->post('/guest/checkout/cart', $this->checkoutForm())->assertRedirect('https://checkout.stripe.test/c/pay/cs_test_created');

        [$cents, $metadata] = $this->createdSession();
        $this->assertSame(100000, $cents);

        $snapshot = CheckoutSnapshot::findOrFail($metadata['snapshot_id']);
        $this->assertEquals(0.00, $snapshot->shipping);
        $this->assertSame(100000, (int) $snapshot->total_cents);

        $order = $this->orderFromCharge($cents, $metadata);
        $this->assertEquals(0.00, $order->shipping_amount);
        $this->assertEquals(1000.00, $order->total_amount);
    }

    public function test_a_coupon_that_takes_the_items_below_the_threshold_brings_the_shipping_back(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->cartOf(1000.00, $user);
        $coupon = ['code' => 'TEN', 'description' => '10% off', 'discount_type' => 'percentage', 'discount_percentage' => 10, 'discount_amount' => 100];

        // 1000 - 100 = 900 of items, under 1000: 900 + 5 shipping
        $this->withSession(['applied_coupon' => $coupon])->actingAs($user)->get('/cart')->assertInertia(fn (Assert $page) => $page
            ->where('discountAmount', 100)->where('shippingCost', 5)->where('total', 905)->where('freeShippingRemaining', 100));

        $this->withSession(['applied_coupon' => $coupon])->actingAs($user)->get('/checkout/details')->assertInertia(fn (Assert $page) => $page
            ->where('discountAmount', 100)->where('shippingCost', 5)->where('total', 905)->where('freeShippingRemaining', 100));

        $this->fakePromotionCode('TEN');
        $this->withSession(['applied_coupon' => $coupon])->actingAs($user)->post('/checkout/cart', $this->checkoutForm())->assertRedirect();
        [$cents, $metadata] = $this->createdSession();
        $this->assertSame(90500, $cents);

        $order = $this->orderFromCharge($cents, $metadata);
        $this->assertEquals(5.00, $order->shipping_amount);
        $this->assertEquals(905.00, $order->total_amount);
    }

    public function test_a_threshold_of_zero_turns_free_shipping_off(): void
    {
        settings()->set('shipping.free_threshold', 0);
        $sid = Str::random(40);
        $this->cartOf(5000.00, null, $sid);

        $this->sessionCookie($sid)->get('/cart')->assertInertia(fn (Assert $page) => $page
            ->where('shippingCost', 5)->where('total', 5005)->where('freeShippingRemaining', null));

        $this->sessionCookie($sid)->get('/guest/checkout/details')->assertInertia(fn (Assert $page) => $page
            ->where('shippingCost', 5)->where('total', 5005)->where('freeShippingRemaining', null));

        $this->sessionCookie($sid)->post('/guest/checkout/cart', $this->checkoutForm())->assertRedirect();
        [$cents, $metadata] = $this->createdSession();
        $this->assertSame(500500, $cents);
        $this->assertEquals(5.00, CheckoutSnapshot::findOrFail($metadata['snapshot_id'])->shipping);
    }

    public function test_a_lower_threshold_set_by_the_admin_applies_at_once(): void
    {
        settings()->set('shipping.free_threshold', 100);
        $sid = Str::random(40);
        $this->cartOf(150.00, null, $sid);

        $this->sessionCookie($sid)->get('/guest/checkout/details')->assertInertia(fn (Assert $page) => $page
            ->where('shippingCost', 0)->where('total', 150)->where('freeShippingRemaining', 0));
    }
}
