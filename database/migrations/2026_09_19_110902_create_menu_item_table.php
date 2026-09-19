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
        Schema::create('menu_item', function (Blueprint $table) {
            $table->increments('item_id');
            $table->unsignedInteger('category_id');
            $table->string('item_name', 100);
            $table->string('description', 500)->nullable();
            $table->string('image_path', 255)->nullable();
            $table->string('destination', 10);
            $table->unsignedTinyInteger('prep_minutes')->default(10);
            $table->boolean('is_available')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('daily_limit')->nullable();
            $table->unsignedSmallInteger('sold_today')->default(0);
            $table->boolean('is_active')->default(true);
            $table->decimal('calories_kcal', 7, 2)->nullable();
            $table->decimal('protein_g', 6, 2)->nullable();
            $table->decimal('carbohydrates_g', 6, 2)->nullable();
            $table->decimal('fat_g', 6, 2)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->nullable();

            $table->foreign('category_id')->references('category_id')->on('menu_category')->restrictOnDelete();

            $table->index(['category_id', 'is_active', 'is_available']);
            $table->index(['is_featured', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_item');
    }
};
