<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $corrections = [
        'floor.index' => 'staff.floor.index',
        'kds.kitchen' => 'staff.kds.kitchen',
        'kds.bar' => 'staff.kds.bar',
    ];

    public function up(): void
    {
        foreach ($this->corrections as $old => $new) {
            DB::table('role')->where('landing_screen', $old)->update(['landing_screen' => $new]);
        }
    }

    public function down(): void
    {
        foreach ($this->corrections as $old => $new) {
            DB::table('role')->where('landing_screen', $new)->update(['landing_screen' => $old]);
        }
    }
};
