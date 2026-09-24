<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'db:backup {--keep-days= : Override how many days of dumps to keep}';

    protected $description = 'Write a mysqldump of the application database and prune old dumps';

    public function handle(): int
    {
        $connection = config('database.default');

        if ($connection !== 'mysql') {
            $this->warn("Skipped: the default connection is [{$connection}], not mysql.");

            return self::SUCCESS;
        }

        $config = config('database.connections.mysql');
        $directory = storage_path('backups');
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.sprintf(
            '%s-%s.sql',
            $config['database'],
            Carbon::now()->format('Y-m-d-His'),
        );

        $result = Process::timeout(600)
            ->env(['MYSQL_PWD' => (string) $config['password']])
            ->run([
                config('database.mysqldump_path', 'mysqldump'),
                '--host='.$config['host'],
                '--port='.$config['port'],
                '--user='.$config['username'],
                '--single-transaction',
                '--routines',
                '--result-file='.$path,
                $config['database'],
            ]);

        if (! $result->successful()) {
            $this->error('mysqldump failed: '.trim($result->errorOutput()));

            return self::FAILURE;
        }

        $this->info('Wrote '.$path);
        $this->info('Pruned '.$this->prune($directory).' expired dump(s).');

        return self::SUCCESS;
    }

    private function prune(string $directory): int
    {
        $keepDays = (int) ($this->option('keep-days') ?? config('database.backup_keep_days', 14));
        $cutoff = Carbon::now()->subDays($keepDays);
        $pruned = 0;

        foreach (File::files($directory) as $file) {
            if ($file->getExtension() === 'sql' && Carbon::createFromTimestamp($file->getMTime())->lt($cutoff)) {
                File::delete($file->getPathname());
                $pruned++;
            }
        }

        return $pruned;
    }
}
