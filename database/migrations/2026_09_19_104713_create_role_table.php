<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('role', function (Blueprint $table) {
            $table->tinyIncrements('role_id');
            $table->string('role_name', 20)->unique(); // admin, waitstaff, kitchen, bar
            $table->string('description', 150)->nullable();
            $table->string('landing_screen', 50); // route name, e.g. admin.dashboard, floor.index, kds.kitchen
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role');
    }
};
