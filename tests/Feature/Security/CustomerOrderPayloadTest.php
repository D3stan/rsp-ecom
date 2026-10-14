<?php

namespace Tests\Feature\Security;

use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Orders\Support\BuildsStripeOrderFixtures;
use Tests\TestCase;

/**
 * Pages a customer sees must carry only what they show: never the internal admin notes, Stripe ids or the
 * guest session id stored on the order.
 */
class CustomerOrderPayloadTest extends TestCase
{
    use BuildsStripeOrderFixtures, RefreshDatabase;

    private const SECRETS = [
        'INTERNAL-SECRET',
        'pi_secret_intent',
        'cs_secret_session',
        'cus_secret_customer',
        'guest-session-secret',
    ];

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

    private function orderWithInternals(?User $user): Order
    {
        $order = Order::factory()->create([
            'user_id' => $user?->id,
            'guest_email' => $user ? null : 'buyer@example.test',
            'status' => 'shipped',
            'payment_status' => 'succeeded',
            'tracking_number' => 'TRK-PUBLIC-1',
            'notes' => "INTERNAL-SECRET: refund to IBAN IT60X0542811101000000123456\nShipping dump ...",
            'stripe_payment_intent_id' => 'pi_secret_intent',
            'stripe_checkout_session_id' => 'cs_secret_session',
            'stripe_customer_id' => 'cus_secret_customer',
            'guest_session_id' => 'guest-session-secret',
            'shipping_address_id' => Address::factory()->shipping()->create(['user_id' => null, 'address_line_1' => 'Via Roma 10'])->id,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => null,
            'product_name' => 'Anello Argento',
            'quantity' => 1,
            'price' => 25.00,
            'total' => 25.00,
        ]);

        return $order;
    }

    private function assertNoSecrets($response): void
    {
        $props = json_encode($response->viewData('page')['props']);

        $leaked = array_values(array_filter(self::SECRETS, fn (string $needle) => str_contains($props, $needle)));

        $this->assertSame([], $leaked, 'Customer page props leak internal order data');
    }

    public function test_my_orders_page_does_not_leak_internal_order_data(): void
    {
        $user = User::factory()->create();
        $order = $this->orderWithInternals($user);

        $response = $this->actingAs($user)->get(route('orders.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Orders')
            ->where('orders.0.id', $order->id)
            ->where('orders.0.status', 'shipped')
            ->where('orders.0.tracking_number', 'TRK-PUBLIC-1')
            ->has('orders.0.created_at')
            ->has('orders.0.total_amount')
            ->where('orders.0.order_items.0.product_name', 'Anello Argento')
        );
        $this->assertNoSecrets($response);
    }

    public function test_account_dashboard_does_not_leak_internal_order_data(): void
    {
        $user = User::factory()->create();
        $order = $this->orderWithInternals($user);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Dashboard')
            ->where('recentOrders.0.id', $order->id)
            ->where('recentOrders.0.status', 'shipped')
            ->has('recentOrders.0.total_amount')
            ->has('recentOrders.0.order_items', 1)
        );
        $this->assertNoSecrets($response);
    }

    public function test_signed_order_page_does_not_leak_internal_order_data(): void
    {
        $order = $this->orderWithInternals(null);

        $response = $this->get($order->publicUrl());

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Orders/GuestShow')
            ->where('order.order_number', $order->order_number)
            ->where('order.tracking_number', 'TRK-PUBLIC-1')
        );
        $this->assertNoSecrets($response);
    }

    public function test_signed_order_page_of_a_deleted_account_renders_without_leaking(): void
    {
        $user = User::factory()->create();
        $order = $this->orderWithInternals($user);
        $user->delete();

        $response = $this->get($order->fresh()->publicUrl());

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Orders/GuestShow')
            ->where('order.order_number', $order->order_number)
            ->where('order.items.0.name', 'Anello Argento')
            ->where('order.shipping_address.line1', 'Via Roma 10')
        );
        $this->assertNoSecrets($response);
    }

    public function test_checkout_success_page_does_not_leak_internal_order_data_to_the_buyer(): void
    {
        $sid = Str::random(40);
        $cart = $this->makeCart(guestSessionId: $sid);
        $this->stripeHttp->responses['/v1/checkout/sessions/cs_test_123'] = $this->guestSessionPayload($cart) + ['created' => 1760000000];

        // First visit creates the order; the admin then works on it, and the buyer reopens the page
        $this->withCookie(config('session.cookie'), $sid)->get('/checkout/success?session_id=cs_test_123')->assertOk();
        Order::sole()->forceFill([
            'notes' => 'INTERNAL-SECRET: customer called, refund 10 EUR',
            'stripe_payment_intent_id' => 'pi_secret_intent',
            'stripe_customer_id' => 'cus_secret_customer',
        ])->save();

        $response = $this->withCookie(config('session.cookie'), $sid)->get('/checkout/success?session_id=cs_test_123');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Checkout/Success')
            ->where('restricted', false)
            ->where('session.customer_email', 'buyer@example.com')
            ->where('order.total_amount', 55)
            ->has('order.order_number')
            ->has('order.orderItems', 1)
            ->missing('order.notes')
            ->missing('order.stripe_checkout_session_id')
            ->missing('order.stripe_payment_intent_id')
            ->missing('order.stripe_customer_id')
        );
        $this->assertNoSecrets($response);
    }
}
