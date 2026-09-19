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
        Schema::create('payment', function (Blueprint $table) {
            $table->increments('payment_id');
            $table->unsignedInteger('order_id');
            $table->unsignedInteger('recorded_by_staff_id')->nullable();
            $table->string('method', 10);
            $table->decimal('amount', 8, 2);
            $table->string('stripe_session_id', 100)->nullable()->unique();
            $table->string('provider_payment_id', 100)->nullable();
            $table->decimal('amount_received', 8, 2)->nullable();
            $table->decimal('change_given', 8, 2)->nullable();
            $table->decimal('rounding_amount', 8, 2)->default(0);
            $table->decimal('adjustment_amount', 8, 2)->default(0);
            $table->string('adjustment_category', 30)->nullable();
            $table->string('adjustment_note', 255)->nullable();
            $table->string('status', 15)->default('pending');
            $table->unsignedInteger('succeeded_order_id')->nullable()->storedAs("CASE WHEN status = 'succeeded' THEN order_id END")->unique();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->foreign('order_id')->references('order_id')->on('orders')->restrictOnDelete();
            $table->foreign('recorded_by_staff_id')->references('staff_id')->on('staff')->restrictOnDelete();

            $table->index(['order_id', 'status']);
            $table->index(['method', 'paid_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment');
    }
};
