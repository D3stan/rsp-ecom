<?php

namespace Tests\Feature\Orders;

use App\Listeners\StripeWebhookListener;
use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Cashier\Events\WebhookReceived;
use Mockery;
use Tests\Feature\Orders\Support\BuildsStripeOrderFixtures;
use Tests\TestCase;

class CheckoutSuccessOrderTest extends TestCase
{
    use BuildsStripeOrderFixtures, RefreshDatabase, WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrderFixtures();
        $this->fakeEmailService();
    }

    protected function tearDown(): void
    {
        $this->tearDownOrderFixtures();
        parent::tearDown();
    }

    /**
     * Stripe returns the session with a creation timestamp when retrieved over the API.
     */
    private function stripeReturns(array $session): void
    {
        $this->stripeHttp->responses['/v1/checkout/sessions/'.$session['id']] = $session + ['created' => 1760000000];
    }

    private function webhook(array $session): void
    {
        app(StripeWebhookListener::class)->handle(new WebhookReceived([
            'id' => 'evt_'.$session['id'],
            'type' => 'checkout.session.completed',
            'data' => ['object' => $session],
        ]));
    }

    private function comparable(Order $order): array
    {
        return [
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'subtotal' => (string) $order->subtotal,
            'tax_amount' => (string) $order->tax_amount,
            'shipping_amount' => (string) $order->shipping_amount,
            'discount_amount' => (string) $order->discount_amount,
            'coupon_code' => $order->coupon_code,
            'total_amount' => (string) $order->total_amount,
            'currency' => $order->currency,
            'locale' => $order->locale,
            'guest_email' => $order->guest_email,
            'items' => $order->orderItems->map(fn ($i) => [
                $i->product_id, $i->product_variant_id, $i->size_id, $i->product_name, $i->quantity, (string) $i->price, (string) $i->total,
            ])->all(),
            'shipping_address' => $order->shippingAddress->only(['first_name', 'last_name', 'address_line_1', 'city', 'state', 'postal_code', 'country']),
        ];
    }

    public function test_success_page_creates_the_order_and_renders_it(): void
    {
        $cart = $this->makeCart(guestSessionId: 'sp-guest-1');
        $session = $this->guestSessionPayload($cart);
        $this->stripeReturns($session);

        $response = $this->get('/checkout/success?session_id=cs_test_123');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Checkout/Success')
            ->where('order.total_amount', 55)
            ->where('order.payment_status', 'succeeded')
            ->has('order.orderItems', 1)
            ->where('isGuest', true)
        );

        $order = Order::sole();
        $this->assertSame([$order->id], $this->confirmationCalls);
        $this->assertNull(Cart::find($cart->id));
    }

    public function test_success_page_then_webhook_gives_one_order(): void
    {
        $cart = $this->makeCart(guestSessionId: 'sp-guest-2');
        $session = $this->guestSessionPayload($cart);
        $this->stripeReturns($session);

        $this->get('/checkout/success?session_id=cs_test_123')->assertOk();
        $this->webhook($session);

        $this->assertSame(1, Order::count());
        $this->assertSame(1, Order::sole()->orderItems()->count());
        $this->assertSame([Order::sole()->id], array_values(array_unique($this->confirmationCalls)));
    }

    public function test_webhook_then_success_page_gives_one_order(): void
    {
        $cart = $this->makeCart(guestSessionId: 'sp-guest-3');
        $session = $this->guestSessionPayload($cart);
        $this->stripeReturns($session);

        $this->webhook($session);
        $response = $this->get('/checkout/success?session_id=cs_test_123');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Checkout/Success')
            ->where('order.total_amount', 55)
            ->has('order.orderItems', 1)
        );
        $this->assertSame(1, Order::count());
        $this->assertSame([Order::sole()->id], array_values(array_unique($this->confirmationCalls)));
    }

    public function test_order_is_identical_whichever_path_creates_it(): void
    {
        // Same cart contents and coupon, one order created by the webhook, one by the success page.
        $overrides = ['amount_total' => 5000, 'metadata' => ['coupon_code' => 'WELCOME10', 'locale' => 'de']];

        $cartA = $this->makeCart(guestSessionId: 'parity-a');
        $sessionA = $this->guestSessionPayload($cartA, $overrides + ['id' => 'cs_parity_a']);
        $this->webhook($sessionA);

        $cartB = $this->makeCart(guestSessionId: 'parity-b');
        $sessionB = $this->guestSessionPayload($cartB, $overrides + ['id' => 'cs_parity_b']);
        $this->stripeReturns($sessionB);
        $this->get('/checkout/success?session_id=cs_parity_b')->assertOk();

        $viaWebhook = Order::where('stripe_checkout_session_id', 'cs_parity_a')->firstOrFail();
        $viaSuccess = Order::where('stripe_checkout_session_id', 'cs_parity_b')->firstOrFail();

        $a = $this->comparable($viaWebhook);
        $b = $this->comparable($viaSuccess);

        // product ids differ per cart fixture; everything the customer/admin sees must match
        foreach ([&$a, &$b] as &$data) {
            $data['items'] = array_map(fn ($item) => array_slice($item, 3), $data['items']);
        }
        unset($data);

        $this->assertSame($a, $b);
        $this->assertSame('5.00', $a['discount_amount']);
        $this->assertSame('WELCOME10', $a['coupon_code']);
        $this->assertSame('de', $a['locale']);
    }

    public function test_registered_user_checkout_via_success_page(): void
    {
        $user = User::factory()->create();
        $cart = $this->makeCart($user);
        $session = $this->userSessionPayload($user, $cart);
        $this->stripeReturns($session);

        $response = $this->actingAs($user)->get('/checkout/success?session_id=cs_test_123');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Checkout/Success')->where('isGuest', false));
        $this->assertSame($user->id, Order::sole()->user_id);
    }

    public function test_success_page_still_renders_when_order_creation_fails_after_payment(): void
    {
        $cart = $this->makeCart(guestSessionId: 'sp-guest-4');
        $session = $this->guestSessionPayload($cart);
        $this->stripeReturns($session);

        $failing = Mockery::mock(OrderCreationService::class);
        $failing->shouldReceive('createFromStripeSession')->andThrow(new \RuntimeException('db down'));
        $this->app->instance(OrderCreationService::class, $failing);

        $response = $this->get('/checkout/success?session_id=cs_test_123');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Checkout/Success')
            ->where('order.order_number', 'PENDING')
            ->where('order.total_amount', 55)
        );
        $this->assertSame(0, Order::count());
    }

    public function test_cancel_route_closes_an_unpaid_order(): void
    {
        $order = Order::factory()->create([
            'status' => 'pending',
            'payment_status' => 'pending',
            'stripe_checkout_session_id' => 'cs_cancel_1',
        ]);

        $this->get('/checkout/cancel?session_id=cs_cancel_1')->assertOk();

        $order->refresh();
        $this->assertSame('cancelled', $order->payment_status);
        $this->assertSame('cancelled', $order->status);
    }

    public function test_cancel_route_never_touches_a_paid_order(): void
    {
        $order = Order::factory()->create([
            'status' => 'processing',
            'payment_status' => 'succeeded',
            'stripe_checkout_session_id' => 'cs_cancel_2',
        ]);

        $this->get('/checkout/cancel?session_id=cs_cancel_2')->assertOk();

        $order->refresh();
        $this->assertSame('succeeded', $order->payment_status);
        $this->assertSame('processing', $order->status);
    }

    public function test_success_page_for_a_foreign_session_creates_no_order(): void
    {
        $session = $this->sessionPayload(['id' => 'cs_foreign', 'metadata' => []]);
        $this->stripeReturns($session);

        $response = $this->get('/checkout/success?session_id=cs_foreign');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Checkout/Success')
            ->where('order.order_number', 'PENDING')
        );
        $this->assertSame(0, Order::count());
        $this->assertSame([], $this->confirmationCalls);
    }
}
