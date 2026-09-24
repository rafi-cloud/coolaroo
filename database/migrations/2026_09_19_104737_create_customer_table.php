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
        Schema::create('customer', function (Blueprint $table) {
            $table->increments('customer_id');
            $table->string('email', 150)->unique();
            $table->string('password_hash', 255);
            $table->string('full_name', 100);
            $table->string('phone', 20)->nullable()->unique();
            $table->dateTime('email_verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->dateTime('last_login_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer');
    }
};
