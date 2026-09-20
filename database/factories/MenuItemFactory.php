<?php

namespace Database\Factories;

use App\Enums\Destination;
use App\Models\MenuItem;
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
            'is_available' => true,
        ];
    }

    public function withSize(): static
    {
        return $this->afterCreating(function (MenuItem $item) {
            $item->sizes()->create(['size_name' => 'Regular', 'price' => 12.50]);
        });
    }
}
