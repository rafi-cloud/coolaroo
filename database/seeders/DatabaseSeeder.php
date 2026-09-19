<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            StaffSeeder::class,
            CustomerSeeder::class,
            SettingSeeder::class,
            SlotCapacitySeeder::class,
            RestaurantTableSeeder::class,
        ]);
    }
}
