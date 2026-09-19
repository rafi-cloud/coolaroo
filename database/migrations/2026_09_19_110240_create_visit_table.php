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
            $table->unsignedInteger('reservation_id')->nullable();
            $table->unsignedInteger('opened_by_staff_id')->nullable();
            $table->unsignedInteger('closed_by_staff_id')->nullable();
            $table->unsignedTinyInteger('guest_count')->nullable();
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->string('close_reason', 20)->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->foreign('table_id')->references('table_id')->on('restaurant_table')->restrictOnDelete();
            $table->foreign('reservation_id')->references('reservation_id')->on('reservation')->nullOnDelete();
            $table->foreign('opened_by_staff_id')->references('staff_id')->on('staff')->restrictOnDelete();
            $table->foreign('closed_by_staff_id')->references('staff_id')->on('staff')->restrictOnDelete();

            $table->index(['table_id', 'closed_at']);
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
