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
        Schema::create('restaurant_table', function (Blueprint $table) {
            $table->increments('table_id');
            $table->string('table_number', 10)->unique();
            $table->unsignedTinyInteger('seat_capacity');
            $table->string('section', 20)->nullable();
            $table->string('qr_token', 64)->unique();
            $table->string('status', 10)->default('available');
            $table->dateTime('status_changed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->useCurrent();

            $table->index(['status', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_table');
    }
};
