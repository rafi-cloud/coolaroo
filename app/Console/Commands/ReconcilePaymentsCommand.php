<?php

namespace App\Console\Commands;

use App\Services\PaymentService;
use Illuminate\Console\Command;

/**
 * Scheduled every two minutes. Stripe runs in test mode
 * with no webhooks, so this job is what catches a payment whose customer
 * never came back to the return URL.
 */
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
