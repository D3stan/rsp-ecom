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

        // Prices are VAT-inclusive (European VAT model): subtotal and total contain the VAT, tax_amount is the
        // portion of the total at tax_rate (same rule as App\Support\Vat). Shipping is always charged.
        $subtotal = $this->faker->randomFloat(2, 15, 500);
        $taxRate = 22.0;
        $shippingAmount = $this->faker->randomFloat(2, 5.99, 9.99);
        $total = round($subtotal + $shippingAmount, 2);
        $taxAmount = round($total * ($taxRate / 100) / (1 + $taxRate / 100), 2);

        return [
            'user_id' => User::factory(),
            'shipping_address_id' => Address::factory()->shipping(),
            'billing_address_id' => Address::factory()->billing(),
            'status' => $status,
            'subtotal' => $subtotal, // VAT-inclusive
            'tax_amount' => $taxAmount, // VAT contained in the total
            'tax_rate' => $taxRate,
            'shipping_amount' => $shippingAmount,
            'total_amount' => $total,
            'currency' => $this->faker->randomElement(['EUR', 'EUR', 'GBP']),
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
            'pending' => 'failed', // seeded orders have no Stripe session, so nothing would ever expire a `pending` payment
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
            // VAT-inclusive pricing for high-value orders; shipping is charged like on any other order
            $subtotal = $this->faker->randomFloat(2, 200, 1000);
            $taxRate = 22.0;
            $shippingAmount = $this->faker->randomFloat(2, 5.99, 9.99);
            $total = round($subtotal + $shippingAmount, 2);
            $taxAmount = round($total * ($taxRate / 100) / (1 + $taxRate / 100), 2);

            return [
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'tax_rate' => $taxRate,
                'shipping_amount' => $shippingAmount,
                'total_amount' => $total,
            ];
        });
    }
}
