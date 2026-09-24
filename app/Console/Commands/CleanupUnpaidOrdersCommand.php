<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

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
