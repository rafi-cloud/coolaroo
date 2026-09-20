<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

/**
 * BR39, FR70, 07.10. Scheduled every minute. The job only suggests — the
 * no-show itself is confirmed by staff.
 */
class SuggestNoShowsCommand extends Command
{
    protected $signature = 'reservations:suggest-no-shows';

    protected $description = 'Alert the floor to confirmed bookings now past their grace period (BR39, FR70)';

    public function handle(ReservationService $reservations): int
    {
        $suggested = $reservations->suggestNoShows();

        $this->info("Suggested {$suggested} no-show(s) for staff confirmation.");

        return self::SUCCESS;
    }
}
