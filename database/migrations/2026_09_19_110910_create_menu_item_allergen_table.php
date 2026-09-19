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
        Schema::create('menu_item_allergen', function (Blueprint $table) {
            $table->unsignedInteger('item_id');
            $table->unsignedSmallInteger('allergen_id');

            $table->primary(['item_id', 'allergen_id']);

            $table->foreign('item_id')->references('item_id')->on('menu_item')->cascadeOnDelete();
            $table->foreign('allergen_id')->references('allergen_id')->on('allergen')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_item_allergen');
    }
};
