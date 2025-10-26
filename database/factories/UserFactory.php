<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name'  => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'ghana_card' => 'GHA-' . strtoupper(Str::random(8)), // Example format: GHA-3DFK29L0
            'user_type' => $this->faker->randomElement(['user', 'developer', 'investor']),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Mark user as unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Developer-specific user.
     */
    public function developer(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'developer',
        ]);
    }

    /**
     * Investor-specific user.
     */
    public function investor(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'investor',
        ]);
    }
}
