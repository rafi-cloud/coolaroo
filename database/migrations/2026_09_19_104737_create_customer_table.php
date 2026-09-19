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
            $table->string('full_name', 100); // shown to others as first name + last initial (BR41)
            $table->string('phone', 20)->nullable()->unique(); // unique when present (BR52); MySQL allows multiple NULLs
            $table->dateTime('email_verified_at')->nullable(); // NULL = unverified; required before reservation (BR42)
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->dateTime('last_login_at')->nullable();
            $table->dateTime('created_at')->useCurrent(); // 'member since' on trust profile
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