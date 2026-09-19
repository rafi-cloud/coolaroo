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
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('order_id');
            $table->unsignedInteger('table_id');
            $table->unsignedInteger('visit_id')->nullable();
            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedInteger('taken_by_staff_id')->nullable();
            $table->string('order_number', 12)->unique();
            $table->string('idempotency_key', 64)->unique();
            $table->string('status', 15)->default('pending_payment');
            $table->string('payment_status', 20)->default('unpaid');
            $table->decimal('total_amount', 8, 2);
            $table->decimal('gst_amount', 8, 2);
            $table->boolean('has_stock_conflict')->default(false);
            $table->dateTime('kitchen_eta_at')->nullable();
            $table->dateTime('bar_eta_at')->nullable();
            $table->dateTime('placed_at')->useCurrent();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ready_at')->nullable();
            $table->dateTime('served_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();

            $table->foreign('table_id')->references('table_id')->on('restaurant_table')->restrictOnDelete();
            $table->foreign('visit_id')->references('visit_id')->on('visit')->restrictOnDelete();
            $table->foreign('customer_id')->references('customer_id')->on('customer')->restrictOnDelete();
            $table->foreign('taken_by_staff_id')->references('staff_id')->on('staff')->restrictOnDelete();

            $table->index(['status', 'placed_at']);
            $table->index(['table_id', 'status']);
            $table->index(['customer_id', 'placed_at']);
            $table->index('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
