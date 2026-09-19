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
        Schema::create('restaurant_table', function (Blueprint $table) {
            $table->increments('table_id');
            $table->string('table_number', 10)->unique(); // printed number, e.g. T12
            $table->unsignedTinyInteger('seat_capacity'); // must be > 0 (BR33); validated in FormRequest
            $table->string('section', 20)->nullable(); // floor area label, e.g. Dining, Bar, Outdoor
            $table->string('qr_token', 64)->unique(); // signed QR URL token (NFR04); regenerating invalidates old QR
            $table->string('status', 10)->default('available'); // enum available/reserved/occupied
            $table->dateTime('status_changed_at')->nullable();
            $table->boolean('is_active')->default(true); // false = QR shows 'table unavailable' (BR07)
            $table->dateTime('created_at')->useCurrent();

            $table->index(['status', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_table');
    }
};
