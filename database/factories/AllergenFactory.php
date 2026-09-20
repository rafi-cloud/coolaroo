<?php

namespace Database\Factories;

use App\Models\Allergen;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Allergen>
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
