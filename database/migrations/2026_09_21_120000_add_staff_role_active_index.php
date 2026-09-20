<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 06.4.2, NFR08, T219. The staff table's foreign key gives MySQL an index on
 * role_id alone; 6.4.2 asks for the composite that also covers is_active, which
 * is how every staff list and the role filter query the table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->index(['role_id', 'is_active'], 'staff_role_id_is_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropIndex('staff_role_id_is_active_index');
        });
    }
};
