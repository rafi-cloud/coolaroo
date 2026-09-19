<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\RestaurantTable>
 */
class RestaurantTableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'table_number' => 'T'.fake()->unique()->numberBetween(100, 999),
            'seat_capacity' => fake()->numberBetween(2, 8),
            'section' => 'Dining',
            'qr_token' => Str::random(64),
            'is_active' => true,
        ];
    }
}
