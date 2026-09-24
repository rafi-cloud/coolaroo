<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::create([
            'role_name' => 'admin',
            'description' => 'Full access to every admin screen.',
            'landing_screen' => 'admin.dashboard',
        ]);

        Role::create([
            'role_name' => 'waitstaff',
            'description' => 'Floor, orders, reservations, refund requests.',
            'landing_screen' => 'staff.floor.index',
        ]);

        Role::create([
            'role_name' => 'kitchen',
            'description' => 'Kitchen station display.',
            'landing_screen' => 'staff.kds.kitchen',
        ]);

        Role::create([
            'role_name' => 'bar',
            'description' => 'Bar station display.',
            'landing_screen' => 'staff.kds.bar',
        ]);
    }
}
