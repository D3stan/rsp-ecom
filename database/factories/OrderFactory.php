<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Address;
use Database\Seeders\SeederConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->getRandomStatus();
        $orderDate = $this->faker->dateTimeBetween('-6 months', 'now');
        
        // Calculate shipping and delivery dates based on status
        $shippingDate = null;
        $deliveryDate = null;
        
        if (in_array($status, ['shipped', 'delivered'])) {
            $shippingDate = $this->faker->dateTimeBetween($orderDate, '+3 days');
        }
        
        if ($status === 'delivered') {
            $deliveryDate = $this->faker->dateTimeBetween($shippingDate ?? $orderDate, '+7 days');
        }

        $subtotal = $this->faker->randomFloat(2, 15, 500);
        $taxRate = $this->faker->randomFloat(4, 0.15, 0.25); // EU tax rates
        $taxAmount = round($subtotal * $taxRate, 2);
        $shippingAmount = $subtotal > 50 ? 0 : $this->faker->randomFloat(2, 4.99, 9.99);
        $total = $subtotal + $taxAmount + $shippingAmount;

        return [
            'user_id' => User::factory(),
            'shipping_address_id' => Address::factory()->shipping(),
            'billing_address_id' => Address::factory()->billing(),
            'status' => $status,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'shipping_amount' => $shippingAmount,
            'total_amount' => $total,
            'currency' => $this->faker->randomElement(['EUR', 'USD', 'GBP']),
            'notes' => $this->faker->boolean(15) ? $this->faker->sentence() : null,
        ];
    }

    /**
     * Get random status with realistic distribution
     */
    private function getRandomStatus(): string
    {
        $statuses = [
            'pending' => 5,      // 5%
            'processing' => 5,   // 5%
            'shipped' => 15,     // 15%
            'delivered' => 70,   // 70%
            'cancelled' => 5,    // 5%
        ];

        $rand = $this->faker->numberBetween(1, 100);
        $cumulative = 0;

        foreach ($statuses as $status => $percentage) {
            $cumulative += $percentage;
            if ($rand <= $cumulative) {
                return $status;
            }
        }

        return 'delivered'; // fallback
    }

    /**
     * Get appropriate payment status based on order status
     */
    private function getPaymentStatusForOrder(string $orderStatus): string
    {
        return match ($orderStatus) {
            'pending' => $this->faker->randomElement(['pending', 'failed']),
            'processing', 'shipped', 'delivered' => 'paid',
            'cancelled' => $this->faker->randomElement(['refunded', 'failed']),
            default => 'pending',
        };
    }

    /**
     * Create a pending order
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Create a processing order
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
        ]);
    }

    /**
     * Create a shipped order
     */
    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'shipped',
        ]);
    }

    /**
     * Create a delivered order
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'delivered',
        ]);
    }

    /**
     * Create a cancelled order
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Create a recent order
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            // Recent orders are handled by seeder timing
        ]);
    }

    /**
     * Create a high-value order
     */
    public function highValue(): static
    {
        return $this->state(function (array $attributes) {
            $subtotal = $this->faker->randomFloat(2, 200, 1000);
            $taxAmount = round($subtotal * 0.20, 2);
            $shippingAmount = 0; // Free shipping for high-value orders
            
            return [
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'shipping_amount' => $shippingAmount,
                'total_amount' => $subtotal + $taxAmount,
            ];
        });
    }
}
