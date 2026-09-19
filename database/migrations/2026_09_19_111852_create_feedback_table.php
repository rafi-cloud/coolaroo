<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->unsignedInteger('order_id')->primary();
            $table->unsignedInteger('customer_id');
            $table->unsignedInteger('replied_by_staff_id')->nullable();
            $table->unsignedTinyInteger('food_rating');
            $table->unsignedTinyInteger('service_rating');
            $table->string('comment', 1000)->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->string('hidden_reason', 255)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->string('admin_reply', 1000)->nullable();
            $table->dateTime('replied_at')->nullable();
            $table->dateTime('submitted_at')->useCurrent();

            $table->foreign('order_id')->references('order_id')->on('orders')->cascadeOnDelete();
            $table->foreign('customer_id')->references('customer_id')->on('customer')->restrictOnDelete();
            $table->foreign('replied_by_staff_id')->references('staff_id')->on('staff')->restrictOnDelete();

            $table->index(['is_hidden', 'is_featured']);
            $table->index('submitted_at');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE feedback ADD CONSTRAINT feedback_rating_check CHECK (food_rating BETWEEN 1 AND 5 AND service_rating BETWEEN 1 AND 5)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
