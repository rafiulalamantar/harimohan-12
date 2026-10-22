<?php

namespace Database\Factories;

use App\Models\MemberEmail;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberEmail>
 */
class MemberEmailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->admin(),
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'audience' => MemberEmail::AUDIENCE_ALL,
            'ssc_batch' => null,
            'recipient_count' => fake()->numberBetween(1, 50),
        ];
    }
}
