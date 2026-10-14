<?php

namespace Tests\Feature\Security;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\Feature\Orders\Support\BuildsStripeOrderFixtures;
use Laravel\Cashier\Events\WebhookReceived;
use Tests\TestCase;

class StripeWebhookSecurityTest extends TestCase
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

    private function forgedPaidEvent(): string
    {
        $cart = $this->makeCart(guestSessionId: Str::random(40));

        return json_encode([
            'id' => 'evt_forged',
            'type' => 'checkout.session.completed',
            'data' => ['object' => $this->guestSessionPayload($cart)],
        ]);
    }

    private function post_webhook(string $body, array $headers = [])
    {
        return $this->call('POST', '/stripe/webhook', [], [], [], array_merge(
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            $headers
        ), $body);
    }

    private function signature(string $body, string $secret): string
    {
        $timestamp = time();

        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public function test_production_without_a_webhook_secret_refuses_every_event(): void
    {
        $this->app['env'] = 'production';
        config(['cashier.webhook.secret' => null]);
        Log::spy();

        $response = $this->post_webhook($this->forgedPaidEvent());

        $response->assertStatus(403);
        $this->assertSame(0, Order::count());
        Log::shouldHaveReceived('critical')->once();
    }

    public function test_production_with_a_secret_rejects_unsigned_and_wrongly_signed_events(): void
    {
        $this->app['env'] = 'production';
        config(['cashier.webhook.secret' => 'whsec_test_secret']);
        $body = $this->forgedPaidEvent();

        $this->post_webhook($body)->assertStatus(403);
        $this->post_webhook($body, ['HTTP_STRIPE_SIGNATURE' => $this->signature($body, 'whsec_someone_else')])->assertStatus(403);

        $this->assertSame(0, Order::count());
    }

    public function test_production_with_a_secret_accepts_a_correctly_signed_event(): void
    {
        $this->app['env'] = 'production';
        config(['cashier.webhook.secret' => 'whsec_test_secret']);
        $body = $this->forgedPaidEvent();

        $this->post_webhook($body, ['HTTP_STRIPE_SIGNATURE' => $this->signature($body, 'whsec_test_secret')])->assertSuccessful();

        $this->assertSame(1, Order::count());
    }

    public function test_the_app_declared_webhook_route_verifies_the_signature_in_every_environment(): void
    {
        config(['cashier.webhook.secret' => 'whsec_test_secret']);
        Event::fake([WebhookReceived::class]);
        $body = $this->forgedPaidEvent();

        $this->post_webhook($body)->assertStatus(403);
        $this->post_webhook($body, ['HTTP_STRIPE_SIGNATURE' => $this->signature($body, 'whsec_someone_else')])->assertStatus(403);

        Event::assertNotDispatched(WebhookReceived::class);
        $this->assertSame(0, Order::count());
    }

    public function test_a_signed_event_still_dispatches_the_cashier_webhook_received_event(): void
    {
        config(['cashier.webhook.secret' => 'whsec_test_secret']);
        Event::fake([WebhookReceived::class]);
        $body = $this->forgedPaidEvent();

        $this->post_webhook($body, ['HTTP_STRIPE_SIGNATURE' => $this->signature($body, 'whsec_test_secret')])->assertSuccessful();

        Event::assertDispatched(WebhookReceived::class, fn (WebhookReceived $e) => $e->payload['id'] === 'evt_forged');
    }

    public function test_the_webhook_url_is_the_one_configured_in_the_stripe_dashboard(): void
    {
        $this->assertSame(url('/stripe/webhook'), route('cashier.webhook'));
    }
}
