<?php

namespace App\Services;

use App\Events\WaiterCalled;
use App\Models\RestaurantTable;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class WaiterCallService
{
    public function call(RestaurantTable $table): bool
    {
        $placed = Cache::add(
            $this->cacheKey($table),
            true,
            $this->cooldownSeconds(),
        );

        if ($placed) {
            event(new WaiterCalled($table));
        }

        return $placed;
    }

    private function cacheKey(RestaurantTable $table): string
    {
        return 'waiter-call:'.$table->table_id;
    }

    private function cooldownSeconds(): int
    {
        return (int) (Setting::find('call_waiter_cooldown_seconds')?->setting_value ?? 120);
    }
}
