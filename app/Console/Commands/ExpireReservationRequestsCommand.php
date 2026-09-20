<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

/**
 * BR37, 07.10. Scheduled every five minutes. Each expiry emails the customer.
 */
class ExpireReservationRequestsCommand extends Command
{
    protected $signature = 'reservations:expire-requests';

    protected $description = 'Expire reservation requests left unreviewed near their booking time (BR37)';

    public function handle(ReservationService $reservations): int
    {
        $expired = $reservations->expireStaleRequests();

        $this->info("Expired {$expired} unreviewed request(s).");

        return self::SUCCESS;
    }
}
