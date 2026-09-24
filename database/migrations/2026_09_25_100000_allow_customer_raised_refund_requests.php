<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FR51 and BR27 previously read "staff only; customers cannot", so a refund
 * row always carried a requesting staff member. A customer may now raise the
 * request from their own order, so exactly one of the two columns is set —
 * enforced in RefundService, as with the visit invariant in 6.4.16.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund', function (Blueprint $table) {
            $table->unsignedInteger('requested_by_staff_id')->nullable()->change();
            $table->unsignedInteger('requested_by_customer_id')->nullable()->after('requested_by_staff_id');

            $table->foreign('requested_by_customer_id')->references('customer_id')->on('customer')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('refund', function (Blueprint $table) {
            $table->dropForeign(['requested_by_customer_id']);
            $table->dropColumn('requested_by_customer_id');
        });
    }
};
