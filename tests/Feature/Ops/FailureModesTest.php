<?php

namespace Tests\Feature\Ops;

use App\Enums\OpsEventType;
use App\Models\Cart;
use App\Models\CheckoutSnapshot;
use App\Models\OpsEvent;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Orders\Support\BuildsStripeOrderFixtures;
use Tests\TestCase;

/**
 * What the shop does when a dependency it cannot control is down. (A carrier outage never reaches a customer: shipping
 * is charged from the package sizes, and the carrier drivers' failures are isolated, see the Shipping tests.)
 */
class FailureModesTest extends TestCase
{
    use BuildsStripeOrderFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrderFixtures();
        $this->fakeEmailService();
        config(['cashier.secret' => 'sk_test_fake_for_tests']);

        $this->stripeHttp->responses['/v1/customers'] = ['id' => 'cus_test_1', 'object' => 'customer'];
    }

    protected function tearDown(): void
    {
        $this->tearDownOrderFixtures();
        parent::tearDown();
    }

    public function test_a_stripe_outage_when_paying_sends_the_customer_back_with_a_message_and_leaves_no_trace_of_a_payment(): void
    {
        $this->stripeHttp->responses['/v1/checkout/sessions'] = ['__throw' => 'connection'];

        $user = User::factory()->create(['email_verified_at' => now()]);
        $cart = Cart::create(['user_id' => $user->id]);
        $product = $this->makeProduct(150.00, 'Expensive Product');
        $cart->cartItems()->create([
            'product_id' => $product->id, 'product_variant_id' => $product->defaultVariant->id,
            'size_id' => $this->size->id, 'quantity' => 1, 'price' => 150.00,
        ]);
        Log::spy();

        $this->actingAs($user)->post('/checkout/cart', [
            'email' => 'buyer@example.com',
            'shipping_address' => [
                'first_name' => 'Mario', 'last_name' => 'Rossi', 'address_line_1' => 'Via Roma 10', 'city' => 'Milano',
                'state' => 'MI', 'postal_code' => '20100', 'country' => 'IT', 'phone' => '+39 333 1234567',
            ],
            'order_notes' => '',
            'coupon_code' => '',
        ])->assertRedirect()->assertSessionHas('error');

        // Logged as an error (which reaches Sentry), and nothing half-built is left behind
        Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, 'Cart checkout failed'))->once();
        $this->assertSame(0, Order::count());
        $this->assertSame(0, CheckoutSnapshot::count());
        $this->assertSame(0, OpsEvent::where('type', OpsEventType::PaymentStarted->value)->count());
    }
}
