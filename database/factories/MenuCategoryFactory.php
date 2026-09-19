<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\MenuCategory>
 */
class MenuCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_name' => fake()->unique()->words(2, true),
            'display_order' => 0,
            'is_active' => true,
        ];
    }
}
