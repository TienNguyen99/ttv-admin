<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        if (!config('internal_sync.enabled')) {
            return;
        }

        $timezone = config('internal_sync.timezone', 'Asia/Ho_Chi_Minh');

        $schedule->command('internal:sync-google-sheets operational')
            ->everyTwoMinutes()
            ->timezone($timezone)
            ->withoutOverlapping(2);

        $schedule->command('internal:sync-google-sheets reference')
            ->everyThirtyMinutes()
            ->timezone($timezone)
            ->withoutOverlapping(30);
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
