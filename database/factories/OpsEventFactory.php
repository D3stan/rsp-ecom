<?php

namespace Database\Factories;

use App\Enums\OpsEventType;
use App\Models\OpsEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpsEvent>
 */
class OpsEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => OpsEventType::AddToCart,
            'visitor' => fake()->sha1(),
            'occurred_on' => now()->toDateString(),
            'created_at' => now(),
        ];
    }

    public function ofType(OpsEventType $type): static
    {
        return $this->state(['type' => $type]);
    }

    public function at(\DateTimeInterface $moment): static
    {
        return $this->state(['occurred_on' => $moment->format('Y-m-d'), 'created_at' => $moment]);
    }

    public function withoutVisitor(): static
    {
        return $this->state(['visitor' => null]);
    }
}
