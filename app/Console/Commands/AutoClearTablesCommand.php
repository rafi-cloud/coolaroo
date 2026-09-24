<?php

namespace App\Console\Commands;

use App\Services\SettingService;
use App\Services\TableStatusService;
use Illuminate\Console\Command;

class AutoClearTablesCommand extends Command
{
    protected $signature = 'tables:auto-clear';

    protected $description = 'Return idle occupied tables to available';

    public function handle(TableStatusService $tables, SettingService $settings): int
    {
        $idleMinutes = $settings->getInt('table_idle_autoclear_minutes', 45);

        $cleared = $tables->autoClearIdleTables($idleMinutes);

        $this->info("Cleared {$cleared} idle table(s) after {$idleMinutes} minutes.");

        return self::SUCCESS;
    }
}
