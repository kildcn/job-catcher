<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\PreCacheJobAnalytics::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Run the pre-cache job daily at midnight for common searches
        $schedule->command('jobs:precache --common-searches --max-jobs=2000')
                 ->dailyAt('00:00')
                 ->withoutOverlapping()
                 ->runInBackground();

        // Weekly full analysis with more jobs for popular locations
        $schedule->command('jobs:precache --common-searches --max-jobs=5000')
                 ->weekly()
                 ->withoutOverlapping()
                 ->runInBackground();

        // Monthly deeper analysis for all jobs in the database for the past 24 months
        $schedule->command('jobs:precache --months=24 --max-jobs=10000')
                 ->monthly()
                 ->withoutOverlapping()
                 ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
