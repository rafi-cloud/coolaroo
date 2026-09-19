<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        Customer::create([
            'email' => 'customer@coolaroo.test',
            'password_hash' => 'password',
            'full_name' => 'Cara Customer',
            'phone' => '0400000000',
            'email_verified_at' => now(),
        ]);
    }
}
