<?php

namespace Tests\Feature\Checkout;

use App\Models\Order;
use App\Services\OrderCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\Feature\Orders\Support\BuildsStripeOrderFixtures;
use Tests\TestCase;

/**
 * The success page of a paid session whose order does not exist yet: the page gets the pending marker
 * (order.id "pending"), polls with a partial reload of the order, and once the order exists the same reload returns
 * it. After the polling stops the page points to the support address (public setting contact.support_email).
 */
class SuccessPendingTest extends TestCase
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

    private function paidSessionWithoutOrderYet(): void
    {
        $cart = $this->makeCart(guestSessionId: 'pending-guest-1');
        $session = $this->guestSessionPayload($cart);
        $this->stripeHttp->responses['/v1/checkout/sessions/'.$session['id']] = $session + ['created' => 1760000000];

        $failing = Mockery::mock(OrderCreationService::class);
        $failing->shouldReceive('createFromStripeSession')->andThrow(new \RuntimeException('lock wait timeout'));
        $this->app->instance(OrderCreationService::class, $failing);
    }

    public function test_a_paid_session_without_an_order_gets_the_pending_marker_and_the_support_address(): void
    {
        settings()->set('contact.support_email', 'help@shop.test');
        $this->paidSessionWithoutOrderYet();

        $this->get('/checkout/success?session_id=cs_test_123')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Checkout/Success')
            ->where('order.id', 'pending')
            ->where('session.payment_status', 'paid')
            ->where('settings', fn ($settings) => $settings['contact.support_email'] === 'help@shop.test'));

        $this->assertSame(0, Order::count());
    }

    public function test_the_polling_reload_returns_the_order_once_it_exists(): void
    {
        $this->paidSessionWithoutOrderYet();

        $first = $this->get('/checkout/success?session_id=cs_test_123');
        $first->assertInertia(fn (Assert $page) => $page->where('order.id', 'pending'));
        $version = $first->viewData('page')['version'];

        // the order can be created now (the database is back / the webhook got there first)
        $this->app->forgetInstance(OrderCreationService::class);
        $this->app->offsetUnset(OrderCreationService::class);

        // what router.reload({ only: [...] }) sends
        $poll = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $version,
            'X-Inertia-Partial-Component' => 'Checkout/Success',
            'X-Inertia-Partial-Data' => 'order,restricted,isGuest,session',
        ])->get('/checkout/success?session_id=cs_test_123');

        $poll->assertOk();
        $order = Order::sole();
        $this->assertSame($order->order_number, $poll->json('props.order.order_number'));
        $this->assertSame($order->id, $poll->json('props.order.id'));
        $this->assertArrayHasKey('restricted', $poll->json('props'));
        $this->assertArrayNotHasKey('user', $poll->json('props'), 'a partial reload only sends the requested props');
    }
}
