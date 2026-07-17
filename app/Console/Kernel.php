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
        \App\Console\Commands\ImportBridgeProperties::class,
        \App\Console\Commands\AuditBridgeFields::class,
        \App\Console\Commands\ValidatePhase0Fields::class,
        \App\Console\Commands\BackfillNativeColumns::class,
        \App\Console\Commands\GeocodeSelleryLandlordListings::class,
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

        // BLK-04: run every minute, but never let two runs overlap. withoutOverlapping()
        // takes an atomic cache lock so a slow run (or an accidentally duplicated
        // scheduler process) cannot double-process the same offers. The 5-minute TTL
        // auto-releases the lock if a run is killed mid-flight. Request-time expiry
        // (BLK-06) remains the safety net whenever this scheduler is delayed or down.
        $schedule->command('offers:expire-pending')
            ->everyMinute()
            ->withoutOverlapping(5);
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
