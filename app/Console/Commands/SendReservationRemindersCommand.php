<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

/**
 * FR75, 07.10. Scheduled every fifteen minutes; the mail itself is queued.
 */
class SendReservationRemindersCommand extends Command
{
    protected $signature = 'reservations:send-reminders';

    protected $description = 'Queue reminder emails for bookings inside the reminder window (FR75)';

    public function handle(ReservationService $reservations): int
    {
        $sent = $reservations->sendDueReminders();

        $this->info("Queued {$sent} reservation reminder(s).");

        return self::SUCCESS;
    }
}
