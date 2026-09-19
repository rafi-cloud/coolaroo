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
        Schema::create('order_item', function (Blueprint $table) {
            $table->increments('order_item_id');
            $table->unsignedInteger('order_id');
            $table->unsignedSmallInteger('line_no');
            $table->unsignedInteger('item_id');
            $table->unsignedInteger('size_id');
            $table->string('item_name', 100);
            $table->string('size_name', 40);
            $table->string('destination', 10);
            $table->unsignedTinyInteger('quantity');
            $table->decimal('original_unit_price', 8, 2);
            $table->decimal('unit_price', 8, 2);
            $table->json('selected_options')->nullable();
            $table->string('special_request', 200)->nullable();
            $table->decimal('line_total', 8, 2);
            $table->string('status', 15)->default('pending');
            $table->dateTime('prepared_at')->nullable();
            $table->unsignedTinyInteger('refunded_qty')->default(0);

            $table->foreign('order_id')->references('order_id')->on('orders')->cascadeOnDelete();
            $table->foreign('item_id')->references('item_id')->on('menu_item')->restrictOnDelete();
            $table->foreign('size_id')->references('size_id')->on('menu_item_size')->restrictOnDelete();

            $table->unique(['order_id', 'line_no']);
            $table->index(['destination', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_item');
    }
};
