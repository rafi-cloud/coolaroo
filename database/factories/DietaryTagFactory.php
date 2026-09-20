<?php

namespace Database\Factories;

use App\Models\DietaryTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DietaryTag>
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
