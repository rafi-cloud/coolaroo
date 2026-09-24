<?php

namespace Tests\Feature\Console;

use App\Mail\ReservationReminderMail;
use App\Models\Reservation;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use ReflectionClass;
use SplFileInfo;
use Tests\TestCase;

class QueueReliabilityTest extends TestCase
{
    public function test_every_broadcast_event_carries_retry_settings_and_the_broadcasts_queue(): void
    {
        foreach (File::files(app_path('Events')) as $file) {
            $class = 'App\\Events\\'.$file->getBasename('.php');

            if (! (new ReflectionClass($class))->implementsInterface(ShouldBroadcast::class)) {
                continue;
            }

            $event = (new ReflectionClass($class))->newInstanceWithoutConstructor();

            $this->assertSame(3, $event->tries, "{$class} does not set a retry count.");
            $this->assertNotEmpty($event->backoff, "{$class} does not set a backoff.");
            $this->assertSame('broadcasts', $event->broadcastQueue(),
                "07.9: {$class} would queue outside the broadcasts queue, behind ordinary jobs.");
        }
    }

    public function test_queued_mail_retries_three_times_with_a_growing_backoff(): void
    {
        $mailable = new ReservationReminderMail(new Reservation);

        $this->assertSame(3, $mailable->tries);
        $this->assertSame([60, 300, 900], $mailable->backoff);
        $this->assertSame('mail', $mailable->queue);
    }

    public function test_the_backup_command_writes_a_dump_and_prunes_expired_ones(): void
    {
        Process::fake();
        config(['database.default' => 'mysql']);

        $directory = storage_path('backups');
        File::ensureDirectoryExists($directory);

        $stale = $directory.DIRECTORY_SEPARATOR.'stale.sql';
        $fresh = $directory.DIRECTORY_SEPARATOR.'fresh.sql';
        File::put($stale, '-- old');
        File::put($fresh, '-- new');
        touch($stale, now()->subDays(20)->timestamp);

        $this->artisan('db:backup')->assertSuccessful();

        $this->assertFileDoesNotExist($stale);
        $this->assertFileExists($fresh);

        Process::assertRan(fn ($process) => str_contains(
            is_array($process->command) ? implode(' ', $process->command) : (string) $process->command,
            'mysqldump',
        ));

        File::delete($fresh);
    }

    public function test_the_backup_command_skips_a_non_mysql_connection(): void
    {
        Process::fake();

        $this->artisan('db:backup')->assertSuccessful();

        Process::assertNothingRan();
    }

    public function test_housekeeping_commands_are_scheduled(): void
    {
        $scheduled = collect(app(Schedule::class)->events())
            ->map(fn ($event) => $event->command)
            ->implode(' ');

        $this->assertStringContainsString('db:backup', $scheduled);
        $this->assertStringContainsString('queue:prune-failed', $scheduled);
    }

    protected function tearDown(): void
    {
        $directory = storage_path('backups');

        if (File::isDirectory($directory)) {
            collect(File::files($directory))
                ->each(fn (SplFileInfo $file) => File::delete($file->getPathname()));
        }

        parent::tearDown();
    }
}
