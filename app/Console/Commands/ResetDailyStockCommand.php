<?php

namespace App\Console\Commands;

use App\Services\StockService;
use Illuminate\Console\Command;

/**
 * Scheduled daily at opening_time (Australia/Melbourne).
 */
class ResetDailyStockCommand extends Command
{
    protected $signature = 'stock:reset-daily';

    protected $description = 'Reset every menu item daily sold counter';

    public function handle(StockService $stock): int
    {
        $stock->resetDailyCounters();

        $this->info('Daily sold counters reset.');

        return self::SUCCESS;
    }
}
