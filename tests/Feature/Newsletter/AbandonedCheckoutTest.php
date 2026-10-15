<?php

namespace Tests\Feature\Newsletter;

use App\Jobs\Newsletter\SendAbandonedCheckoutReminder;
use App\Listeners\StripeWebhookListener;
use App\Mail\AbandonedCheckoutReminder;
use App\Mail\NewsletterConfirm;
use App\Models\Cart;
use App\Models\CheckoutSnapshot;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Newsletter\MarketingConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Cashier\Events\WebhookReceived;
use Tests\Feature\Orders\Support\BuildsStripeOrderFixtures;
use Tests\TestCase;

/**
 * Abandoned-checkout reminder: consent captured at checkout, one reminder after the Stripe session expired,
 * and the signed recovery link that rebuilds the cart.
 */
class AbandonedCheckoutTest extends TestCase
{
    use BuildsStripeOrderFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrderFixtures();
        $this->fakeEmailService();
        config(['cashier.secret' => 'sk_test_fake_for_tests']);
        Mail::fake();

        settings()->set('newsletter.enabled', true);
        settings()->set('newsletter.abandoned_checkout_enabled', true);
        settings()->set('newsletter.abandoned_checkout_delay_minutes', 90);

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

    private function form(array $overrides = []): array
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

    /** Params of the last Stripe "create checkout session" request. */
    private function stripeSessionParams(): array
    {
        foreach (array_reverse($this->stripeHttp->requests) as [$method, $url, $params]) {
            if ($method === 'post' && str_ends_with($url, '/v1/checkout/sessions')) {
                return $params;
            }
        }

        $this->fail('No Stripe checkout session was created');
    }

    private function guestCheckout(array $form = [], ?string $sid = null): CheckoutSnapshot
    {
        $sid ??= Str::random(40);
        $this->makeCart(guestSessionId: $sid);
        $this->withCookie(config('session.cookie'), $sid)->post('/guest/checkout/cart', $this->form($form))->assertRedirect();

        return CheckoutSnapshot::sole();
    }

    private function expire(string $sessionId = 'cs_test_created', string $eventId = 'evt_expired'): void
    {
        app(StripeWebhookListener::class)->handle(new WebhookReceived([
            'id' => $eventId,
            'type' => 'checkout.session.expired',
            'data' => ['object' => $this->sessionPayload([
                'id' => $sessionId,
                'payment_status' => 'unpaid',
                'metadata' => ['type' => 'guest_cart_checkout', 'guest_session_id' => 'x', 'guest_email' => 'buyer@example.com'],
            ])],
        ]));
    }

    /** An expired checkout with consent, as the reminder job sees it. */
    private function consentedSnapshot(array $attributes = [], ?Product $product = null, int $quantity = 2): CheckoutSnapshot
    {
        $product ??= $this->makeProduct(25.00, 'Angel Eye Kit');
        app(MarketingConsent::class)->subscribe($attributes['email'] ?? 'buyer@example.com', 'checkout', 'it');

        return CheckoutSnapshot::forceCreate(array_merge([
            'stripe_session_id' => 'cs_test_'.Str::random(8),
            'guest_session_id' => 'old-session',
            'lines' => [[
                'product_id' => $product->id,
                'product_variant_id' => $product->defaultVariant->id,
                'size_id' => $this->size->id,
                'quantity' => $quantity,
                'price' => 25.00,
                'product_name' => $product->name,
                'variant_name' => $product->defaultVariant->name,
                'sku' => 'SKU-1',
                'price_changed' => false,
            ]],
            'subtotal' => 50, 'shipping' => 5, 'discount' => 0, 'tax' => 9.92, 'total' => 55, 'total_cents' => 5500,
            'currency' => 'EUR', 'cart_hash' => 'hash',
            'email' => 'buyer@example.com',
            'locale' => 'it',
            'marketing_consent' => true,
            'consent_ip' => '203.0.113.7',
            'consumed_at' => now(),
        ], $attributes));
    }

    private function runReminder(CheckoutSnapshot $snapshot): void
    {
        app()->call([new SendAbandonedCheckoutReminder($snapshot->id), 'handle']);
    }

    private function recoverUrl(CheckoutSnapshot $snapshot, int $days = 7): string
    {
        return URL::temporarySignedRoute('checkout.recover', now()->addDays($days), ['snapshot' => $snapshot->id]);
    }

