<?php

namespace Database\Seeders;

use App\Models\SlotCapacity;
use Illuminate\Database\Seeder;

class SlotCapacitySeeder extends Seeder
{
    public function run(): void
    {
        $slots = [
            ['12:00', 30],
            ['13:00', 30],
            ['14:00', 20],
            ['15:00', 20],
            ['16:00', 20],
            ['17:00', 30],
            ['18:00', 50],
            ['19:00', 50],
            ['20:00', 50],
        ];

        foreach ($slots as [$time, $maxCovers]) {
            SlotCapacity::create([
                'slot_time' => $time,
                'max_covers' => $maxCovers,
            ]);
        }
    }
}
