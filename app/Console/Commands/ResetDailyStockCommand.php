<?php

namespace App\Console\Commands;

use App\Services\StockService;
use Illuminate\Console\Command;

/**
 * BR11, 07.10. Scheduled daily at opening_time (Australia/Melbourne).
 */
class ResetDailyStockCommand extends Command
{
    protected $signature = 'stock:reset-daily';

    protected $description = 'Reset every menu item daily sold counter (BR11)';

    public function handle(StockService $stock): int
    {
        $stock->resetDailyCounters();

        $this->info('Daily sold counters reset.');

        return self::SUCCESS;
    }
}
