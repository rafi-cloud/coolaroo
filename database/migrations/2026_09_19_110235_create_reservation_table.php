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
        Schema::create('reservation', function (Blueprint $table) {
            $table->increments('reservation_id');
            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedInteger('slot_id');
            $table->unsignedInteger('created_by_staff_id')->nullable();
            $table->unsignedInteger('reviewed_by_staff_id')->nullable();
            $table->unsignedInteger('no_show_by_staff_id')->nullable();
            $table->unsignedInteger('no_show_cleared_by_staff_id')->nullable();
            $table->string('reference_code', 16)->unique();
            $table->string('guest_name', 100)->nullable();
            $table->string('guest_phone', 20)->nullable();
            $table->date('booking_date');
            $table->time('booking_time');
            $table->unsignedTinyInteger('party_size');
            $table->string('special_requests', 500)->nullable();
            $table->string('status', 15)->default('requested');
            $table->string('decline_reason', 255)->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('seated_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancelled_by', 10)->nullable();
            $table->boolean('is_late_cancellation')->default(false);
            $table->dateTime('no_show_at')->nullable();
            $table->dateTime('no_show_cleared_at')->nullable();
            $table->string('no_show_clear_reason', 255)->nullable();
            $table->dateTime('reminder_sent_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->nullable();

            $table->foreign('customer_id')->references('customer_id')->on('customer')->restrictOnDelete();
            $table->foreign('slot_id')->references('slot_id')->on('slot_capacity')->restrictOnDelete();
            $table->foreign('created_by_staff_id')->references('staff_id')->on('staff')->restrictOnDelete();
            $table->foreign('reviewed_by_staff_id')->references('staff_id')->on('staff')->restrictOnDelete();
            $table->foreign('no_show_by_staff_id')->references('staff_id')->on('staff')->restrictOnDelete();
            $table->foreign('no_show_cleared_by_staff_id')->references('staff_id')->on('staff')->restrictOnDelete();

            $table->index(['booking_date', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index(['slot_id', 'booking_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation');
    }
};
