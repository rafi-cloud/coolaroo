<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

/**
 * Scheduled daily at closing_time. Orders carry no timed
 * expiry of their own — this is the only job that cancels them.
 */
class CleanupUnpaidOrdersCommand extends Command
{
    protected $signature = 'orders:cleanup-unpaid';

    protected $description = 'Expire open Stripe sessions and cancel orders still unpaid at closing time';

    public function handle(OrderService $orders): int
    {
        $result = $orders->cancelUnpaidAtClose();

        $this->info(sprintf(
            'Reviewed %d unpaid order(s): %d cancelled, %d found paid, %d failed.',
            $result['pending'],
            $result['cancelled'],
            $result['paid'],
            $result['failed'],
        ));

        return self::SUCCESS;
    }
}
