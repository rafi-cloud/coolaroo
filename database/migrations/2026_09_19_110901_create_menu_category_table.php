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
        Schema::create('menu_category', function (Blueprint $table) {
            $table->increments('category_id');
            $table->unsignedInteger('parent_category_id')->nullable();
            $table->string('category_name', 60);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->foreign('parent_category_id')->references('category_id')->on('menu_category')->restrictOnDelete();

            $table->index(['parent_category_id', 'display_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_category');
    }
};
