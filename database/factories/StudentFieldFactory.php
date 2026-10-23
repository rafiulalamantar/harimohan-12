<?php

namespace Database\Factories;

use App\Models\StudentField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentField>
 */
class StudentFieldFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => fake()->words(2, true),
            'type' => 'text',
            'is_required' => false,
            'is_active' => true,
        ];
    }

    public function required(): static
    {
        return $this->state(fn () => ['is_required' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * @param  array<int, string>  $options
     */
    public function select(array $options = ['S', 'M', 'L', 'XL', 'XXL']): static
    {
        return $this->state(fn () => ['type' => 'select', 'options' => $options]);
    }

    /**
     * @param  array<int, string>  $options
     */
    public function checkboxes(array $options = ['Lunch', 'Dinner', 'Cultural night']): static
    {
        return $this->state(fn () => ['type' => 'checkboxes', 'options' => $options]);
    }
}
