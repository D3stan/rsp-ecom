<?php

namespace Tests\Feature\Orders;

use App\Listeners\StripeWebhookListener;
use App\Models\Cart;
use App\Models\Order;
use App\Services\OrderCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;
use Mockery;
use Tests\Feature\Orders\Support\BuildsStripeOrderFixtures;
use Tests\TestCase;

class StripeWebhookOrderTest extends TestCase
{
    use BuildsStripeOrderFixtures, RefreshDatabase;

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

    private function dispatchWebhook(array $payload): void
    {
        app(StripeWebhookListener::class)->handle(new WebhookReceived($payload));
    }

    private function sessionCompletedEvent(array $session, string $eventId = 'evt_1'): array
    {
        return [
            'id' => $eventId,
            'type' => 'checkout.session.completed',
            'data' => ['object' => $session],
        ];
    }

    public function test_checkout_session_completed_creates_the_order_and_requests_one_confirmation(): void
    {
        $cart = $this->makeCart(guestSessionId: 'wh-guest-1');

        $this->dispatchWebhook($this->sessionCompletedEvent($this->guestSessionPayload($cart)));

        $order = Order::sole();
        $this->assertSame('cs_test_123', $order->stripe_checkout_session_id);
        $this->assertSame('succeeded', $order->payment_status);
        $this->assertSame(1, $order->orderItems()->count());
        $this->assertSame([$order->id], $this->confirmationCalls);
        $this->assertNull(Cart::find($cart->id));
    }

    public function test_unpaid_checkout_session_completed_creates_a_pending_order_and_keeps_the_cart(): void
    {
        $cart = $this->makeCart(guestSessionId: 'wh-guest-2');

        $this->dispatchWebhook($this->sessionCompletedEvent(
            $this->guestSessionPayload($cart, ['payment_status' => 'unpaid'])
        ));

        // Delayed payment method: the order exists (so the async event always finds it) but is not paid yet
        $order = Order::sole();
        $this->assertSame('pending', $order->status);
        $this->assertSame('processing', $order->payment_status);
        $this->assertSame([], $this->confirmationCalls);
        $this->assertNotNull(Cart::find($cart->id));
    }

    public function test_same_event_delivered_twice_yields_one_order(): void
    {
        $cart = $this->makeCart(guestSessionId: 'wh-guest-3');
        $event = $this->sessionCompletedEvent($this->guestSessionPayload($cart));

        $this->dispatchWebhook($event);
        $this->dispatchWebhook($event);

        $this->assertSame(1, Order::count());
        $this->assertSame(1, Order::sole()->orderItems()->count());
        // EmailService is idempotent; the same single order is the only one ever requested
        $this->assertSame([Order::sole()->id], array_values(array_unique($this->confirmationCalls)));
    }

    public function test_unhandled_event_types_are_ignored(): void
    {
        $this->dispatchWebhook(['id' => 'evt_x', 'type' => 'charge.refunded', 'data' => ['object' => ['id' => 'ch_1']]]);

        $this->assertSame(0, Order::count());
    }

    public function test_failure_is_logged_as_critical_and_rethrown_so_stripe_retries(): void
    {
        $failing = Mockery::mock(OrderCreationService::class);
        $failing->shouldReceive('createFromStripeSession')->andThrow(new \RuntimeException('database is down'));
        $this->app->instance(OrderCreationService::class, $failing);

        Log::spy();

        try {
            $this->dispatchWebhook($this->sessionCompletedEvent($this->sessionPayload()));
            $this->fail('The listener must rethrow so the webhook answers with a non-2xx status');
        } catch (\RuntimeException $e) {
            $this->assertSame('database is down', $e->getMessage());
        }

        Log::shouldHaveReceived('critical')->once()->withArgs(function ($message, $context) {
            return $context['session_id'] === 'cs_test_123' && $context['event_id'] === 'evt_1';
        });
    }

    public function test_replayed_webhook_after_a_failed_attempt_recovers(): void
    {
        $cart = $this->makeCart(guestSessionId: 'wh-guest-6');
        $event = $this->sessionCompletedEvent($this->guestSessionPayload($cart));

        $attempts = 0;
        \App\Models\OrderItem::creating(function () use (&$attempts) {
            if ($attempts++ === 0) {
                throw new \RuntimeException('transient failure');
            }
        });

        try {
            $this->dispatchWebhook($event);
            $this->fail('First delivery should fail');
        } catch (\RuntimeException) {
            $this->assertSame(0, Order::count());
        }

        // Stripe retries the same event
        $this->dispatchWebhook($event);
        \App\Models\OrderItem::flushEventListeners();

        $this->assertSame(1, Order::count());
        $this->assertSame(1, Order::sole()->orderItems()->count());
    }

    public function test_foreign_paid_session_is_skipped_with_no_order_address_or_email(): void
    {
        // a paid session of another Stripe-account product: no shop metadata at all
        $this->dispatchWebhook($this->sessionCompletedEvent($this->sessionPayload(['metadata' => []])));

        $this->assertSame(0, Order::count());
        $this->assertSame(0, \App\Models\Address::count());
        $this->assertSame([], $this->confirmationCalls);
    }

    public function test_legacy_metadata_session_still_creates_an_order(): void
    {
        $user = \App\Models\User::factory()->create();
        $cart = $this->makeCart($user);

        $this->dispatchWebhook($this->sessionCompletedEvent($this->userSessionPayload($user, $cart)));

        $this->assertSame(1, Order::count());
    }
}
