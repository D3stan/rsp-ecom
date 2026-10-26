<?php

namespace Tests\Feature\Security;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RouteHygieneTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_legacy_guest_session_endpoint_that_trusted_a_client_supplied_cart_id_is_gone(): void
    {
        $this->assertFalse(Route::has('checkout.guest.session'));
        $this->post('/guest/checkout/session', ['cart_session_id' => 'anything'])->assertStatus(405);
    }

    public function test_the_legacy_session_endpoint_and_the_session_details_page_are_gone(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->assertFalse(Route::has('checkout.session'));
        $this->assertFalse(Route::has('checkout.show'));
        $this->assertNull($this->routeFor('POST', 'checkout/session'));
        $this->assertNull($this->routeFor('GET', 'checkout/show'));

        $this->actingAs($user)->postJson('/checkout/session', [])->assertStatus(405);
        $this->actingAs($user)->get('/checkout/show?session_id=cs_test_1')
            ->assertInertia(fn (Assert $page) => $page->component('Errors/NotFound'));
    }

    public function test_the_cashier_demo_checkout_routes_nothing_uses_are_no_longer_routed(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        foreach (['checkout.product', 'checkout.product.promo', 'checkout.product.tax', 'checkout.charge', 'guest.checkout', 'guest.checkout.promo'] as $name) {
            $this->assertFalse(Route::has($name), $name);
        }

        $this->assertNull($this->routeFor('GET', 'checkout/product/price_123'));
        $this->assertNull($this->routeFor('GET', 'checkout/product/price_123/promo'));
        $this->assertNull($this->routeFor('GET', 'checkout/product/price_123/tax'));
        $this->assertNull($this->routeFor('GET', 'checkout/charge/1000/anything'));
        $this->assertNull($this->routeFor('GET', 'guest/checkout/price_123'));
        $this->assertNull($this->routeFor('GET', 'guest/checkout/price_123/promo/CODE'));

        $this->actingAs($user)->get('/checkout/product/price_123')->assertInertia(fn (Assert $page) => $page->component('Errors/NotFound'));
        $this->actingAs($user)->get('/checkout/charge/1000/anything')->assertInertia(fn (Assert $page) => $page->component('Errors/NotFound'));
        $this->get('/guest/checkout/price_123')->assertInertia(fn (Assert $page) => $page->component('Errors/NotFound'));

        // the checkout the shop really uses stays
        foreach (['checkout.details', 'checkout.cart', 'guest.checkout.details', 'guest.cart.checkout', 'guest.checkout.redirect', 'checkout.success', 'checkout.cancel'] as $name) {
            $this->assertTrue(Route::has($name), $name);
        }
    }

    public function test_the_cashier_public_payment_page_is_not_registered(): void
    {
        $this->assertFalse(Route::has('cashier.payment'));
        $this->assertNull($this->routeFor('GET', 'stripe/payment/x'));
        $this->get('/stripe/payment/x')->assertInertia(fn (Assert $page) => $page->component('Errors/NotFound'));
    }

    public function test_the_stripe_webhook_is_declared_by_the_app_outside_the_web_group(): void
    {
        $route = Route::getRoutes()->getByName('cashier.webhook');

        $this->assertNotNull($route);
        $this->assertSame(['POST'], $route->methods());
        $this->assertSame('stripe/webhook', $route->uri());
        $this->assertSame('Laravel\\Cashier\\Http\\Controllers\\WebhookController@handleWebhook', $route->getActionName());
        $this->assertContains('web', $route->excludedMiddleware());
        $this->assertNotContains(PreventRequestForgery::class, app('router')->resolveMiddleware($route->gatherMiddleware(), $route->excludedMiddleware()));
    }

    private function routeFor(string $method, string $uri): ?\Illuminate\Routing\Route
    {
        foreach (Route::getRoutes()->get($method) as $route) {
            if ($route->uri() === $uri || preg_match('#^'.preg_replace('/\{[^}]+\}/', '[^/]+', $route->uri()).'$#', $uri)) {
                return $route;
            }
        }

        return null;
    }

    public function test_dashboard_completed_counter_counts_delivered_orders_only(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Order::factory()->count(2)->create(['user_id' => $user->id, 'status' => 'delivered']);
        Order::factory()->create(['user_id' => $user->id, 'status' => 'shipped']);
        Order::factory()->create(['user_id' => $user->id, 'status' => 'cancelled']);
        Order::factory()->create(['user_id' => $user->id, 'status' => 'pending']);

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('orderStats.total', 5)
            ->where('orderStats.completed', 2)
            ->where('orderStats.pending', 1)
        );
    }
}
