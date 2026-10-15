<?php

namespace Tests\Feature\Orders;

use App\Jobs\SendOrderEmail;
use App\Listeners\StripeWebhookListener;
use App\Models\Cart;
use App\Models\CheckoutSnapshot;
use App\Models\Order;
use App\Models\Product;
use App\Services\EmailService;
use App\Services\OrderCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Laravel\Cashier\Events\WebhookReceived;
use Mockery;
use Tests\Feature\Orders\Support\BuildsStripeOrderFixtures;
use Tests\TestCase;

/**
 * Delayed payment methods: completed-but-unpaid creates a pending order; async_payment_succeeded pays it,
 * async_payment_failed / expired close it (only while pending); payment_intent.* is not used.
 */
class StripeAsyncEventsTest extends TestCase
{
    use BuildsStripeOrderFixtures, RefreshDatabase;

    private Product $stocked;

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

    private function dispatch(string $type, array $session, string $eventId = 'evt_1'): void
    {
        app(StripeWebhookListener::class)->handle(new WebhookReceived([
            'id' => $eventId,
            'type' => $type,
            'data' => ['object' => $session],
        ]));
    }

    private function cart(string $sid = 'async-guest'): Cart
    {
        $cart = $this->makeCart(guestSessionId: $sid);
        $this->stocked = $cart->cartItems()->first()->product;
        $this->stocked->defaultVariant->update(['stock_quantity' => 10]);

        return $cart->fresh();
    }

    private function stock(): int
    {
        return $this->stocked->defaultVariant->fresh()->stock_quantity;
    }

    private function unpaid(Cart $cart, array $overrides = []): array
    {
        return $this->guestSessionPayload($cart, ['payment_status' => 'unpaid'] + $overrides);
    }

    public function test_async_payment_succeeded_pays_the_pending_order_and_does_everything_a_paid_order_gets(): void
    {
        $cart = $this->cart();
        $mock = Mockery::mock(EmailService::class);
        $mock->shouldReceive('sendOrderConfirmation')->andReturnUsing(function ($order) {
            $this->confirmationCalls[] = $order->id;

            return true;
        });
        $mock->shouldReceive('sendNewOrderAlert')->once()->andReturn(true);
        $this->app->instance(EmailService::class, $mock);

        $this->dispatch('checkout.session.completed', $this->unpaid($cart));
        $pending = Order::sole();
        $this->assertSame('pending', $pending->status);
        $this->assertSame([], $this->confirmationCalls);
        $this->assertNotNull(Cart::find($cart->id));
        $this->assertSame(10, $this->stock());

        $this->dispatch('checkout.session.async_payment_succeeded', $this->guestSessionPayload($cart), 'evt_2');
        $this->dispatch('checkout.session.async_payment_succeeded', $this->guestSessionPayload($cart), 'evt_3'); // replay

        $order = Order::sole();
        $this->assertSame($pending->id, $order->id);
        $this->assertSame('succeeded', $order->payment_status);
        $this->assertSame('processing', $order->status);
        $this->assertNull(Cart::find($cart->id));
        $this->assertSame([$order->id], array_values(array_unique($this->confirmationCalls)));
        $this->assertSame(8, $this->stock());
    }

    public function test_async_payment_failed_closes_a_pending_order_silently_and_keeps_cart_and_stock(): void
    {
        Queue::fake();
        $this->app->bind(EmailService::class, fn () => new EmailService);
        $cart = $this->cart();

        $this->dispatch('checkout.session.completed', $this->unpaid($cart));
        $this->dispatch('checkout.session.async_payment_failed', $this->unpaid($cart), 'evt_2');

        $order = Order::sole();
        $this->assertSame('failed', $order->payment_status);
        $this->assertSame('cancelled', $order->status);
        $this->assertNotNull(Cart::find($cart->id));
        $this->assertSame(10, $this->stock());
        $this->assertNull($order->stock_restored_at);
        Queue::assertNotPushed(SendOrderEmail::class); // never confirmed, so no customer mail (and no cancellation mail)
    }

    public function test_expired_cancels_the_pending_order_and_retires_the_snapshot(): void
    {
        $cart = $this->cart();
        $snapshot = CheckoutSnapshot::forceCreate([
            'lines' => [], 'subtotal' => 50, 'shipping' => 5, 'discount' => 0, 'tax' => 9.92, 'total' => 55,
            'total_cents' => 5500, 'currency' => 'EUR', 'cart_hash' => $cart->contentHash(),
        ]);
        $session = $this->unpaid($cart, ['metadata' => ['snapshot_id' => $snapshot->id]]);

        $this->dispatch('checkout.session.completed', $session);
        $this->dispatch('checkout.session.expired', $session, 'evt_2');

        $this->assertSame('cancelled', Order::sole()->payment_status);
        $this->assertSame('cancelled', Order::sole()->status);
        $this->assertNotNull($snapshot->fresh()->consumed_at);
    }

