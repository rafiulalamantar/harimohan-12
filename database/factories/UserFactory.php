<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
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
            'phone' => fake()->unique()->numerify('01'.fake()->numberBetween(3, 9).'########'),
            'ssc_batch' => fake()->numberBetween(1990, 2022),
            'role' => 'student',
            'status' => 'approved',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin', 'admin_level' => User::ADMIN_LEVEL_SUPER, 'status' => 'approved']);
    }

    /** An approved student a super admin has made an admin. */
    public function studentAdmin(): static
    {
        return $this->state(fn () => ['admin_level' => User::ADMIN_LEVEL_ADMIN]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
