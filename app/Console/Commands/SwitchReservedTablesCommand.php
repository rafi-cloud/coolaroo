<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

/**
 * BR04, FR71, 07.10. Scheduled every minute.
 */
class SwitchReservedTablesCommand extends Command
{
    protected $signature = 'reservations:switch-reserved';

    protected $description = 'Switch assigned tables to reserved at T-30 and raise floor alerts (BR04, FR71)';

    public function handle(ReservationService $reservations): int
    {
        $result = $reservations->switchReservedTables();

        $this->info("Switched {$result['switched']} table(s) to reserved; raised {$result['alerts']} alert(s).");

        return self::SUCCESS;
    }
}
