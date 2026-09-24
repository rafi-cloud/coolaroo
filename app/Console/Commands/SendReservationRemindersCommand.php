<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

class SendReservationRemindersCommand extends Command
{
    protected $signature = 'reservations:send-reminders';

    protected $description = 'Queue reminder emails for bookings inside the reminder window';

    public function handle(ReservationService $reservations): int
    {
        $sent = $reservations->sendDueReminders();

        $this->info("Queued {$sent} reservation reminder(s).");

        return self::SUCCESS;
    }
}
