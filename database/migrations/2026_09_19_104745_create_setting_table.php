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
            $table->string('setting_key', 60)->primary();
            $table->string('setting_value', 255);
            $table->string('value_type', 10)->default('string');
            $table->string('description', 255)->nullable();
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
