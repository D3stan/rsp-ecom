<?php

namespace Tests\Feature\Checkout;

use App\Models\Cart;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Orders\Support\BuildsStripeOrderFixtures;
use Tests\TestCase;

/**
 * The checkout details page lists what is being bought (order summary): every line carries its product name, the
 * chosen variant, quantity, unit price, line total and image, as plain values, and nothing else of the catalogue
 * models (stock, SKUs, timestamps). Validation errors come back keyed by field so the page can show them in place.
 */
class CheckoutDetailsSummaryTest extends TestCase
{
    use BuildsStripeOrderFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrderFixtures();
    }

    protected function tearDown(): void
    {
        $this->tearDownOrderFixtures();
        parent::tearDown();
    }

    /** Two units of the red variant (30.00) of "Angel Eye". */
    private function cartWithRedVariant(?User $user = null, ?string $guestSessionId = null): Cart
    {
        $product = $this->makeProduct(25.00, 'Angel Eye');
        $red = ProductVariant::create([
            'product_id' => $product->id, 'name' => 'Red', 'sku_suffix' => '-RED', 'price' => 30.00,
            'stock_quantity' => 10, 'images' => [], 'is_default' => false, 'sort_order' => 1, 'status' => 'active',
        ]);

        $cart = Cart::create(['user_id' => $user?->id, 'session_id' => $guestSessionId]);
        $cart->cartItems()->create([
            'product_id' => $product->id, 'product_variant_id' => $red->id, 'size_id' => $this->size->id,
            'quantity' => 2, 'price' => 30.00,
        ]);

        return $cart;
    }

    private function assertSummaryLine(Assert $page): Assert
    {
        return $page
            ->component('Checkout/Details')
            ->has('cartItems', 1)
            ->has('cartItems.0', fn (Assert $line) => $line
                ->where('name', 'Angel Eye')
                ->where('variant_name', 'Red')
                ->where('quantity', 2)
                ->where('price', 30)
                ->where('total', 60)
                ->where('image', fn ($image) => is_string($image) && $image !== '')
                ->has('id')
                ->etc()
                ->missing('stock_quantity')
                ->missing('product')
                ->missing('product_variant'));
    }

    public function test_the_registered_details_page_carries_the_summary_lines(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->cartWithRedVariant($user);

        $this->actingAs($user)->get('/checkout/details')->assertOk()->assertInertia(fn (Assert $page) => $this->assertSummaryLine($page));
    }

    public function test_the_guest_details_page_carries_the_summary_lines(): void
    {
        $sid = Str::random(40);
        $this->cartWithRedVariant(null, $sid);

        $this->withCookie(config('session.cookie'), $sid)->get('/guest/checkout/details')->assertOk()
            ->assertInertia(fn (Assert $page) => $this->assertSummaryLine($page));
    }

    public function test_no_catalogue_internals_reach_the_page(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->cartWithRedVariant($user);

        $props = json_encode($this->actingAs($user)->get('/checkout/details')->viewData('page')['props']['cartItems']);

        foreach (['stock_quantity', 'base_sku', 'sku_suffix', 'shipping_cost', 'created_at'] as $internal) {
            $this->assertStringNotContainsString($internal, $props);
        }
    }

    public function test_server_validation_errors_come_back_keyed_by_field(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->cartWithRedVariant($user);

        $this->actingAs($user)->from('/checkout/details')->post('/checkout/cart', [
            'email' => 'not-an-email',
            'shipping_address' => [
                'first_name' => 'Mario', 'last_name' => 'Rossi', 'address_line_1' => 'Via Roma 10', 'city' => 'Milano',
                'state' => 'MI', 'postal_code' => '', 'country' => 'IT', 'phone' => '+39 333 1234567',
            ],
        ])->assertRedirect('/checkout/details')
            ->assertSessionHasErrors(['email', 'shipping_address.postal_code'])
            ->assertSessionDoesntHaveErrors(['shipping_address.city']);
    }
}
