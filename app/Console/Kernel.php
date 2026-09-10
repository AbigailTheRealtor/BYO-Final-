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

        $this->scheduleMlsSync($schedule);
    }

    /**
     * Keep MLS-linked listings current with their Stellar source records.
     *
     * TWO ENTRIES, ONE COMMAND, DIFFERENT JOBS
     * ----------------------------------------
     * The SWEEP is the mechanism the platform actually relies on. The RECONCILE
     * pass is the daily floor the owner's contract names as the minimum: it runs
     * once with a raised ceiling so the estate is fully covered even on a day
     * when every sweep hit its limit.
     *
     * WHY THE CADENCE IS SAFE — the short version; `config/mls_sync.php`
     * → `schedule` carries the evidence in full.
     *
     * The sweep interval does not set request volume. `freshness_minutes` does.
     * A sweep SELECTS listings whose window has expired and the service declines
     * the rest on a local timestamp comparison, so running four times an hour
     * rather than once changes how quickly a newly-stale listing is noticed, not
     * how often it is fetched. One sync is one Bridge request. The per-run
     * ceiling bounds the worst case regardless of how many listings exist.
     *
     * SHIPS UNREGISTERED, AND FAILS CLOSED
     * ------------------------------------
     * `mls_sync.enabled` and `mls_sync.schedule.enabled` BOTH default false, so
     * merging this code starts no unattended traffic. Deploying and activating
     * are two decisions; a default of true would collapse them into one, taken
     * by whoever pressed merge at whatever moment the container restarted.
     *
     * The WIRING still ships — nothing here has to be edited to activate. Two
     * environment variables and a restart, after a readiness review.
     *
     * Both `config()` calls pass `false` as their fallback, which is a different
     * safeguard from the env default: it covers `config/mls_sync.php` failing to
     * load at all. A config that did not load is indistinguishable from one that
     * requires nothing, and for a switch governing outbound provider traffic
     * that ambiguity must not resolve to "on".
     *
     * Both gates are checked HERE rather than only inside the command, so a
     * disabled sweep does not appear in `schedule:list` as something that runs.
     * A row in that output saying a command fires every fifteen minutes, when it
     * in fact returns immediately, is a false statement in the one place an
     * operator looks to find out what the server does unattended.
     *
     * The command re-checks `mls_sync.enabled` itself as well. That is not
     * redundant: it can be invoked by hand, and a hand-run must obey the master
     * switch even though it never consulted this method.
     */
    private function scheduleMlsSync(Schedule $schedule): void
    {
        if (! (bool) config('mls_sync.enabled', false)
            || ! (bool) config('mls_sync.schedule.enabled', false)) {
            return;
        }

        // Clamped to a divisor of 60 so the generated cron expression describes
        // an even cadence. A value like 7 would fire at :00,:07…:56 and then
        // wait 4 minutes across the hour boundary — a cadence nobody chose.
        $minutes = (int) config('mls_sync.schedule.sweep_minutes', 15);
        $minutes = in_array($minutes, [1, 2, 3, 4, 5, 6, 10, 12, 15, 20, 30, 60], true) ? $minutes : 15;

        $guard = max(1, (int) config('mls_sync.schedule.overlap_guard_minutes', 10));

        $sweep = $schedule->command('mls:sync-listings')
            // withoutOverlapping, for the same reason offers:expire-pending has
            // it: a slow run must not have a second run start on top of it and
            // double the outbound traffic. The per-listing cache lock inside the
            // service is the finer-grained backstop; this is the coarse one.
            ->withoutOverlapping($guard)
            ->runInBackground();

        $minutes === 60
            ? $sweep->hourly()
            : $sweep->cron('*/' . $minutes . ' * * * *');

        // The daily floor. Raised ceiling, no demand priority — its job is to
        // reach everything, not to serve whoever is looking.
        $schedule->command('mls:sync-listings --reconcile')
            ->dailyAt((string) config('mls_sync.schedule.reconcile_at', '03:20'))
            ->withoutOverlapping($guard)
            ->runInBackground();
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
