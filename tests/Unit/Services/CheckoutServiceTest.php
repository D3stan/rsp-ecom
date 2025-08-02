<?php

namespace Tests\Unit\Services;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Size;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CheckoutService $checkoutService;
    protected User $user;
    protected Product $product;
    protected Size $size;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checkoutService = new CheckoutService();
        $this->user = User::factory()->create();
        $this->category = Category::factory()->create();
        $this->product = Product::factory()->create([
            'category_id' => $this->category->id,
            'price' => 25.00,
        ]);
        $this->size = Size::factory()->create();
    }

    public function test_calculate_totals_with_single_item(): void
    {
        $cartItems = collect([
            (object) [
                'product' => $this->product,
                'size' => $this->size,
                'quantity' => 2,
            ],
        ]);

        $totals = $this->checkoutService->calculateTotals($cartItems);

        $this->assertEquals(50.00, $totals['subtotal']);
        $this->assertEquals(2, $totals['total_quantity']);
        $this->assertEquals(10.00, $totals['shipping_cost']); // Under $100 threshold
        $this->assertEquals(4.38, $totals['tax_amount']); // 8.75% of $50
        $this->assertEquals(64.38, $totals['total']);
    }

    public function test_calculate_totals_with_multiple_items(): void
    {
        $product2 = Product::factory()->create([
            'category_id' => $this->category->id,
            'price' => 15.00,
        ]);

        $cartItems = collect([
            (object) [
                'product' => $this->product,
                'size' => $this->size,
                'quantity' => 2,
            ],
            (object) [
                'product' => $product2,
                'size' => $this->size,
                'quantity' => 1,
            ],
        ]);

        $totals = $this->checkoutService->calculateTotals($cartItems);

        $this->assertEquals(65.00, $totals['subtotal']); // (25 * 2) + (15 * 1)
        $this->assertEquals(3, $totals['total_quantity']);
        $this->assertEquals(10.00, $totals['shipping_cost']);
        $this->assertEquals(5.69, $totals['tax_amount']); // 8.75% of $65
        $this->assertEquals(80.69, $totals['total']);
    }

    public function test_free_shipping_over_threshold(): void
    {
        $expensiveProduct = Product::factory()->create([
            'category_id' => $this->category->id,
            'price' => 150.00,
        ]);

        $cartItems = collect([
            (object) [
                'product' => $expensiveProduct,
                'size' => $this->size,
                'quantity' => 1,
            ],
        ]);

        $totals = $this->checkoutService->calculateTotals($cartItems);

        $this->assertEquals(150.00, $totals['subtotal']);
        $this->assertEquals(0.00, $totals['shipping_cost']); // Free shipping over $100
        $this->assertEquals(13.13, $totals['tax_amount']);
        $this->assertEquals(163.13, $totals['total']);
    }

    public function test_calculate_totals_handles_empty_cart(): void
    {
        $cartItems = collect([]);

        $totals = $this->checkoutService->calculateTotals($cartItems);

        $this->assertEquals(0.00, $totals['subtotal']);
        $this->assertEquals(0, $totals['total_quantity']);
        $this->assertEquals(10.00, $totals['shipping_cost']); // Still charges shipping for empty cart
        $this->assertEquals(0.00, $totals['tax_amount']);
        $this->assertEquals(10.00, $totals['total']);
    }

    public function test_calculate_totals_tax_rate_applied_correctly(): void
    {
        $cartItems = collect([
            (object) [
                'product' => Product::factory()->create([
                    'category_id' => $this->category->id,
                    'price' => 100.00,
                ]),
                'size' => $this->size,
                'quantity' => 1,
            ],
        ]);

        $totals = $this->checkoutService->calculateTotals($cartItems);

        $expectedTax = round(100.00 * 0.0875, 2); // 8.75% tax rate
        $this->assertEquals($expectedTax, $totals['tax_amount']);
        $this->assertEquals(0.0875, $totals['tax_rate']);
    }

    public function test_retrieve_checkout_session_handles_invalid_session(): void
    {
        $this->expectException(\Stripe\Exception\InvalidRequestException::class);

        $this->checkoutService->retrieveCheckoutSession('invalid_session_id');
    }

    public function test_handle_webhook_event_logs_unknown_event_type(): void
    {
        $event = (object) [
            'type' => 'unknown.event.type',
            'id' => 'evt_test_123',
            'data' => (object) ['object' => (object) []],
        ];

        // This should not throw an exception
        $this->checkoutService->handleWebhookEvent($event);

        // We can't easily test log output without mocking the Log facade
        // but we can ensure the method completes without error
        $this->assertTrue(true);
    }

    public function test_calculate_totals_precision(): void
    {
        // Test with prices that might cause floating point precision issues
        $product1 = Product::factory()->create([
            'category_id' => $this->category->id,
            'price' => 19.99,
        ]);

        $product2 = Product::factory()->create([
            'category_id' => $this->category->id,
            'price' => 29.99,
        ]);

        $cartItems = collect([
            (object) [
                'product' => $product1,
                'size' => $this->size,
                'quantity' => 3,
            ],
            (object) [
                'product' => $product2,
                'size' => $this->size,
                'quantity' => 2,
            ],
        ]);

        $totals = $this->checkoutService->calculateTotals($cartItems);

        $expectedSubtotal = (19.99 * 3) + (29.99 * 2); // 59.97 + 59.98 = 119.95
        $this->assertEquals($expectedSubtotal, $totals['subtotal']);
        
        // Ensure totals are properly rounded to 2 decimal places
        $this->assertEquals(2, strlen(substr(strrchr($totals['total'], '.'), 1)));
    }

    public function test_calculate_totals_quantity_aggregation(): void
    {
        $cartItems = collect([
            (object) [
                'product' => $this->product,
                'size' => $this->size,
                'quantity' => 3,
            ],
            (object) [
                'product' => $this->product,
                'size' => $this->size,
                'quantity' => 2,
            ],
        ]);

        $totals = $this->checkoutService->calculateTotals($cartItems);

        $this->assertEquals(5, $totals['total_quantity']);
        $this->assertEquals(125.00, $totals['subtotal']); // 25 * 5
    }
}
