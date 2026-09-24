<?php

namespace Database\Seeders;

use App\Models\SlotCapacity;
use Illuminate\Database\Seeder;

class SlotCapacitySeeder extends Seeder
{
    public function run(): void
    {
        $slots = [
            ['11:30', 20],
            ['12:00', 40],
            ['12:30', 40],
            ['13:00', 40],
            ['13:30', 30],
            ['14:00', 24],
            ['14:30', 20],
            ['15:00', 16],
            ['15:30', 16],
            ['16:00', 16],
            ['16:30', 20],
            ['17:00', 30],
            ['17:30', 40],
            ['18:00', 60],
            ['18:30', 60],
            ['19:00', 60],
            ['19:30', 60],
            ['20:00', 50],
            ['20:30', 40],
            ['21:00', 30],
        ];

        foreach ($slots as [$time, $maxCovers]) {
            SlotCapacity::updateOrCreate(
                ['slot_time' => $time],
                ['max_covers' => $maxCovers, 'is_active' => true],
            );
        }
    }
}
