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
            ['T9', 4, 'Dining'],
            ['B1', 2, 'Bar'],
            ['B2', 2, 'Bar'],
            ['B3', 2, 'Bar'],
        ];

        foreach ($tables as [$number, $capacity, $section]) {
            $existing = RestaurantTable::where('table_number', $number)->first();
            if ($existing) {
                $existing->update([
                    'seat_capacity' => $capacity,
                    'section' => $section,
                    'is_active' => true,
                ]);
            } else {
                RestaurantTable::create([
                    'table_number' => $number,
                    'seat_capacity' => $capacity,
                    'section' => $section,
                    'qr_token' => Str::random(64),
                    'is_active' => true,
                ]);
            }
        }

        $keepNumbers = collect($tables)->pluck(0)->all();
        $t1 = RestaurantTable::where('table_number', 'T1')->first();
        $extra = RestaurantTable::whereNotIn('table_number', $keepNumbers)->get();
        foreach ($extra as $table) {
            if ($t1) {
                $table->visits()->update(['table_id' => $t1->table_id]);
                $table->orders()->update(['table_id' => $t1->table_id]);
            }
            $table->delete();
        }
    }
}
