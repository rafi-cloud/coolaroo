<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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

    public function down(): void {}
};