    // ------------------------------------------------------------ Stripe session expiry

    public function test_both_checkouts_set_the_configured_session_expiry_on_stripe(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00');

        $this->guestCheckout();
        $this->assertSame(Carbon::parse('2026-10-15 11:00:00')->timestamp, (int) $this->stripeSessionParams()['expires_at']);

        CheckoutSnapshot::query()->delete();
        settings()->set('orders.checkout_expiry_minutes', 180);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->makeCart($user);
        $this->actingAs($user)->post('/checkout/cart', $this->form())->assertRedirect();

        $this->assertSame(Carbon::parse('2026-10-15 13:00:00')->timestamp, (int) $this->stripeSessionParams()['expires_at']);
    }

    public function test_the_expiry_stays_inside_stripes_30_minutes_to_24_hours_window(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00');

        settings()->set('orders.checkout_expiry_minutes', 30);
        $this->guestCheckout();
        $expires = (int) $this->stripeSessionParams()['expires_at'];
        $this->assertGreaterThan(Carbon::parse('2026-10-15 10:30:00')->timestamp, $expires);

        CheckoutSnapshot::query()->delete();
        settings()->set('orders.checkout_expiry_minutes', 1440);
        $this->guestCheckout(sid: Str::random(40));
        $expires = (int) $this->stripeSessionParams()['expires_at'];
        $this->assertLessThan(Carbon::parse('2026-10-16 10:00:00')->timestamp, $expires);
    }

    // ------------------------------------------------------------ consent at checkout

    public function test_checkout_with_consent_stores_it_on_the_snapshot_and_starts_the_newsletter_double_opt_in(): void
    {
        $snapshot = $this->guestCheckout(['marketing_consent' => true]);

        $this->assertTrue($snapshot->marketing_consent);
        $this->assertSame('buyer@example.com', $snapshot->email);
        $this->assertNotNull($snapshot->locale);
        $this->assertSame('127.0.0.1', $snapshot->consent_ip);

        $subscriber = NewsletterSubscriber::sole();
        $this->assertSame('checkout', $subscriber->source);
        $this->assertSame(NewsletterSubscriber::STATUS_PENDING, $subscriber->status);
        Mail::assertQueued(NewsletterConfirm::class, fn ($mail) => $mail->hasTo('buyer@example.com'));
    }

    public function test_checkout_without_consent_stores_no_consent_and_subscribes_nobody(): void
    {
        $snapshot = $this->guestCheckout();

        $this->assertFalse($snapshot->marketing_consent);
        $this->assertSame(0, NewsletterSubscriber::count());
        Mail::assertNothingQueued();
    }

    public function test_consent_is_ignored_while_the_newsletter_is_switched_off(): void
    {
        settings()->set('newsletter.enabled', false);

        $snapshot = $this->guestCheckout(['marketing_consent' => true]);

        $this->assertFalse($snapshot->marketing_consent);
        $this->assertSame(0, NewsletterSubscriber::count());
    }

    // ------------------------------------------------------------ expiry webhook

    public function test_an_expired_session_with_consent_queues_one_reminder_with_the_delay(): void
    {
        $snapshot = $this->guestCheckout(['marketing_consent' => true]);
        Queue::fake([SendAbandonedCheckoutReminder::class]);
        Carbon::setTestNow(now());

        $this->expire();

        Queue::assertPushed(SendAbandonedCheckoutReminder::class, function (SendAbandonedCheckoutReminder $job) use ($snapshot) {
            return $job->snapshotId === $snapshot->id
                && $job->delay instanceof \DateTimeInterface
                && Carbon::instance($job->delay)->equalTo(now()->addMinutes(90));
        });
    }

    public function test_an_expired_session_without_consent_queues_nothing(): void
    {
        $this->guestCheckout();
        Queue::fake([SendAbandonedCheckoutReminder::class]);

        $this->expire();

        Queue::assertNotPushed(SendAbandonedCheckoutReminder::class);
    }

