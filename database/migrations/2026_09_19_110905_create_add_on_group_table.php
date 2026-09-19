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
        Schema::create('add_on_group', function (Blueprint $table) {
            $table->increments('group_id');
            $table->unsignedInteger('item_id');
            $table->string('group_name', 60);
            $table->boolean('is_required')->default(false);
            $table->unsignedTinyInteger('min_select')->default(0);
            $table->unsignedTinyInteger('max_select')->default(1);
            $table->unsignedSmallInteger('display_order')->default(0);

            $table->foreign('item_id')->references('item_id')->on('menu_item')->cascadeOnDelete();

            $table->index(['item_id', 'display_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('add_on_group');
    }
};
