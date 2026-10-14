<?php

namespace Tests\Feature\Orders;

use App\Models\Cart;
use App\Models\CheckoutSnapshot;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\Feature\Orders\Support\BuildsStripeOrderFixtures;
use Tests\TestCase;

/**
 * cartCheckout / guestCartCheckout against canned Stripe responses: what is sent to Stripe, the checkout snapshot that
 * is written before the call, and the order that is later built from it (also when the cart changed meanwhile).
 */
class CheckoutSnapshotTest extends TestCase
{
    use BuildsStripeOrderFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrderFixtures();
        $this->fakeEmailService();
        config(['cashier.secret' => 'sk_test_fake_for_tests']);

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

    /** @return array{0: array, 1: string} the params of the Stripe request that created the session, and its raw JSON */
    private function stripeSessionRequest(): array
    {
        foreach (array_reverse($this->stripeHttp->requests) as [$method, $url, $params]) {
            if ($method === 'post' && str_ends_with($url, '/v1/checkout/sessions')) {
                return [$params, json_encode($params)];
            }
        }

        $this->fail('No Stripe checkout session was created');
    }

    private function sessionCookie(string $sessionId): static
    {
        return $this->withCookie(config('session.cookie'), $sessionId);
    }

    /** Guest checkout of the standard cart (2 x 25.00 + 5.00 shipping); returns [cart, snapshot, metadata]. */
    private function guestCheckout(string $sid): array
    {
        $cart = $this->makeCart(guestSessionId: $sid);
        $this->sessionCookie($sid)->post('/guest/checkout/cart', $this->form())->assertRedirect('https://checkout.stripe.test/c/pay/cs_test_created');

        [$params] = $this->stripeSessionRequest();

        return [$cart, CheckoutSnapshot::sole(), $params['metadata']];
    }

    /** Stripe's paid session for a checkout created with this metadata. */
    private function paidSession(array $metadata, array $overrides = []): array
    {
        return $this->sessionPayload(array_replace_recursive([
            'id' => 'cs_test_created',
            'amount_subtotal' => 5500,
            'amount_total' => 5500,
            'metadata' => $metadata,
        ], $overrides));
    }

    // ------------------------------------------------------------ what is sent to Stripe

    public function test_guest_checkout_sends_the_charge_and_metadata_to_stripe_and_writes_the_snapshot_first(): void
    {
        $sid = Str::random(40);
        [$cart, $snapshot, $metadata] = $this->guestCheckout($sid);
        [$params, $json] = $this->stripeSessionRequest();

        $this->assertSame(5500, (int) $params['line_items'][0]['price_data']['unit_amount']);
        $this->assertSame($snapshot->id, $metadata['snapshot_id']);
        $this->assertSame(OrderCreationService::SESSION_SOURCE, $metadata['source']);
        $this->assertSame($cart->contentHash(), $metadata['cart_hash']);
        $this->assertSame($sid, $metadata['guest_session_id']);
        foreach (['description', 'shipping_address', 'customer_phone', 'order_notes', 'coupon_code', 'locale', 'type'] as $key) {
            $this->assertArrayHasKey($key, $metadata);
        }
        $this->assertStringNotContainsString('sk_test', $json);
        $this->assertStringNotContainsString('whsec', $json);

        $this->assertSame('cs_test_created', $snapshot->stripe_session_id);
        $this->assertNull($snapshot->consumed_at);
        $this->assertNull($snapshot->user_id);
        $this->assertSame($sid, $snapshot->guest_session_id);
        $this->assertSame(5500, $snapshot->total_cents);
        $this->assertEquals(50.00, $snapshot->subtotal);
        $this->assertEquals(5.00, $snapshot->shipping);
        $this->assertSame($cart->contentHash(), $snapshot->cart_hash);
        $this->assertCount(1, $snapshot->lines);
        $this->assertSame(2, $snapshot->lines[0]['quantity']);
        $this->assertEquals(25.00, $snapshot->lines[0]['price']);
        $this->assertSame('Test Product', $snapshot->lines[0]['product_name']);
        $this->assertSame($this->size->id, $snapshot->lines[0]['size_id']);
        $this->assertFalse($snapshot->lines[0]['price_changed']);
    }

