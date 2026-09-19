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
        Schema::create('staff', function (Blueprint $table) {
            $table->increments('staff_id');
            $table->unsignedTinyInteger('role_id');
            $table->string('email', 150)->unique();
            $table->string('password_hash', 255);
            $table->string('full_name', 100);
            $table->string('phone', 20)->nullable();
            $table->boolean('is_active')->default(true); // false = deactivated (FR08), login refused
            $table->rememberToken();
            $table->dateTime('last_login_at')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->foreign('role_id')->references('role_id')->on('role')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};