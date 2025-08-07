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
        $this->size = Size::factory()->create();
        $this->product = Product::factory()->create([
            'category_id' => $this->category->id,
            'size_id' => $this->size->id,
            'price' => 25.00,
        ]);
    }

    public function test_calculate_totals_with_single_item(): void
    {
        // Create required settings for tax calculation
        \App\Models\Setting::set('tax_rate', '8.75', 'string');
        \App\Models\Setting::set('prices_include_tax', '0', 'boolean');

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
        // Create required settings for tax calculation
        \App\Models\Setting::set('tax_rate', '8.75', 'string');
        \App\Models\Setting::set('prices_include_tax', '0', 'boolean');

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
        // Create required settings for tax calculation
        \App\Models\Setting::set('tax_rate', '8.75', 'string');
        \App\Models\Setting::set('prices_include_tax', '0', 'boolean');

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
        // Create required settings for tax calculation
        \App\Models\Setting::set('tax_rate', '8.75', 'string');
        \App\Models\Setting::set('prices_include_tax', '0', 'boolean');

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
        // Create required settings for tax calculation
        \App\Models\Setting::set('tax_rate', '8.75', 'string');
        \App\Models\Setting::set('prices_include_tax', '0', 'boolean');

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

    public function test_retrieve_checkout_session_method_exists(): void
    {
        // Simply test that the method exists and accepts the right parameters
        $this->assertTrue(method_exists($this->checkoutService, 'retrieveCheckoutSession'));
        
        // Test with a method that doesn't make actual API calls
        $reflection = new \ReflectionClass($this->checkoutService);
        $method = $reflection->getMethod('retrieveCheckoutSession');
        
        $this->assertEquals(1, $method->getNumberOfRequiredParameters());
    }

    public function test_calculate_totals_precision(): void
    {
        // Create required settings for tax calculation
        \App\Models\Setting::set('tax_rate', '8.75', 'string');
        \App\Models\Setting::set('prices_include_tax', '0', 'boolean');

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

        $expectedSubtotal = round((19.99 * 3) + (29.99 * 2), 2); // Round expected result
        $this->assertEquals($expectedSubtotal, $totals['subtotal']);
        
        // Ensure totals are properly rounded to 2 decimal places
        $this->assertEquals(2, strlen(substr(strrchr($totals['total'], '.'), 1)));
    }

    public function test_calculate_totals_quantity_aggregation(): void
    {
        // Create required settings for tax calculation
        \App\Models\Setting::set('tax_rate', '8.75', 'string');
        \App\Models\Setting::set('prices_include_tax', '0', 'boolean');

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