    public function test_registered_checkout_sends_the_snapshot_id_and_binds_the_snapshot_to_the_session(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->makeCart($user);

        $this->actingAs($user)->post('/checkout/cart', $this->form())->assertRedirect('https://checkout.stripe.test/c/pay/cs_test_created');

        [$params, $json] = $this->stripeSessionRequest();
        $snapshot = CheckoutSnapshot::sole();

        $this->assertSame(5500, (int) $params['line_items'][0]['price_data']['unit_amount']);
        $this->assertSame($snapshot->id, $params['metadata']['snapshot_id']);
        $this->assertSame((string) $user->id, (string) $params['metadata']['user_id']);
        $this->assertSame($user->id, $snapshot->user_id);
        $this->assertSame('cs_test_created', $snapshot->stripe_session_id);
        $this->assertStringNotContainsString('sk_test', $json);
    }

    public function test_long_description_and_address_are_truncated_to_the_500_characters_stripe_accepts(): void
    {
        $sid = Str::random(40);
        $cart = $this->makeCart(guestSessionId: $sid);
        $cart->cartItems()->first()->product->update(['name' => str_repeat('Ö', 600)]);

        $this->sessionCookie($sid)->post('/guest/checkout/cart', $this->form([
            'shipping_address' => ['first_name' => str_repeat('A', 250), 'address_line_1' => str_repeat('V', 250), 'city' => str_repeat('C', 250)],
        ]))->assertRedirect();

        [$params] = $this->stripeSessionRequest();

        foreach ($params['metadata'] as $key => $value) {
            $this->assertLessThanOrEqual(500, mb_strlen((string) $value), "metadata.{$key}");
        }
        $this->assertSame(500, mb_strlen($params['metadata']['description']));
        $this->assertSame(500, mb_strlen($params['metadata']['shipping_address']));
        $this->assertLessThanOrEqual(500, mb_strlen($params['line_items'][0]['price_data']['product_data']['description']));
    }

    public function test_the_snapshot_is_deleted_when_stripe_fails(): void
    {
        unset($this->stripeHttp->responses['/v1/checkout/sessions']);
        $sid = Str::random(40);
        $this->makeCart(guestSessionId: $sid);

        $this->sessionCookie($sid)->post('/guest/checkout/cart', $this->form())->assertSessionHas('error');

        $this->assertSame(0, CheckoutSnapshot::count());
    }

    public function test_a_cart_line_without_enough_stock_stops_the_checkout_before_stripe(): void
    {
        $sid = Str::random(40);
        $cart = $this->makeCart(guestSessionId: $sid, quantity: 2);
        $cart->cartItems()->first()->product->defaultVariant->update(['stock_quantity' => 1]);

        $this->sessionCookie($sid)->post('/guest/checkout/cart', $this->form())
            ->assertSessionHasErrors('cart');

        $this->assertSame(0, CheckoutSnapshot::count());
        $this->assertSame([], $this->stripeHttp->requests);
    }

    // ------------------------------------------------------------ the order is built from the snapshot

    public function test_an_order_is_built_from_the_snapshot_even_if_the_cart_was_edited_while_paying(): void
    {
        [$cart, $snapshot, $metadata] = $this->guestCheckout(Str::random(40));

        // The customer plays with the cart in another tab, and the product is repriced
        $item = $cart->cartItems()->first();
        $item->update(['quantity' => 5, 'price' => 99.00]);

        $order = app(OrderCreationService::class)->createFromStripeSession($this->paidSession($metadata));

        $line = $order->orderItems->sole();
        $this->assertSame(2, $line->quantity);
        $this->assertEquals(25.00, $line->price);
        $this->assertEquals(50.00, $line->total);
        $this->assertSame($this->size->id, $line->size_id);
        $this->assertEquals(50.00, $order->subtotal);
        $this->assertEquals(5.00, $order->shipping_amount);
        $this->assertEquals(55.00, $order->total_amount);
        $this->assertEquals(9.92, $order->tax_amount);
        $this->assertNotNull(Cart::find($cart->id), 'an edited cart is left untouched');
        $this->assertSame(5, $item->fresh()->quantity);

        $snapshot->refresh();
        $this->assertNotNull($snapshot->consumed_at);
        $this->assertSame('cs_test_created', $snapshot->stripe_session_id);
    }

