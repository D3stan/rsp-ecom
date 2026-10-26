<?php

namespace Tests\Feature\Orders;

use App\Jobs\SendOrderEmail;
use App\Mail\OversoldOrderAlert;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderEmail;
use App\Models\Product;
use App\Models\User;
use App\Services\EmailService;
use App\Services\OrderCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Orders\Support\BuildsStripeOrderFixtures;
use Tests\TestCase;

/**
 * Stock follows paid orders: deducted once, never rejecting the order, restored once on cancellation.
 * Stock is always set explicitly here (the product factory randomises it).
 */
class OrderStockTest extends TestCase
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

    private function service(): OrderCreationService
    {
        return app(OrderCreationService::class);
    }

    /** Product of the last cartWithStock() cart (the cart itself is deleted once the order is paid). */
    private Product $stocked;

    private function stockOf(?Cart $cart = null): int
    {
        return $this->stocked->defaultVariant->fresh()->stock_quantity;
    }

    /** Standard cart (2 x 25.00) whose default variant holds $stock units. */
    private function cartWithStock(int $stock, string $sid = 'stock-guest', int $quantity = 2): Cart
    {
        $cart = $this->makeCart(guestSessionId: $sid, quantity: $quantity);
        $this->stocked = $cart->cartItems()->first()->product;
        $this->setStock($this->stocked, $stock);

        return $cart->fresh();
    }

    private function setStock(Product $product, int $stock): void
    {
        $product->defaultVariant->update(['stock_quantity' => $stock]);
        $product->syncStockMirror();
    }

    public function test_a_paid_order_deducts_the_stock_once_across_replays(): void
    {
        $cart = $this->cartWithStock(10);
        $payload = $this->guestSessionPayload($cart);

        $order = $this->service()->createFromStripeSession($payload);
        $this->service()->createFromStripeSession($payload);
        $this->service()->createFromStripeSession($payload);

        $this->assertSame(8, $this->stockOf($cart));
        $this->assertSame(8, $this->stocked->fresh()->stock_quantity, 'the legacy mirror follows the default variant');
        $this->assertNotNull($order->fresh()->stock_deducted_at);
        $this->assertFalse((bool) $order->fresh()->has_oversold_items);
        $this->assertSame(0, $order->orderItems->first()->oversold_qty);
    }

    public function test_a_shortfall_is_flagged_and_the_paid_order_is_not_rejected(): void
    {
        $cart = $this->cartWithStock(1);
        $mock = \Mockery::mock(EmailService::class);
        $mock->shouldReceive('sendOrderConfirmation')->andReturn(true);
        $mock->shouldReceive('sendNewOrderAlert')->andReturn(true);
        $mock->shouldReceive('sendOversoldAlert')->atLeast()->once()->andReturn(true);
        $this->app->instance(EmailService::class, $mock);

        $order = $this->service()->createFromStripeSession($this->guestSessionPayload($cart));

        $this->assertSame('succeeded', $order->payment_status);
        $this->assertSame(1, $order->orderItems()->count());
        $this->assertSame(0, $this->stockOf($cart), 'stock never goes below zero');
        $this->assertTrue((bool) $order->fresh()->has_oversold_items);
        $this->assertSame(1, $order->orderItems->first()->oversold_qty); // 2 ordered, 1 available
    }

    public function test_the_oversold_alert_is_queued_once_for_the_admin_and_not_for_normal_orders(): void
    {
        Queue::fake();
        $this->app->forgetInstance(EmailService::class);
        $this->app->bind(EmailService::class, fn () => new EmailService);
        config(['mail.contact_email' => 'owner@shop.test']);

        $normal = $this->cartWithStock(10, 'normal-guest');
        $this->service()->createFromStripeSession($this->guestSessionPayload($normal, ['id' => 'cs_normal']));
        $this->assertSame(0, OrderEmail::where('type', OrderEmail::TYPE_ADMIN_OVERSOLD)->count());

        $short = $this->cartWithStock(0, 'short-guest');
        $payload = $this->guestSessionPayload($short, ['id' => 'cs_short']);
        $this->service()->createFromStripeSession($payload);
        $this->service()->createFromStripeSession($payload);

        $this->assertSame(1, OrderEmail::where('type', OrderEmail::TYPE_ADMIN_OVERSOLD)->count());
        Queue::assertPushed(SendOrderEmail::class, fn ($job) => $job->type === OrderEmail::TYPE_ADMIN_OVERSOLD);
    }

    public function test_the_oversold_alert_respects_the_master_email_switch(): void
    {
        Queue::fake();
        $this->app->bind(EmailService::class, fn () => new EmailService);
        config(['mail.contact_email' => 'owner@shop.test']);
        settings()->set('email.enabled', false);

        $this->service()->createFromStripeSession($this->guestSessionPayload($this->cartWithStock(0)));

        Queue::assertNotPushed(SendOrderEmail::class);
    }

    public function test_the_oversold_alert_renders_in_every_locale(): void
    {
        $cart = $this->cartWithStock(0);
        $order = $this->service()->createFromStripeSession($this->guestSessionPayload($cart));

        foreach (['it', 'en', 'de', 'es', 'fr'] as $locale) {
            app()->setLocale($locale);
            $mailable = new OversoldOrderAlert($order->fresh());
            $html = $mailable->render();

            $this->assertStringContainsString($order->order_number, $html, $locale);
            $this->assertStringNotContainsString('emails.admin.oversold', $html, $locale);
            $this->assertStringNotContainsString('emails.admin.oversold', $mailable->envelope()->subject, $locale);
        }
    }

    public function test_cancelling_a_paid_order_restores_the_stock_exactly_once(): void
    {
        $cart = $this->cartWithStock(10);
        $order = $this->service()->createFromStripeSession($this->guestSessionPayload($cart));
        $this->assertSame(8, $this->stockOf($cart));

        $order->update(['status' => 'cancelled']);
        $this->assertSame(10, $this->stockOf($cart));
        $this->assertNotNull($order->fresh()->stock_restored_at);
        $this->assertSame(10, $this->stocked->fresh()->stock_quantity);

        // Out of cancelled and back: no second restore, and no re-deduction either (documented policy)
        $order->update(['status' => 'processing']);
        $this->assertSame(10, $this->stockOf($cart));
        $order->update(['status' => 'cancelled']);
        $this->assertSame(10, $this->stockOf($cart));
    }

    public function test_cancelling_restores_only_the_units_that_were_actually_deducted(): void
    {
        $cart = $this->cartWithStock(1); // 2 ordered, 1 available
        $order = $this->service()->createFromStripeSession($this->guestSessionPayload($cart));

        $order->update(['status' => 'cancelled']);

        $this->assertSame(1, $this->stockOf($cart));
    }

    public function test_cancelling_an_unpaid_order_restores_nothing(): void
    {
        $cart = $this->cartWithStock(10);
        $order = $this->service()->createFromStripeSession($this->guestSessionPayload($cart, ['payment_status' => 'unpaid']));

        $this->assertSame(10, $this->stockOf($cart), 'an unpaid order takes no stock');
        $this->assertNull($order->stock_deducted_at);

        $order->update(['status' => 'cancelled']);

        $this->assertSame(10, $this->stockOf($cart));
        $this->assertNull($order->fresh()->stock_restored_at);
    }

    public function test_a_legacy_paid_order_without_stock_bookkeeping_restores_nothing_on_cancel(): void
    {
        $cart = $this->cartWithStock(10);
        $order = Order::factory()->create(['status' => 'processing', 'payment_status' => 'succeeded']);
        $order->orderItems()->create([
            'product_id' => $this->stocked->id,
            'product_name' => 'x', 'quantity' => 3, 'price' => 1, 'total' => 3,
        ]);

        $order->update(['status' => 'cancelled']);

        $this->assertSame(10, $this->stockOf($cart));
    }

    public function test_a_pending_order_that_becomes_paid_deducts_the_stock_then(): void
    {
        $cart = $this->cartWithStock(10);
        $this->service()->createFromStripeSession($this->guestSessionPayload($cart, ['payment_status' => 'unpaid']));
        $this->assertSame(10, $this->stockOf($cart));

        $order = $this->service()->createFromStripeSession($this->guestSessionPayload($cart));

        $this->assertSame(8, $this->stockOf($cart));
        $this->assertNotNull($order->fresh()->stock_deducted_at);
        $this->assertSame(1, Order::count());
    }

    public function test_a_non_default_variant_takes_from_its_own_stock_and_a_line_without_variant_from_the_default(): void
    {
        $cart = Cart::create(['session_id' => 'variant-guest']);

        $withVariant = $this->makeProduct(25.00, 'Has variants');
        $this->setStock($withVariant, 20);
        $large = $withVariant->variants()->create([
            'name' => 'Large', 'sku_suffix' => '-L', 'price' => 40, 'stock_quantity' => 5,
            'is_default' => false, 'sort_order' => 1, 'status' => 'active', 'images' => [],
        ]);
        $cart->cartItems()->create([
            'product_id' => $withVariant->id, 'product_variant_id' => $large->id,
            'size_id' => $this->size->id, 'quantity' => 2, 'price' => 40,
        ]);

        $legacy = $this->makeProduct(10.00, 'Legacy line');
        $this->setStock($legacy, 7);
        $cart->cartItems()->create([
            'product_id' => $legacy->id, 'product_variant_id' => null,
            'size_id' => $this->size->id, 'quantity' => 3, 'price' => 10,
        ]);

        $this->service()->createFromStripeSession($this->guestSessionPayload($cart->fresh(), [
            'amount_subtotal' => 11500, 'amount_total' => 11500,
        ]));

        $this->assertSame(3, $large->fresh()->stock_quantity);
        $this->assertSame(20, $withVariant->defaultVariant->fresh()->stock_quantity);
        $this->assertSame(20, $withVariant->fresh()->stock_quantity, 'the mirror stays on the default variant');
        $this->assertSame(4, $legacy->defaultVariant->fresh()->stock_quantity);
        $this->assertSame(4, $legacy->fresh()->stock_quantity);
    }

    public function test_a_line_without_a_known_product_is_skipped_without_failing_the_order(): void
    {
        // No cart and no snapshot: the item comes from Stripe's line items and has no product id
        $this->stripeHttp->responses['/v1/checkout/sessions/cs_test_123/line_items'] = ['object' => 'list', 'data' => []];

        $order = $this->service()->createFromStripeSession($this->guestSessionPayload(new Cart(['session_id' => 'gone'])));

        $this->assertSame('succeeded', $order->payment_status);
        $this->assertNull($order->orderItems->sole()->product_id);
        $this->assertNotNull($order->fresh()->stock_deducted_at);
        $this->assertFalse((bool) $order->fresh()->has_oversold_items);
    }

    public function test_losing_the_creation_race_deducts_the_stock_only_once(): void
    {
        $cart = $this->cartWithStock(10, 'race-guest');
        $payload = $this->guestSessionPayload($cart);
        $winner = $this->service()->createFromStripeSession($payload);

        $racing = new class extends OrderCreationService
        {
            public int $lookups = 0;

            protected function lockedLookup(string $sessionId): ?Order
            {
                return $this->lookups++ === 0 ? null : parent::lockedLookup($sessionId);
            }
        };

        $racing->createFromStripeSession($this->guestSessionPayload($this->makeCart(guestSessionId: 'race-guest')));

        $this->assertSame(1, Order::count());
        $this->assertSame($winner->id, Order::sole()->id);
        $this->assertSame(8, $this->stockOf($cart));
    }

    public function test_the_admin_order_page_gets_the_oversold_info_and_the_customer_arrays_do_not(): void
    {
        $cart = $this->cartWithStock(1);
        $order = $this->service()->createFromStripeSession($this->guestSessionPayload($cart));
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->get("/admin/orders/{$order->id}")->assertInertia(fn ($page) => $page
            ->component('Admin/Orders/Show')
            ->where('stock.has_oversold_items', true)
            ->where('stock.oversold.'.$order->orderItems->first()->id, 1));

        $order->load('orderItems');
        $customerView = $order->toFrontendArray();
        $this->assertArrayNotHasKey('has_oversold_items', $customerView);
        $this->assertArrayNotHasKey('stock_deducted_at', $customerView);
        $this->assertArrayNotHasKey('oversold_qty', $order->orderItems->first()->toArray());
    }
}
