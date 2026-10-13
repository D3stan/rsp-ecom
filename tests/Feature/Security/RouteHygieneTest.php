<?php

namespace Tests\Feature\Security;

use App\Models\Order;
use App\Models\User;
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
