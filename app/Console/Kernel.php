<?php

namespace App\Console;

use App\Console\Commands\Billing\RunBillingLifecycleCommand;
use App\Console\Commands\TenantsCleanup;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        TenantsCleanup::class,
        RunBillingLifecycleCommand::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('billing:run-lifecycle')->dailyAt('01:45');

        $schedule->command('tenants:cleanup --grace-days=7')->dailyAt('02:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