    public function test_expired_without_an_order_only_retires_the_snapshot(): void
    {
        $cart = $this->cart();
        $snapshot = CheckoutSnapshot::forceCreate([
            'stripe_session_id' => 'cs_test_123',
            'lines' => [], 'subtotal' => 50, 'shipping' => 5, 'discount' => 0, 'tax' => 9.92, 'total' => 55,
            'total_cents' => 5500, 'currency' => 'EUR', 'cart_hash' => $cart->contentHash(),
        ]);

        $this->dispatch('checkout.session.expired', $this->unpaid($cart, ['metadata' => ['snapshot_id' => $snapshot->id]]));

        $this->assertSame(0, Order::count());
        $this->assertNotNull($snapshot->fresh()->consumed_at);
        $this->assertNotNull(Cart::find($cart->id));
    }

    public function test_failed_and_expired_never_touch_a_paid_order(): void
    {
        $cart = $this->cart();
        $this->dispatch('checkout.session.completed', $this->guestSessionPayload($cart));
        $this->assertSame(8, $this->stock());

        $this->dispatch('checkout.session.async_payment_failed', $this->guestSessionPayload($cart), 'evt_2');
        $this->dispatch('checkout.session.expired', $this->guestSessionPayload($cart), 'evt_3');

        $order = Order::sole();
        $this->assertSame('succeeded', $order->payment_status);
        $this->assertSame('processing', $order->status);
        $this->assertSame(8, $this->stock());
        $this->assertNull($order->stock_restored_at);
    }

    public function test_an_expired_session_webhook_for_a_paid_processing_order_answers_200_and_changes_nothing(): void
    {
        config(['cashier.webhook.secret' => 'whsec_test_secret']);
        $cart = $this->cart();
        $this->dispatch('checkout.session.completed', $this->guestSessionPayload($cart));

        $body = json_encode(['id' => 'evt_exp', 'type' => 'checkout.session.expired', 'data' => ['object' => $this->guestSessionPayload($cart)]]);
        $timestamp = time();

        $this->call('POST', '/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test_secret'),
        ], $body)->assertOk();

        $order = Order::sole();
        $this->assertSame('processing', $order->status);
        $this->assertSame('succeeded', $order->payment_status);
    }

    public function test_sessions_of_other_products_are_ignored_by_the_closing_events(): void
    {
        $this->dispatch('checkout.session.expired', $this->sessionPayload(['payment_status' => 'unpaid', 'metadata' => []]));
        $this->dispatch('checkout.session.async_payment_failed', $this->sessionPayload(['payment_status' => 'unpaid', 'metadata' => []]));

        $this->assertSame(0, Order::count());
    }

    public function test_payment_intent_events_are_no_longer_handled(): void
    {
        $this->dispatch('payment_intent.succeeded', [
            'id' => 'pi_1',
            'metadata' => ['checkout_session_id' => 'cs_test_123'],
        ]);

        $this->assertSame(0, Order::count());
        $this->assertSame([], $this->stripeHttp->requests, 'no session is retrieved any more');
    }

    public function test_a_failure_while_closing_is_logged_as_critical_and_rethrown_so_stripe_retries(): void
    {
        $failing = Mockery::mock(OrderCreationService::class);
        $failing->shouldReceive('closeUnpaidSession')->andThrow(new \RuntimeException('database is down'));
        $this->app->instance(OrderCreationService::class, $failing);
        Log::spy();

        try {
            $this->dispatch('checkout.session.expired', $this->unpaid($this->cart()));
            $this->fail('The listener must rethrow so Stripe retries');
        } catch (\RuntimeException $e) {
            $this->assertSame('database is down', $e->getMessage());
        }

        Log::shouldHaveReceived('critical')->once()->withArgs(fn ($message, $context) => $context['session_id'] === 'cs_test_123');
    }

    public function test_async_success_for_a_foreign_session_creates_nothing(): void
    {
        $this->dispatch('checkout.session.async_payment_succeeded', $this->sessionPayload(['metadata' => []]));

        $this->assertSame(0, Order::count());
    }
}