    public function test_an_unchanged_cart_is_cleared_and_replays_are_idempotent(): void
    {
        [$cart, $snapshot, $metadata] = $this->guestCheckout(Str::random(40));
        $session = $this->paidSession($metadata);

        $first = app(OrderCreationService::class)->createFromStripeSession($session);
        $consumedAt = $snapshot->fresh()->consumed_at;
        $second = app(OrderCreationService::class)->createFromStripeSession($session);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Order::count());
        $this->assertSame(1, $first->orderItems()->count());
        $this->assertNull(Cart::find($cart->id));
        $this->assertEquals($consumedAt, $snapshot->fresh()->consumed_at);
    }

    public function test_a_snapshot_not_yet_bound_to_a_session_is_bound_on_consumption(): void
    {
        [, $snapshot, $metadata] = $this->guestCheckout(Str::random(40));
        $snapshot->update(['stripe_session_id' => null]); // e.g. the bind after the Stripe call failed

        app(OrderCreationService::class)->createFromStripeSession($this->paidSession($metadata));

        $this->assertSame('cs_test_created', $snapshot->fresh()->stripe_session_id);
        $this->assertNotNull($snapshot->fresh()->consumed_at);
    }

    public function test_a_snapshot_of_another_session_is_never_used(): void
    {
        [$cart, $snapshot, $metadata] = $this->guestCheckout(Str::random(40));
        $snapshot->update(['stripe_session_id' => 'cs_somebody_else']);
        $cart->cartItems()->first()->update(['quantity' => 3]);
        // the cart no longer matches its hash either: items come from Stripe, never from a foreign snapshot
        $this->stripeHttp->responses['/v1/checkout/sessions/cs_test_created/line_items'] = ['object' => 'list', 'data' => []];

        $order = app(OrderCreationService::class)->createFromStripeSession($this->paidSession($metadata));

        $this->assertNull($snapshot->fresh()->consumed_at);
        $this->assertSame(1, $order->orderItems()->count());
        $this->assertNull($order->orderItems->first()->product_id);
    }

    public function test_sessions_without_a_snapshot_id_keep_using_the_cart(): void
    {
        $cart = $this->makeCart(guestSessionId: 'legacy-guest');

        $order = app(OrderCreationService::class)->createFromStripeSession($this->guestSessionPayload($cart));

        $this->assertSame('Test Product', $order->orderItems->sole()->product_name);
        $this->assertNull(Cart::find($cart->id));
    }

    public function test_the_snapshot_is_consumed_in_the_same_transaction_as_the_order(): void
    {
        [, $snapshot, $metadata] = $this->guestCheckout(Str::random(40));

        \App\Models\OrderItem::creating(function () {
            throw new \RuntimeException('boom while creating the items');
        });

        try {
            app(OrderCreationService::class)->createFromStripeSession($this->paidSession($metadata));
            $this->fail('expected the failure');
        } catch (\RuntimeException) {
        } finally {
            \App\Models\OrderItem::flushEventListeners();
        }

        $this->assertSame(0, Order::count());
        $this->assertNull($snapshot->fresh()->consumed_at);
    }

    // ------------------------------------------------------------ pruning

    public function test_old_snapshots_are_pruned(): void
    {
        $make = fn (array $attributes) => CheckoutSnapshot::forceCreate($attributes + [
            'lines' => [], 'subtotal' => 1, 'shipping' => 0, 'discount' => 0, 'tax' => 0, 'total' => 1,
            'total_cents' => 100, 'currency' => 'EUR', 'cart_hash' => 'x',
        ]);

        $abandonedOld = $make(['created_at' => now()->subDays(8)]);
        $abandonedFresh = $make(['created_at' => now()->subDays(6)]);
        $consumedRecent = $make(['created_at' => now()->subDays(40), 'consumed_at' => now()->subDays(30)]);
        $consumedOld = $make(['created_at' => now()->subDays(120), 'consumed_at' => now()->subDays(91)]);

        Artisan::call('model:prune', ['--model' => CheckoutSnapshot::class]);

        $this->assertNull(CheckoutSnapshot::find($abandonedOld->id));
        $this->assertNotNull(CheckoutSnapshot::find($abandonedFresh->id));
        $this->assertNotNull(CheckoutSnapshot::find($consumedRecent->id));
        $this->assertNull(CheckoutSnapshot::find($consumedOld->id));
    }

    // ------------------------------------------------------------ the variant price

    private function cartWithSecondVariant(string $sid, float $variantPrice = 40.00): array
    {
        $cart = Cart::create(['session_id' => $sid]);
        $product = $this->makeProduct(25.00, 'Two Variants');
        $large = $product->variants()->create([
            'name' => 'Large', 'sku_suffix' => '-L', 'price' => $variantPrice, 'stock_quantity' => 50,
            'is_default' => false, 'sort_order' => 1, 'status' => 'active', 'images' => [],
        ]);
        $item = $cart->cartItems()->create([
            'product_id' => $product->id,
            'product_variant_id' => $large->id,
            'size_id' => $this->size->id,
            'quantity' => 1,
            'price' => $variantPrice,
        ]);

        return [$cart, $item, $large, $product];
    }

    public function test_a_non_default_variant_is_charged_at_its_own_price_not_the_default_variants(): void
    {
        $sid = Str::random(40);
        [, $item, $large] = $this->cartWithSecondVariant($sid);

        $this->sessionCookie($sid)->post('/guest/checkout/cart', $this->form())->assertRedirect();

        [$params] = $this->stripeSessionRequest();
        $this->assertSame(4500, (int) $params['line_items'][0]['price_data']['unit_amount']); // 40 + 5 shipping, not 25 + 5
        $line = CheckoutSnapshot::sole()->lines[0];
        $this->assertEquals(40.00, $line['price']);
        $this->assertFalse($line['price_changed']);
        $this->assertSame($large->id, $line['product_variant_id']);
        $this->assertSame('Large', $line['variant_name']);
        $this->assertEquals(40.00, $item->fresh()->price);

        $order = app(OrderCreationService::class)->createFromStripeSession($this->paidSession(
            $params['metadata'],
            ['amount_subtotal' => 4500, 'amount_total' => 4500]
        ));
        $this->assertEquals(40.00, $order->orderItems->sole()->price);
    }

    public function test_a_catalogue_price_change_is_charged_for_the_right_variant_and_flagged(): void
    {
        $sid = Str::random(40);
        [, $item, $large] = $this->cartWithSecondVariant($sid);
        $large->update(['price' => 45.00]); // repriced after the customer filled the cart

        $this->sessionCookie($sid)->post('/guest/checkout/cart', $this->form())->assertRedirect();

        [$params] = $this->stripeSessionRequest();
        $this->assertSame(5000, (int) $params['line_items'][0]['price_data']['unit_amount']);
        $line = CheckoutSnapshot::sole()->lines[0];
        $this->assertEquals(45.00, $line['price']);
        $this->assertTrue($line['price_changed']);
        $this->assertEquals(40.00, $line['previous_price']);
        $this->assertEquals(45.00, $item->fresh()->price);
    }

    public function test_the_cart_and_details_pages_show_the_non_default_variants_price(): void
    {
        $sid = Str::random(40);
        $this->cartWithSecondVariant($sid);

        $this->sessionCookie($sid)->get('/cart')->assertInertia(fn ($page) => $page->where('subtotal', 40)->where('cartItems.0.price', 40));
        $this->sessionCookie($sid)->get('/guest/checkout/details')->assertInertia(fn ($page) => $page->where('subtotal', 40)->where('total', 45));
    }
}