    public function test_nothing_is_queued_or_sent_while_the_reminders_are_switched_off(): void
    {
        $this->guestCheckout(['marketing_consent' => true]);
        settings()->set('newsletter.abandoned_checkout_enabled', false);
        Queue::fake([SendAbandonedCheckoutReminder::class]);

        $this->expire();
        Queue::assertNotPushed(SendAbandonedCheckoutReminder::class);

        // Switched off between queueing and sending
        $snapshot = $this->consentedSnapshot(['email' => 'other@example.com']);
        $this->runReminder($snapshot);
        Mail::assertNotSent(AbandonedCheckoutReminder::class);
    }

    public function test_a_double_webhook_delivery_sends_one_email(): void
    {
        $this->guestCheckout(['marketing_consent' => true]);

        $this->expire('cs_test_created', 'evt_1');
        $this->expire('cs_test_created', 'evt_2');

        Mail::assertSent(AbandonedCheckoutReminder::class, 1);
        $this->assertNotNull(CheckoutSnapshot::sole()->reminder_sent_at);
    }

    // ------------------------------------------------------------ the reminder job

    public function test_the_reminder_lists_the_lines_links_to_the_signed_recovery_and_can_unsubscribe(): void
    {
        $snapshot = $this->consentedSnapshot();

        $this->runReminder($snapshot);

        $mail = Mail::sent(AbandonedCheckoutReminder::class)->sole();
        $this->assertTrue($mail->hasTo('buyer@example.com'));
        $this->assertSame('it', $mail->locale);

        $subscriber = NewsletterSubscriber::sole();
        $headers = $mail->headers()->text;
        $this->assertSame('<'.$subscriber->unsubscribeUrl().'>', $headers['List-Unsubscribe']);
        $this->assertSame('List-Unsubscribe=One-Click', $headers['List-Unsubscribe-Post']);

        $html = $mail->render();
        $this->assertStringContainsString('Angel Eye Kit', $html);
        $this->assertStringContainsString(e($subscriber->unsubscribeUrl()), $html);
        $this->assertStringContainsString(e($mail->recoverUrl), $html);
        $this->assertStringContainsString('/checkout/recover/'.$snapshot->id, $mail->recoverUrl);
        $this->assertStringContainsString('signature=', $mail->recoverUrl);

        // Never a second one
        $this->runReminder($snapshot->fresh());
        Mail::assertSent(AbandonedCheckoutReminder::class, 1);
    }

    public function test_no_reminder_after_a_paid_order_for_the_same_email(): void
    {
        $snapshot = $this->consentedSnapshot();
        Order::factory()->create([
            'user_id' => null,
            'guest_email' => 'buyer@example.com',
            'payment_status' => 'succeeded',
            'created_at' => now()->addMinute(),
        ]);

        $this->runReminder($snapshot);

        Mail::assertNotSent(AbandonedCheckoutReminder::class);
        $this->assertNull($snapshot->fresh()->reminder_sent_at);
    }

    public function test_no_reminder_after_a_paid_order_of_the_same_user(): void
    {
        $user = User::factory()->create(['email' => 'account@example.com']);
        $snapshot = $this->consentedSnapshot(['user_id' => $user->id, 'guest_session_id' => null]);
        Order::factory()->create(['user_id' => $user->id, 'payment_status' => 'succeeded', 'created_at' => now()->addMinute()]);

        $this->runReminder($snapshot);

        Mail::assertNotSent(AbandonedCheckoutReminder::class);
    }

    public function test_no_reminder_to_an_unsubscribed_or_erased_address(): void
    {
        $snapshot = $this->consentedSnapshot();
        $consent = app(MarketingConsent::class);
        $consent->unsubscribe(NewsletterSubscriber::sole(), 'link');

        $this->runReminder($snapshot);
        Mail::assertNotSent(AbandonedCheckoutReminder::class);

        $other = $this->consentedSnapshot(['email' => 'erased@example.com']);
        $consent->erase(NewsletterSubscriber::where('email', 'erased@example.com')->sole());

        $this->runReminder($other);
        Mail::assertNotSent(AbandonedCheckoutReminder::class);
    }

    public function test_no_reminder_for_a_snapshot_without_lines(): void
    {
        $snapshot = $this->consentedSnapshot(['lines' => []]);

        $this->runReminder($snapshot);

        Mail::assertNotSent(AbandonedCheckoutReminder::class);
    }

    // ------------------------------------------------------------ recovery link

