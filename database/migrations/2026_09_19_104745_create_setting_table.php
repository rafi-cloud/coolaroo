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
        Schema::create('setting', function (Blueprint $table) {
            $table->string('setting_key', 60)->primary(); // e.g. table_idle_autoclear_minutes
            $table->string('setting_value', 255); // stored as text, cast by value_type at read time
            $table->string('value_type', 10)->default('string'); // int, decimal, bool, time, string (06.3)
            $table->string('description', 255)->nullable(); // help text on the settings screen
            $table->unsignedInteger('updated_by_staff_id')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->foreign('updated_by_staff_id')->references('staff_id')->on('staff')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('setting');
    }
};
