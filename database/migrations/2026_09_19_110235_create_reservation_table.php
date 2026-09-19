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
            $table->unsignedInteger('customer_id')->nullable(); // NULL for guest phone bookings (A5)
            $table->unsignedInteger('slot_id');
            $table->unsignedInteger('created_by_staff_id')->nullable(); // set for phone bookings (FR64)
            $table->unsignedInteger('reviewed_by_staff_id')->nullable(); // staff who approved/declined
            $table->unsignedInteger('no_show_by_staff_id')->nullable(); // staff who confirmed no-show
            $table->unsignedInteger('no_show_cleared_by_staff_id')->nullable(); // admin who cleared flag (FR10)
            $table->string('reference_code', 16)->unique(); // shown to customer, e.g. CR-7K2P9Q
            $table->string('guest_name', 100)->nullable(); // required when customer_id is NULL
            $table->string('guest_phone', 20)->nullable(); // required when customer_id is NULL
            $table->date('booking_date');
            $table->time('booking_time'); // copied from slot_time at booking
            $table->unsignedTinyInteger('party_size'); // <= online max party size for customer requests (BR35)
            $table->string('special_requests', 500)->nullable(); // editable anytime by customer
            $table->string('status', 15)->default('requested'); // reservation state machine (06.3)
            $table->string('decline_reason', 255)->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('seated_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancelled_by', 10)->nullable(); // enum customer/staff
            $table->boolean('is_late_cancellation')->default(false); // 1 if cancelled < 2h before booking (BR38)
            $table->dateTime('no_show_at')->nullable(); // counts toward Flagged badge for 12 months (BR40)
            $table->dateTime('no_show_cleared_at')->nullable(); // cleared no-shows excluded from badge
            $table->string('no_show_clear_reason', 255)->nullable(); // required when cleared
            $table->dateTime('reminder_sent_at')->nullable(); // prevents duplicate reminders (FR75)
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
