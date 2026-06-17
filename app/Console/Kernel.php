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
        $pollInterval = min(59, max(1, (int) config('ai-order-scan.inbox.poll_interval_minutes', 1)));

        $event = $schedule
            ->command('orders:ai-inbox-poll')
            ->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/ai-inbox-poll.log'));

        if ($pollInterval === 1) {
            $event->everyMinute();

            return;
        }

        $event->cron(sprintf('*/%d * * * *', $pollInterval));
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
