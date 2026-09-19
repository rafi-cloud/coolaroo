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
        Schema::create('menu_item_size', function (Blueprint $table) {
            $table->increments('size_id');
            $table->unsignedInteger('item_id');
            $table->string('size_name', 40)->default('Regular');
            $table->decimal('price', 8, 2);
            $table->decimal('sale_price', 8, 2)->nullable();
            $table->dateTime('sale_starts_at')->nullable();
            $table->dateTime('sale_ends_at')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->foreign('item_id')->references('item_id')->on('menu_item')->cascadeOnDelete();

            $table->unique(['item_id', 'size_name']);
            $table->index(['item_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_item_size');
    }
};
