<?php

namespace Database\Seeders;

use App\Models\RestaurantTable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RestaurantTableSeeder extends Seeder
{
    public function run(): void
    {
        $tables = [
            ['T1', 2, 'Dining'],
            ['T2', 2, 'Dining'],
            ['T3', 4, 'Dining'],
            ['T4', 4, 'Dining'],
            ['T5', 4, 'Dining'],
            ['T6', 4, 'Dining'],
            ['T7', 6, 'Dining'],
            ['T8', 6, 'Dining'],
            ['T9', 2, 'Bar'],
            ['T10', 4, 'Bar'],
            ['T11', 4, 'Outdoor'],
            ['T12', 6, 'Outdoor'],
        ];

        foreach ($tables as [$number, $capacity, $section]) {
            RestaurantTable::create([
                'table_number' => $number,
                'seat_capacity' => $capacity,
                'section' => $section,
                'qr_token' => Str::random(64),
            ]);
        }
    }
}
