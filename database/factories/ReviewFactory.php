<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rating = $this->getRealisticRating();
        
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'rating' => $rating,
            'comment' => $this->generateReviewComment($rating),
            'is_approved' => $this->faker->boolean(85), // 85% are approved
        ];
    }

    /**
     * Generate realistic rating distribution (higher ratings more common)
     */
    private function getRealisticRating(): int
    {
        $ratings = [
            5 => 45, // 45%
            4 => 30, // 30%
            3 => 15, // 15%
            2 => 7,  // 7%
            1 => 3,  // 3%
        ];

        $rand = $this->faker->numberBetween(1, 100);
        $cumulative = 0;

        foreach ($ratings as $rating => $percentage) {
            $cumulative += $percentage;
            if ($rand <= $cumulative) {
                return $rating;
            }
        }

        return 5; // fallback
    }

    /**
     * Generate a comment based on rating
     */
    private function generateReviewComment(int $rating): string
    {
        $positiveComments = [
            'Excellent product! Highly recommend.',
            'Great quality and fast shipping.',
            'Perfect! Exactly what I was looking for.',
            'Amazing value for money.',
            'Outstanding customer service.',
            'Will definitely buy again.',
            'Exceeded my expectations.',
        ];

        $neutralComments = [
            'It\'s okay, nothing special.',
            'Average product, does the job.',
            'Fair quality for the price.',
            'Not bad, but could be better.',
        ];

        $negativeComments = [
            'Not what I expected.',
            'Poor quality for the price.',
            'Disappointed with this purchase.',
            'Would not recommend.',
            'Took too long to arrive.',
            'Not as described.',
        ];

        return match($rating) {
            5, 4 => $this->faker->randomElement($positiveComments),
            3 => $this->faker->randomElement($neutralComments),
            default => $this->faker->randomElement($negativeComments),
        };
    }

    /**
     * Create an approved review
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approved' => true,
        ]);
    }

    /**
     * Create an unapproved review
     */
    public function unapproved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approved' => false,
        ]);
    }

    /**
     * Create a review for specific product
     */
    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
        ]);
    }

    /**
     * Create a review by specific user
     */
    public function byUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
