<?php

namespace Tests\Feature\Orders\Support;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Size;
use App\Models\User;
use App\Services\EmailService;
use Mockery;
use Mockery\MockInterface;
use Stripe\ApiRequestor;
use Stripe\Stripe;

trait BuildsStripeOrderFixtures
{
    protected FakeStripeHttpClient $stripeHttp;

    /** @var array<int, int> order ids passed to EmailService::sendOrderConfirmation */
    protected array $confirmationCalls = [];

    protected Category $category;

    protected Size $size;

    protected function setUpOrderFixtures(): void
    {
        // Never talk to the real Stripe API, whatever keys the local .env holds.
        $this->stripeHttp = new FakeStripeHttpClient;
        ApiRequestor::setHttpClient($this->stripeHttp);
        Setting::set('stripe_secret_key', 'sk_test_fake_for_tests');

        settings()->set('tax.rate', 22);
        settings()->set('tax.prices_include_tax', true);

        $this->category = Category::factory()->create();
        $this->size = Size::factory()->create(['shipping_cost' => 5.00]);
    }

    protected function tearDownOrderFixtures(): void
    {
        ApiRequestor::setHttpClient(null);
        Stripe::setApiKey(null);
    }

    /**
     * Answer Stripe's promotion-code lookup, which the checkout repeats before charging a cart that carries a coupon.
     * $coupon overrides fields of the coupon (e.g. ['valid' => false] or ['redeem_by' => time() - 60]), $promotion those
     * of the promotion code itself (e.g. ['max_redemptions' => 2] or ['restrictions' => ['first_time_transaction' => true]]).
     */
    protected function fakePromotionCode(string $code, array $coupon = [], bool $found = true, array $promotion = []): void
    {
        // PromotionService calls Stripe statically, which needs the global key AppServiceProvider sets in production
        Stripe::setApiKey('sk_test_fake_for_tests');
        $this->stripeHttp->responses['GET /v1/promotion_codes'] = [
            'object' => 'list',
            'data' => $found ? [array_merge([
                'id' => 'promo_test_1',
                'object' => 'promotion_code',
                'code' => $code,
                'active' => true,
                'expires_at' => null,
                'max_redemptions' => null,
                'times_redeemed' => 0,
                'customer' => null,
                'restrictions' => ['first_time_transaction' => false, 'minimum_amount' => null, 'minimum_amount_currency' => null],
                'coupon' => array_merge([
                    'id' => 'coupon_test_1',
                    'object' => 'coupon',
                    'name' => $code,
                    'valid' => true,
                    'percent_off' => 10,
                    'amount_off' => null,
                    'redeem_by' => null,
                    'max_redemptions' => null,
                    'times_redeemed' => 0,
                ], $coupon),
            ], $promotion)] : [],
            'has_more' => false,
        ];
    }

    /**
     * Swap EmailService for a mock that records which orders a confirmation was requested for.
     */
    protected function fakeEmailService(?\Closure $callback = null): MockInterface
    {
        $this->confirmationCalls = [];

        $mock = Mockery::mock(EmailService::class);
        $mock->shouldReceive('sendOrderConfirmation')->andReturnUsing(function ($order) use ($callback) {
            $this->confirmationCalls[] = $order->id;

            return $callback ? $callback($order) : true;
        });

        $this->app->instance(EmailService::class, $mock);

        return $mock;
    }

    protected function makeProduct(float $price = 25.00, ?string $name = null): Product
    {
        $attributes = [
            'category_id' => $this->category->id,
            'size_id' => $this->size->id,
            'price' => $price,
            // The factory randomises both; tests that care about stock set it explicitly
            'stock_quantity' => 100,
            'status' => 'active',
        ];

        if ($name) {
            $attributes['name'] = $name;
        }

        return Product::factory()->create($attributes);
    }

    /**
     * Cart with one item: 2 x 25.00 (size shipping 5.00) => items 50.00, shipping 5.00.
     */
    protected function makeCart(?User $user = null, ?string $guestSessionId = null, int $quantity = 2): Cart
    {
        $cart = Cart::create([
            'user_id' => $user?->id,
            'session_id' => $guestSessionId,
        ]);

        $product = $this->makeProduct(25.00, 'Test Product');

        $cart->cartItems()->create([
            'product_id' => $product->id,
            'product_variant_id' => $product->defaultVariant->id,
            'size_id' => $this->size->id,
            'quantity' => $quantity,
            'price' => 25.00,
        ]);

        return $cart->fresh();
    }

    /**
     * Stripe checkout.session payload, as the webhook delivers it (nested arrays).
     */
    protected function sessionPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 'cs_test_123',
            'object' => 'checkout.session',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_test_123',
            'amount_subtotal' => 5500,
            'amount_total' => 5500,
            'currency' => 'eur',
            'customer_details' => [
                'email' => 'buyer@example.com',
                'name' => 'Mario Rossi',
                'phone' => null,
                'address' => ['line1' => 'Via Stripe 1', 'city' => 'Roma', 'state' => 'RM', 'postal_code' => '00100', 'country' => 'IT'],
            ],
            'total_details' => ['amount_discount' => 0, 'amount_shipping' => 0, 'amount_tax' => 0],
            'metadata' => [
                'shipping_address' => 'Mario Rossi, Via Roma 10, Milano, MI 20100, IT',
                'customer_phone' => '+39 333 1234567',
                'order_notes' => '',
                'coupon_code' => '',
                'locale' => 'it',
            ],
        ], $overrides);
    }

    protected function guestSessionPayload(Cart $cart, array $overrides = []): array
    {
        return $this->sessionPayload(array_replace_recursive([
            'metadata' => [
                'guest_session_id' => $cart->session_id,
                'guest_email' => 'buyer@example.com',
                'type' => 'guest_cart_checkout',
            ],
        ], $overrides));
    }

    protected function userSessionPayload(User $user, Cart $cart, array $overrides = []): array
    {
        return $this->sessionPayload(array_replace_recursive([
            'metadata' => [
                'cart_id' => (string) $cart->id,
                'user_id' => (string) $user->id,
                'user_email' => $user->email,
                'type' => 'cart_checkout',
            ],
        ], $overrides));
    }
}
