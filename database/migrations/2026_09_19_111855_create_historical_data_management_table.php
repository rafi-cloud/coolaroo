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
        Schema::create('historical_data_management', function (Blueprint $table) {
            $table->bigIncrements('history_id');
            $table->unsignedBigInteger('log_id')->unique();
            $table->string('entity_name', 50);
            $table->string('record_id', 100);
            $table->text('record_data');
            $table->string('status', 20)->default('archived');
            $table->dateTime('archived_at')->useCurrent();

            $table->foreign('log_id')->references('log_id')->on('audit_log')->restrictOnDelete();

            $table->index(['entity_name', 'record_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historical_data_management');
    }
};
