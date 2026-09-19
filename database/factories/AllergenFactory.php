<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Allergen>
 */
class AllergenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'allergen_name' => fake()->unique()->word(),
            'is_active' => true,
        ];
    }
}