    public function test_the_recovery_link_rebuilds_the_cart_of_a_guest_with_todays_prices(): void
    {
        $product = $this->makeProduct(25.00, 'Angel Eye Kit');
        $snapshot = $this->consentedSnapshot([], $product);
        $product->defaultVariant->update(['price' => 29.00]);

        $sid = Str::random(40);
        $this->withCookie(config('session.cookie'), $sid)->get($this->recoverUrl($snapshot))
            ->assertRedirect(route('cart'));

        $cart = Cart::with('cartItems')->whereNotNull('session_id')->sole();
        $this->assertCount(1, $cart->cartItems);
        $this->assertSame(2, (int) $cart->cartItems[0]->quantity);
        $this->assertEquals(29.00, (float) $cart->cartItems[0]->price);
        $this->assertNotNull($snapshot->fresh()->recovered_at);
    }

    public function test_the_recovery_link_skips_unavailable_lines_and_caps_quantities(): void
    {
        $gone = $this->makeProduct(25.00, 'Sold Out Lamp');
        $gone->defaultVariant->update(['stock_quantity' => 0]);
        $scarce = $this->makeProduct(25.00, 'Last Units');
        $scarce->defaultVariant->update(['stock_quantity' => 1]);

        $snapshot = $this->consentedSnapshot([], $gone);
        $lines = $snapshot->lines;
        $lines[] = array_merge($lines[0], [
            'product_id' => $scarce->id, 'product_variant_id' => $scarce->defaultVariant->id, 'product_name' => 'Last Units', 'quantity' => 3,
        ]);
        $snapshot->forceFill(['lines' => $lines])->save();

        $this->get($this->recoverUrl($snapshot))
            ->assertRedirect(route('cart'))
            ->assertSessionHas('warning');

        $items = Cart::with('cartItems')->sole()->cartItems;
        $this->assertCount(1, $items);
        $this->assertSame($scarce->id, $items[0]->product_id);
        $this->assertSame(1, (int) $items[0]->quantity);
    }

    public function test_the_recovery_link_rebuilds_the_cart_of_the_logged_in_owner(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $snapshot = $this->consentedSnapshot(['user_id' => $user->id, 'guest_session_id' => null]);

        $this->actingAs($user)->get($this->recoverUrl($snapshot))->assertRedirect(route('cart'));

        $cart = Cart::where('user_id', $user->id)->with('cartItems')->sole();
        $this->assertCount(1, $cart->cartItems);
    }

    public function test_the_recovery_link_is_refused_unsigned_expired_or_for_another_user(): void
    {
        $owner = User::factory()->create();
        $snapshot = $this->consentedSnapshot(['user_id' => $owner->id, 'guest_session_id' => null]);
        $url = $this->recoverUrl($snapshot);

        $this->get('/checkout/recover/'.$snapshot->id)->assertForbidden();

        $intruder = User::factory()->create();
        $this->actingAs($intruder)->get($url)->assertForbidden();

        $this->travel(8)->days();
        $this->actingAs($owner)->get($url)->assertForbidden();

        $this->assertSame(0, Cart::count());
        $this->assertNull($snapshot->fresh()->recovered_at);
    }

    // ------------------------------------------------------------ admin stats

    public function test_the_newsletter_admin_page_shows_the_abandoned_checkout_stats_of_the_last_30_days(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $converted = $this->consentedSnapshot(['email' => 'paid@example.com', 'reminder_sent_at' => now()->subDays(3), 'recovered_at' => now()->subDays(3)]);
        Order::factory()->create(['user_id' => null, 'guest_email' => 'paid@example.com', 'payment_status' => 'succeeded', 'created_at' => now()->subDays(2)]);
        $this->consentedSnapshot(['email' => 'recovered@example.com', 'reminder_sent_at' => now()->subDays(5), 'recovered_at' => now()->subDays(5)]);
        $this->consentedSnapshot(['email' => 'ignored@example.com', 'reminder_sent_at' => now()->subDays(10)]);
        $this->consentedSnapshot(['email' => 'old@example.com', 'reminder_sent_at' => now()->subDays(40), 'recovered_at' => now()->subDays(40)]);

        $this->actingAs($admin)->get(route('admin.newsletter.subscribers'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('abandoned.enabled', true)
                ->where('abandoned.sent', 3)
                ->where('abandoned.recovered', 2)
                ->where('abandoned.converted', 1));
    }
}
