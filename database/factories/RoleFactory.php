<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'role_name' => fake()->unique()->word(),
            'description' => fake()->sentence(),
            'landing_screen' => 'admin.dashboard',
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role_name' => 'admin', 'landing_screen' => 'admin.dashboard']);
    }

    public function waitstaff(): static
    {
        return $this->state(fn () => ['role_name' => 'waitstaff', 'landing_screen' => 'floor.index']);
    }

    public function kitchen(): static
    {
        return $this->state(fn () => ['role_name' => 'kitchen', 'landing_screen' => 'kds.kitchen']);
    }

    public function bar(): static
    {
        return $this->state(fn () => ['role_name' => 'bar', 'landing_screen' => 'kds.bar']);
    }
}
