<?php

namespace Tests\Feature\Security;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Orders\Support\BuildsStripeOrderFixtures;
use Tests\TestCase;

class CheckoutSuccessPrivacyTest extends TestCase
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

    private function stripeReturns(array $session): void
    {
        $this->stripeHttp->responses['/v1/checkout/sessions/'.$session['id']] = $session + ['created' => 1760000000];
    }

    private function withSessionCookie(string $sessionId): static
    {
        return $this->withCookie(config('session.cookie'), $sessionId);
    }

    /** Everything the page would hand to the browser, as one string. */
    private function propsOf($response): string
    {
        return json_encode($response->viewData('page')['props']);
    }

    private function assertNoPersonalData(string $props): void
    {
        foreach (['buyer@example.com', 'Via Roma', '+39 333', 'Mario Rossi', 'Via Stripe', 'guest_phone', 'guest_session_id'] as $needle) {
            $this->assertStringNotContainsString($needle, $props);
        }
    }

    public function test_the_buyers_own_browser_sees_the_full_confirmation(): void
    {
        $sid = Str::random(40);
        $cart = $this->makeCart(guestSessionId: $sid);
        $this->stripeReturns($this->guestSessionPayload($cart));

        $response = $this->withSessionCookie($sid)->get('/checkout/success?session_id=cs_test_123');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Checkout/Success')
            ->where('restricted', false)
            ->where('session.customer_email', 'buyer@example.com')
            ->where('order.total_amount', 55)
        );
        // the session id that proves ownership is never echoed back
        $this->assertStringNotContainsString($sid, $this->propsOf($response));
    }

    public function test_a_different_browser_with_the_session_id_gets_only_the_summary(): void
    {
        $cart = $this->makeCart(guestSessionId: Str::random(40));
        $this->stripeReturns($this->guestSessionPayload($cart));

        $response = $this->withSessionCookie(Str::random(40))->get('/checkout/success?session_id=cs_test_123');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Checkout/Success')
            ->where('restricted', true)
            ->where('session.customer_email', '')
            ->where('order.total_amount', 55)
            ->where('order.payment_status', 'succeeded')
            ->has('order.order_number')
            ->has('order.orderItems', 1)
            ->where('order.orderItems.0.product.name', 'Test Product')
            ->missing('order.guest_email')
            ->missing('order.guest_phone')
            ->missing('order.guest_session_id')
            ->missing('order.shippingAddress')
            ->missing('order.billingAddress')
        );
        $this->assertNoPersonalData($this->propsOf($response));
    }

    public function test_a_lost_cookie_still_gets_the_thank_you_and_the_order_is_still_created_once(): void
    {
        $cart = $this->makeCart(guestSessionId: Str::random(40));
        $this->stripeReturns($this->guestSessionPayload($cart));

        // in-app browser that dropped the session cookie: no cookie at all
        $first = $this->get('/checkout/success?session_id=cs_test_123');
        $second = $this->get('/checkout/success?session_id=cs_test_123');

        $first->assertOk()->assertInertia(fn (Assert $page) => $page->component('Checkout/Success')->where('restricted', true));
        $second->assertOk();
        $this->assertSame(1, Order::count());
        $this->assertSame([Order::sole()->id], array_values(array_unique($this->confirmationCalls)));
        $this->assertNoPersonalData($this->propsOf($first));
    }

    public function test_registered_owner_sees_the_full_confirmation(): void
    {
        $user = User::factory()->create();
        $cart = $this->makeCart($user);
        $this->stripeReturns($this->userSessionPayload($user, $cart));

        $response = $this->actingAs($user)->get('/checkout/success?session_id=cs_test_123');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('restricted', false)
            ->where('isGuest', false)
            ->where('session.customer_email', 'buyer@example.com')
        );
    }

    public function test_another_logged_in_user_gets_only_the_summary(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $cart = $this->makeCart($owner);
        $this->stripeReturns($this->userSessionPayload($owner, $cart));

        $response = $this->actingAs($stranger)->get('/checkout/success?session_id=cs_test_123');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('restricted', true)
            ->where('session.customer_email', '')
            ->missing('order.user_id')
            ->missing('order.guest_email')
        );
        $this->assertNoPersonalData($this->propsOf($response));
    }

    public function test_a_valid_signature_proves_ownership(): void
    {
        $cart = $this->makeCart(guestSessionId: Str::random(40));
        $this->stripeReturns($this->guestSessionPayload($cart));

        $signed = URL::signedRoute('checkout.success', ['session_id' => 'cs_test_123']);

        $this->get($signed)->assertOk()->assertInertia(fn (Assert $page) => $page->where('restricted', false));

        // a signature for another session id does not carry over
        $tampered = str_replace('cs_test_123', 'cs_test_999', $signed);
        $this->stripeReturns($this->guestSessionPayload($cart, ['id' => 'cs_test_999']));
        $this->get($tampered)->assertOk()->assertInertia(fn (Assert $page) => $page->where('restricted', true));
    }

    public function test_the_payment_fallback_page_never_carries_personal_data(): void
    {
        $cart = $this->makeCart(guestSessionId: Str::random(40));
        $this->stripeReturns($this->guestSessionPayload($cart));

        $failing = \Mockery::mock(OrderCreationService::class);
        $failing->shouldReceive('createFromStripeSession')->andThrow(new \RuntimeException('db down'));
        $this->app->instance(OrderCreationService::class, $failing);
        $this->stripeHttp->responses['/line_items'] = ['object' => 'list', 'data' => [], 'has_more' => false];

        $response = $this->get('/checkout/success?session_id=cs_test_123');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('restricted', true)
            ->where('session.customer_email', '')
        );
        $this->assertNoPersonalData($this->propsOf($response));
    }
}
