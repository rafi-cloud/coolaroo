<?php

namespace Database\Factories;

use App\Enums\Destination;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\MenuItem>
 */
class MenuItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => MenuCategoryFactory::new(),
            'item_name' => fake()->unique()->words(2, true),
            'destination' => Destination::Kitchen,
            'is_active' => true,
        ];
    }
}
