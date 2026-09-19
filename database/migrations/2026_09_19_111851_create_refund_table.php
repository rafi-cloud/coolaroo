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
        Schema::create('refund', function (Blueprint $table) {
            $table->increments('refund_id');
            $table->unsignedInteger('order_id');
            $table->unsignedInteger('order_item_id')->nullable();
            $table->unsignedInteger('payment_id')->nullable();
            $table->unsignedInteger('requested_by_staff_id');
            $table->unsignedInteger('processed_by_staff_id')->nullable();
            $table->string('method', 10);
            $table->unsignedTinyInteger('quantity')->default(0);
            $table->decimal('amount', 8, 2);
            $table->string('reason', 255);
            $table->string('status', 15)->default('requested');
            $table->boolean('return_to_stock')->default(false);
            $table->string('provider_refund_id', 100)->nullable()->unique();
            $table->string('manual_reference', 100)->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->dateTime('requested_at')->useCurrent();
            $table->dateTime('completed_at')->nullable();

            $table->foreign('order_id')->references('order_id')->on('orders')->restrictOnDelete();
            $table->foreign('order_item_id')->references('order_item_id')->on('order_item')->restrictOnDelete();
            $table->foreign('payment_id')->references('payment_id')->on('payment')->restrictOnDelete();
            $table->foreign('requested_by_staff_id')->references('staff_id')->on('staff')->restrictOnDelete();
            $table->foreign('processed_by_staff_id')->references('staff_id')->on('staff')->restrictOnDelete();

            $table->index(['status', 'requested_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refund');
    }
};
