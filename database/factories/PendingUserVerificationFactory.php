<?php

namespace Database\Factories;

use App\Models\PendingUserVerification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PendingUserVerification>
 */
class PendingUserVerificationFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'verification_token' => PendingUserVerification::generateToken(),
            'token_expires_at' => now()->addHours(24),
        ];
    }

    /**
     * Indicate that the pending verification has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'token_expires_at' => now()->subHours(1),
        ]);
    }

    /**
     * Indicate that the pending verification expires soon.
     */
    public function expiringSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'token_expires_at' => now()->addMinutes(30),
        ]);
    }
}
