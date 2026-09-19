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
        Schema::create('visit', function (Blueprint $table) {
            $table->increments('visit_id');
            $table->unsignedInteger('table_id');
            $table->unsignedInteger('reservation_id')->nullable(); // NULL = walk-in or QR occupancy
            $table->unsignedInteger('opened_by_staff_id')->nullable(); // NULL when opened by paid order or holder scan
            $table->unsignedInteger('closed_by_staff_id')->nullable(); // NULL when closed by system
            $table->unsignedTinyInteger('guest_count')->nullable(); // party size for reservations; optional for walk-ins
            $table->dateTime('opened_at')->nullable(); // NULL = assigned to reservation, not yet occupied (BR04)
            $table->dateTime('closed_at')->nullable(); // set when cleared or assignment removed
            $table->string('close_reason', 20)->nullable(); // enum staff_clear/auto_clear/no_show/cancelled/unassigned/override
            $table->dateTime('created_at')->useCurrent(); // assignment or opening time

            $table->foreign('table_id')->references('table_id')->on('restaurant_table')->restrictOnDelete();
            $table->foreign('reservation_id')->references('reservation_id')->on('reservation')->nullOnDelete();
            $table->foreign('opened_by_staff_id')->references('staff_id')->on('staff')->restrictOnDelete();
            $table->foreign('closed_by_staff_id')->references('staff_id')->on('staff')->restrictOnDelete();

            $table->index(['table_id', 'closed_at']); // find open visit of a table
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visit');
    }
};
