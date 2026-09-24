<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * @var array<int, class-string<Seeder>>
     */
    public const FOUNDATION = [
        RoleSeeder::class,
        StaffSeeder::class,
        CustomerSeeder::class,
        SettingSeeder::class,
        SlotCapacitySeeder::class,
        RestaurantTableSeeder::class,
    ];

    public function run(): void
    {
        $this->call(self::FOUNDATION);
        $this->call(DemoSeeder::class);
    }
}
