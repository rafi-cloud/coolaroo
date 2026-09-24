<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lines of cancelled and served orders were left at pending or preparing:
 * cancelling an order never touched them, and DemoSeeder's week-old served
 * orders were built with the default line status. BR30's "orders ahead" counted
 * every one of them, which is what put a three-hour ETA on a burger.
 */
return new class extends Migration
{
    private const UNFINISHED = ['pending', 'preparing'];

    public function up(): void
    {
        DB::table('order_item')
            ->whereIn('status', self::UNFINISHED)
            ->whereIn('order_id', DB::table('orders')->where('status', 'cancelled')->pluck('order_id'))
            ->update(['status' => 'cancelled']);

        DB::table('order_item')
            ->whereIn('status', [...self::UNFINISHED, 'ready'])
            ->whereIn('order_id', DB::table('orders')->where('status', 'served')->pluck('order_id'))
            ->update(['status' => 'served']);
    }

    public function down(): void
    {
        //
    }
};
