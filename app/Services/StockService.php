<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * FR30, BR09-BR14, BR54.
 */
class StockService
{
    private function bufferMultiplier(): int
    {
        return (int) (Setting::find('qr_stock_buffer_multiplier')?->setting_value ?? 5);
    }

    public function hasQrStock(MenuItem $item, int $quantity): bool
    {
        if ($item->daily_limit === null) {
            return true;
        }

        return ($item->daily_limit - $item->sold_today) >= $this->bufferMultiplier() * $quantity;
    }

    public function hasExactStock(MenuItem $item, int $quantity): bool
    {
        if ($item->daily_limit === null) {
            return true;
        }

        return ($item->daily_limit - $item->sold_today) >= $quantity;
    }

    public function isSoldOutForQr(MenuItem $item): bool
    {
        if ($item->daily_limit === null) {
            return false;
        }

        return ($item->daily_limit - $item->sold_today) < $this->bufferMultiplier();
    }

    /**
     * Atomically increments sold_today only if it stays within daily_limit.
     * Returns false (not an exception — BR54) if it would be exceeded.
     */
    public function deduct(MenuItem $item, int $quantity): bool
    {
        $affected = DB::table('menu_item')
            ->where('item_id', $item->item_id)
            ->where(function ($query) use ($quantity) {
                $query->whereNull('daily_limit')
                    ->orWhereRaw('sold_today + ? <= daily_limit', [$quantity]);
            })
            ->increment('sold_today', $quantity);

        return $affected > 0;
    }

    public function returnToStock(MenuItem $item, int $quantity): void
    {
        DB::table('menu_item')
            ->where('item_id', $item->item_id)
            ->where('sold_today', '>=', $quantity)
            ->decrement('sold_today', $quantity);
    }

    public function resetDailyCounters(): void
    {
        DB::table('menu_item')->update(['sold_today' => 0]);
    }
}
