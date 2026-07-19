<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => fake()->randomElement(['patient', 'doctor']),
        ];
    }

    public function patient(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'patient',
        ]);
    }

    public function doctor(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'doctor',
        ]);
    }
}