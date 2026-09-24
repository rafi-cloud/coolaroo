<?php

use App\Models\Setting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$tradingTime = function (string $key, string $fallback): string {
    try {
        $value = Setting::find($key)?->setting_value;
    } catch (Throwable) {
        $value = null;
    }

    return $value !== null ? substr($value, 0, 5) : $fallback;
};

Schedule::command('stock:reset-daily')->dailyAt($tradingTime('opening_time', '11:00'));
Schedule::command('payments:reconcile')->everyTwoMinutes()->withoutOverlapping();
Schedule::command('orders:cleanup-unpaid')->dailyAt($tradingTime('closing_time', '23:00'))->withoutOverlapping();
Schedule::command('tables:auto-clear')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('reservations:switch-reserved')->everyMinute()->withoutOverlapping();
Schedule::command('reservations:expire-requests')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('reservations:send-reminders')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('reservations:suggest-no-shows')->everyMinute()->withoutOverlapping();

Schedule::command('db:backup')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=336')->daily();
