<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['admin', 'Full access to every admin screen.', 'admin.dashboard'],
            ['waitstaff', 'Floor, orders, reservations, refund requests.', 'staff.floor.index'],
            ['kitchen', 'Kitchen station display.', 'staff.kds.kitchen'],
            ['bar', 'Bar station display.', 'staff.kds.bar'],
        ];

        foreach ($roles as [$name, $description, $landingScreen]) {
            Role::updateOrCreate(
                ['role_name' => $name],
                ['description' => $description, 'landing_screen' => $landingScreen],
            );
        }
    }
}
