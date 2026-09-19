<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Staff>
 */
class StaffFactory extends Factory
{
    public function definition(): array
    {
        return [
            'role_id' => Role::factory(),
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => 'password',
            'full_name' => fake()->name(),
            'phone' => fake()->unique()->numerify('04########'),
            'is_active' => true,
        ];
    }
}
