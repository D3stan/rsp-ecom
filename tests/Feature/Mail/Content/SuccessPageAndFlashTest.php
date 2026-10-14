<?php

namespace Tests\Feature\Mail\Content;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Orders\Support\BuildsStripeOrderFixtures;
use Tests\TestCase;

/**
 * The data Checkout/Success.tsx needs for the customer-facing order reference and the "restricted" view,
 * and the flash messages the admin layout turns into toasts.
 */
class SuccessPageAndFlashTest extends TestCase
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

    public function test_the_success_page_carries_the_order_number_and_amounts_the_page_shows_for_a_restricted_viewer(): void
    {
        $cart = $this->makeCart(guestSessionId: Str::random(40));
        $this->stripeHttp->responses['/v1/checkout/sessions/cs_test_123'] = $this->guestSessionPayload($cart) + ['created' => 1760000000];

        // a different browser than the buyer's: restricted view, no email on the page
        $this->withCookie(config('session.cookie'), Str::random(40))
            ->get('/checkout/success?session_id=cs_test_123')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout/Success')
                ->where('restricted', true)
                ->where('session.customer_email', '')
                ->where('order.order_number', Order::sole()->order_number)
                ->where('order.currency', 'EUR')
                ->where('order.total_amount', 55)
                ->where('order.shipping_amount', 5)
                ->has('order.discount_amount')
                ->has('order.subtotal')
            );
    }

    public function test_the_owner_gets_the_same_reference_and_the_email_row_data(): void
    {
        $sid = Str::random(40);
        $cart = $this->makeCart(guestSessionId: $sid);
        $this->stripeHttp->responses['/v1/checkout/sessions/cs_test_123'] = $this->guestSessionPayload($cart) + ['created' => 1760000000];

        $this->withCookie(config('session.cookie'), $sid)
            ->get('/checkout/success?session_id=cs_test_123')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('restricted', false)
                ->where('session.customer_email', 'buyer@example.com')
                ->where('order.order_number', Order::sole()->order_number)
            );
    }

    public function test_session_flashes_are_shared_with_every_page_so_the_admin_layout_can_show_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withSession(['success' => 'Order shipped.', 'warning' => '1 shipping email could not be queued.'])
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('flash.success', 'Order shipped.')
                ->where('flash.warning', '1 shipping email could not be queued.')
                ->where('flash.error', null)
                ->where('flash.id', fn ($id) => is_string($id) && Str::isUuid($id))
            );
    }

    public function test_every_flash_gets_its_own_id_so_the_client_shows_each_one_once(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ids = [];

        foreach (['first', 'second'] as $message) {
            $this->actingAs($admin)->withSession(['success' => $message])->get(route('admin.orders.index'))
                ->assertInertia(function (Assert $page) use (&$ids) {
                    $ids[] = $page->toArray()['props']['flash']['id'];
                });
        }

        $this->assertCount(2, array_unique($ids));
    }

    public function test_flashes_are_empty_without_a_message_and_ignore_non_text_values(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.orders.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('flash.success', null)->where('flash.error', null)->where('flash.warning', null)->where('flash.id', null));

        $this->actingAs($admin)->withSession(['error' => ['not' => 'a string'], 'success' => ''])
            ->get(route('admin.orders.index'))
            ->assertInertia(fn (Assert $page) => $page->where('flash.error', null)->where('flash.success', null)->where('flash.id', null));
    }

    public function test_a_flash_set_by_an_admin_action_reaches_the_next_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = Order::factory()->create(['user_id' => null, 'guest_email' => 'g@example.test', 'status' => 'processing', 'payment_status' => 'succeeded']);

        $this->actingAs($admin)->followingRedirects()
            ->from(route('admin.orders.index'))
            ->put(route('admin.orders.update', $order), ['status' => 'cancelled'])
            ->assertInertia(fn (Assert $page) => $page->whereNot('flash.success', null)->whereNot('flash.id', null));
    }

    public function test_the_success_page_shows_the_real_size_and_variant_of_each_item(): void
    {
        $sid = Str::random(40);
        $cart = $this->makeCart(guestSessionId: $sid);
        $variantName = $cart->cartItems->first()->product->defaultVariant->name;
        $this->stripeHttp->responses['/v1/checkout/sessions/cs_test_123'] = $this->guestSessionPayload($cart) + ['created' => 1760000000];

        $this->withCookie(config('session.cookie'), $sid)
            ->get('/checkout/success?session_id=cs_test_123')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.orderItems.0.size', ['id' => $this->size->id, 'name' => $this->size->name])
                ->where('order.orderItems.0.variant.name', $variantName)
            );
    }

    public function test_an_item_without_size_or_variant_has_null_for_both(): void
    {
        $sid = Str::random(40);
        $cart = $this->makeCart(guestSessionId: $sid);
        $this->stripeHttp->responses['/v1/checkout/sessions/cs_test_123'] = $this->guestSessionPayload($cart) + ['created' => 1760000000];

        $this->withCookie(config('session.cookie'), $sid)->get('/checkout/success?session_id=cs_test_123')->assertOk();
        Order::sole()->orderItems()->update(['size_id' => null, 'product_variant_id' => null]);

        $this->withCookie(config('session.cookie'), $sid)
            ->get('/checkout/success?session_id=cs_test_123')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.orderItems.0.size', null)
                ->where('order.orderItems.0.variant', null)
            );
    }
}
