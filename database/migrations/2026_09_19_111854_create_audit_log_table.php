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
        Schema::create('audit_log', function (Blueprint $table) {
            $table->bigIncrements('log_id');
            $table->unsignedInteger('staff_id')->nullable();
            $table->unsignedInteger('customer_id')->nullable();
            $table->string('action_type', 50);
            $table->string('entity_name', 50);
            $table->unsignedInteger('entity_id')->nullable();
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->dateTime('logged_at')->useCurrent();

            $table->foreign('staff_id')->references('staff_id')->on('staff')->restrictOnDelete();
            $table->foreign('customer_id')->references('customer_id')->on('customer')->restrictOnDelete();

            $table->index(['entity_name', 'entity_id']);
            $table->index(['action_type', 'logged_at']);
            $table->index(['staff_id', 'logged_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
