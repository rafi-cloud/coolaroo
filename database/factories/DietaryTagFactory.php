<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\DietaryTag>
 */
class DietaryTagFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tag_name' => fake()->unique()->word(),
            'is_active' => true,
        ];
    }
}
