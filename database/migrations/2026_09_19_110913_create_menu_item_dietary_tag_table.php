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
        Schema::create('menu_item_dietary_tag', function (Blueprint $table) {
            $table->unsignedInteger('item_id');
            $table->unsignedSmallInteger('dietary_tag_id');

            $table->primary(['item_id', 'dietary_tag_id']);

            $table->foreign('item_id')->references('item_id')->on('menu_item')->cascadeOnDelete();
            $table->foreign('dietary_tag_id')->references('dietary_tag_id')->on('dietary_tag')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_item_dietary_tag');
    }
};
