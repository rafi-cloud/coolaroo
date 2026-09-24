<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Staff;
use Illuminate\Database\Seeder;

class StaffSeeder extends Seeder
{
    public const PASSWORD = 'Hello@123';

    public function run(): void
    {
        $accounts = [
            ['role_name' => 'admin', 'email' => 'admin@coolaroo.test', 'full_name' => 'Ava Admin'],
            ['role_name' => 'waitstaff', 'email' => 'waiter@coolaroo.test', 'full_name' => 'Will Waiter'],
            ['role_name' => 'kitchen', 'email' => 'kitchen@coolaroo.test', 'full_name' => 'Kim Kitchen'],
            ['role_name' => 'bar', 'email' => 'bar@coolaroo.test', 'full_name' => 'Bo Bartender'],
        ];

        foreach ($accounts as $account) {
            Staff::updateOrCreate(
                ['email' => $account['email']],
                [
                    'role_id' => Role::where('role_name', $account['role_name'])->value('role_id'),
                    'password_hash' => self::PASSWORD,
                    'full_name' => $account['full_name'],
                ]
            );
        }
    }
}
