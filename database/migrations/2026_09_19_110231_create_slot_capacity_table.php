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
        Schema::create('slot_capacity', function (Blueprint $table) {
            $table->increments('slot_id');
            $table->time('slot_time')->unique(); // start time, e.g. 18:00; also defines booking hours
            $table->unsignedSmallInteger('max_covers'); // max guests across requested+confirmed bookings (BR32/BR33)
            $table->boolean('is_active')->default(true); // false = not bookable online
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slot_capacity');
    }
};
