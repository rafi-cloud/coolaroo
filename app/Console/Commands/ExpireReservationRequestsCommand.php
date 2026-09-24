<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

class ExpireReservationRequestsCommand extends Command
{
    protected $signature = 'reservations:expire-requests';

    protected $description = 'Expire reservation requests left unreviewed near their booking time';

    public function handle(ReservationService $reservations): int
    {
        $expired = $reservations->expireStaleRequests();

        $this->info("Expired {$expired} unreviewed request(s).");

        return self::SUCCESS;
    }
}
