<?php

namespace App\Console\Commands;

use App\Services\PaymentService;
use Illuminate\Console\Command;

class ReconcilePaymentsCommand extends Command
{
    protected $signature = 'payments:reconcile';

    protected $description = 'Retrieve pending Stripe sessions and mark verified payments paid';

    public function handle(PaymentService $payments): int
    {
        $result = $payments->reconcilePendingStripePayments();

        $this->info(sprintf(
            'Checked %d pending attempt(s): %d paid, %d expired, %d failed.',
            $result['checked'],
            $result['paid'],
            $result['expired'],
            $result['failed'],
        ));

        return self::SUCCESS;
    }
}
