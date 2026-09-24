<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

class SuggestNoShowsCommand extends Command
{
    protected $signature = 'reservations:suggest-no-shows';

    protected $description = 'Alert the floor to confirmed bookings now past their grace period';

    public function handle(ReservationService $reservations): int
    {
        $suggested = $reservations->suggestNoShows();

        $this->info("Suggested {$suggested} no-show(s) for staff confirmation.");

        return self::SUCCESS;
    }
}
