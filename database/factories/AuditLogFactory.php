<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_id' => User::factory()->state(['role' => 'admin']),
            'action' => 'product.updated',
            'subject_type' => 'Product',
            'subject_id' => fake()->numberBetween(1, 500),
            'subject_label' => fake()->words(2, true),
            'changes' => null,
            'ip_address' => fake()->ipv4(),
            'created_at' => now(),
        ];
    }
}
