<?php

namespace Tests\Feature\Mail\Lifecycle;

use App\Jobs\SendOrderEmail;
use App\Listeners\StripeWebhookListener;
use App\Mail\PaidOrderMissingAlert;
use App\Models\Order;
use App\Models\OrderEmail;
use App\Services\EmailService;
use App\Services\OrderCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Cashier\Events\WebhookReceived;
use Mockery;
use Tests\Feature\Orders\Support\BuildsStripeOrderFixtures;
use Tests\TestCase;

class AdminAlertsTest extends TestCase
{
    use BuildsStripeOrderFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrderFixtures();
        config(['mail.contact_email' => 'owner@shop.test']);
    }

    protected function tearDown(): void
    {
        $this->tearDownOrderFixtures();
        parent::tearDown();
    }

    private function newOrderAlerts()
    {
        return Queue::pushed(SendOrderEmail::class, fn ($job) => $job->type === OrderEmail::TYPE_ADMIN_NEW_ORDER);
    }

    private function dispatchWebhook(array $session, string $eventId = 'evt_1'): void
    {
        app(StripeWebhookListener::class)->handle(new WebhookReceived([
            'id' => $eventId,
            'type' => 'checkout.session.completed',
            'data' => ['object' => $session],
        ]));
    }

    private function failingOrderCreation(): void
    {
        $failing = Mockery::mock(OrderCreationService::class);
        $failing->shouldReceive('createFromStripeSession')->andThrow(new \RuntimeException('database is down'));
        $this->app->instance(OrderCreationService::class, $failing);
    }

    // ------------------------------------------------------------ new order alert

    public function test_a_newly_created_paid_order_alerts_the_admin_once_across_webhook_and_success_page_replays(): void
    {
        Queue::fake();
        $cart = $this->makeCart(guestSessionId: 'alert-guest-1');
        $session = $this->guestSessionPayload($cart);

        $this->dispatchWebhook($session);                                                    // webhook creates
        $this->dispatchWebhook($session, 'evt_replay');                                      // Stripe replay
        app(OrderCreationService::class)->createFromStripeSession($session);                // success page after webhook

        $this->assertSame(1, Order::count());
        $this->assertCount(1, $this->newOrderAlerts());
        $this->assertSame(1, OrderEmail::where('type', OrderEmail::TYPE_ADMIN_NEW_ORDER)->count());
    }

    public function test_the_service_only_asks_for_the_alert_on_first_creation(): void
    {
        // Independent of order_emails idempotency: replays must not even call EmailService.
        $mock = Mockery::mock(EmailService::class);
        $mock->shouldReceive('sendOrderConfirmation')->andReturn(true);
        $mock->shouldReceive('sendNewOrderAlert')->once()->andReturn(true);
        $this->app->instance(EmailService::class, $mock);

        $cart = $this->makeCart(guestSessionId: 'alert-guest-2');
        $session = $this->guestSessionPayload($cart);

        app(OrderCreationService::class)->createFromStripeSession($session);
        app(OrderCreationService::class)->createFromStripeSession($session);
        app(OrderCreationService::class)->createFromStripeSession($session);

        $this->assertSame(1, Order::count());
    }

    public function test_an_unpaid_session_does_not_alert_until_it_is_paid(): void
    {
        $mock = Mockery::mock(EmailService::class);
        $mock->shouldReceive('sendOrderConfirmation')->andReturn(true);
        $mock->shouldReceive('sendNewOrderAlert')->once()->andReturn(true);
        $this->app->instance(EmailService::class, $mock);

        $cart = $this->makeCart(guestSessionId: 'alert-guest-3');
        $unpaid = $this->guestSessionPayload($cart, ['payment_status' => 'unpaid']);
        $paid = $this->guestSessionPayload($cart);

        app(OrderCreationService::class)->createFromStripeSession($unpaid);   // no alert
        app(OrderCreationService::class)->createFromStripeSession($paid);     // becomes paid: alert
        app(OrderCreationService::class)->createFromStripeSession($paid);     // replay: nothing

        $this->assertSame(1, Order::count());
    }

    public function test_a_failing_alert_never_fails_order_creation(): void
    {
        $mock = Mockery::mock(EmailService::class);
        $mock->shouldReceive('sendOrderConfirmation')->andReturn(true);
        $mock->shouldReceive('sendNewOrderAlert')->andThrow(new \RuntimeException('queue is down'));
        $this->app->instance(EmailService::class, $mock);

        $cart = $this->makeCart(guestSessionId: 'alert-guest-4');

        $order = app(OrderCreationService::class)->createFromStripeSession($this->guestSessionPayload($cart));

        $this->assertNotNull($order);
        $this->assertSame(1, Order::count());
    }

    // ------------------------------------------------------------ paid but no order

    public function test_a_paid_session_that_cannot_become_an_order_alerts_the_admin_once_and_still_rethrows(): void
    {
        Mail::fake();
        $this->failingOrderCreation();
        $session = $this->sessionPayload(['metadata' => ['cart_id' => '99', 'guest_email' => 'late@example.test']]);

        foreach (['evt_1', 'evt_2'] as $eventId) {      // Stripe retries the delivery
            try {
                $this->dispatchWebhook($session, $eventId);
                $this->fail('The original exception must still reach Stripe');
            } catch (\RuntimeException $e) {
                $this->assertSame('database is down', $e->getMessage());
            }
        }

        Mail::assertQueued(PaidOrderMissingAlert::class, 1);
        Mail::assertQueued(PaidOrderMissingAlert::class, fn (PaidOrderMissingAlert $mail) => $mail->hasTo('owner@shop.test')
            && $mail->sessionId === 'cs_test_123'
            && $mail->details['amount'] === '55.00 EUR'
            && $mail->details['customer_email'] === 'buyer@example.com'
            && str_contains($mail->details['error'], 'database is down'));
    }

    public function test_the_alert_is_allowed_again_after_the_dedupe_window(): void
    {
        Mail::fake();
        $this->failingOrderCreation();

        $this->assertTrue(app(EmailService::class)->sendPaidOrderMissingAlert('cs_a'));
        $this->assertTrue(app(EmailService::class)->sendPaidOrderMissingAlert('cs_a'));     // deduped
        $this->assertTrue(app(EmailService::class)->sendPaidOrderMissingAlert('cs_b'));     // other session
        Mail::assertQueued(PaidOrderMissingAlert::class, 2);

        Cache::forget('paid-order-missing:cs_a');
        app(EmailService::class)->sendPaidOrderMissingAlert('cs_a');
        Mail::assertQueued(PaidOrderMissingAlert::class, 3);
    }

    public function test_the_alert_goes_to_the_admin_setting_even_when_customer_mail_switches_are_off(): void
    {
        Mail::fake();
        settings()->set('contact.admin_notification_email', 'boss@shop.test');
        settings()->set('email.enabled', false);

        app(EmailService::class)->sendPaidOrderMissingAlert('cs_c');

        Mail::assertQueued(PaidOrderMissingAlert::class, fn ($mail) => $mail->hasTo('boss@shop.test'));
    }

    public function test_a_broken_alert_never_masks_the_original_exception(): void
    {
        $this->failingOrderCreation();
        $mock = Mockery::mock(EmailService::class);
        $mock->shouldReceive('sendPaidOrderMissingAlert')->andThrow(new \LogicException('mail layer exploded'));
        $this->app->instance(EmailService::class, $mock);

        try {
            $this->dispatchWebhook($this->sessionPayload(['metadata' => ['cart_id' => '99']]));
            $this->fail('expected the original exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('database is down', $e->getMessage());
        }
    }

    public function test_a_queue_failure_while_alerting_is_swallowed_and_releases_the_dedupe_claim(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('queue is down'));

        $this->assertFalse(app(EmailService::class)->sendPaidOrderMissingAlert('cs_d'));
        $this->assertTrue(Cache::add('paid-order-missing:cs_d', true, 60), 'the claim must be released so a later failure can retry');
    }

    public function test_no_alert_is_possible_without_any_admin_address(): void
    {
        Mail::fake();
        config(['mail.contact_email' => '']);
        settings()->set('contact.admin_notification_email', '');

        $this->assertFalse(app(EmailService::class)->sendPaidOrderMissingAlert('cs_e'));
        Mail::assertNothingQueued();
    }
}
