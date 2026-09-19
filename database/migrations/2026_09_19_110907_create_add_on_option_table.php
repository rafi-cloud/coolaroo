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
        Schema::create('add_on_option', function (Blueprint $table) {
            $table->increments('option_id');
            $table->unsignedInteger('group_id');
            $table->string('option_name', 60);
            $table->decimal('price_delta', 8, 2)->default(0);
            $table->boolean('is_available')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);

            $table->foreign('group_id')->references('group_id')->on('add_on_group')->cascadeOnDelete();

            $table->index(['group_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('add_on_option');
    }
};
