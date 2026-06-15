<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    // protected $commands = [\App\Console\Commands\AutoBid::class,];
    // protected $commands = [\App\Console\Commands\SellerAutocounter::class, \App\Console\Commands\BuyerAutocounter::class];

    protected $commands = [
        \App\Console\Commands\MlsParseDebug::class,
        \App\Console\Commands\MlsImportAuditCommand::class,
        \App\Console\Commands\BackfillLocationSnapshots::class,
    ];
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        // $schedule->command('autoBid')->everyMinute();
        // $schedule->command('expirationDate')->everyMinute();
        // $schedule->command('autoBid')->everyThirtyMinutes();

        // $schedule->command('seller:autocounter')->everyMinute();
        // $schedule->command('buyer:autocounter')->everyMinute();

        $schedule->command('offers:expire-pending')->everyMinute();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
