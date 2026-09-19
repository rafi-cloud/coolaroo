<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\SlotCapacity>
 */
class SlotCapacityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slot_time' => fake()->unique()->time('H:i'),
            'max_covers' => fake()->numberBetween(20, 60),
            'is_active' => true,
        ];
    }
}
