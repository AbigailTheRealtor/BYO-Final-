<?php

namespace Tests\Feature\ListingImport;

use App\Models\BridgeProperty;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter as Meta;
use App\Services\ListingImport\QuickImport\MlsQuickImportService;
use App\Services\ListingImport\Sync\MlsListingSyncService;
use App\Services\ListingImport\Sync\MlsStaleAccessRefresher;
use App\Services\ListingImport\Sync\MlsSyncDemandQueue;
use App\Services\ListingImport\Sync\MlsSyncOutcome;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ACTIVATION IS A SEPARATE DECISION FROM DEPLOYMENT.
 *
 * Merging this code must not, by itself, start unattended traffic to a
 * third-party provider. Deploying and activating are two acts with two
 * different reviews, and a default of `true` collapses them into one — the
 * deploy becomes the activation, taken by whoever pressed merge, at whatever
 * moment the container happened to restart.
 *
 * This file is the proof of that property, and it is deliberately paranoid in
 * two directions:
 *
 *   · the ABSENT variable — an environment that supplies nothing;
 *   · the ABSENT CONFIG — `config/mls_sync.php` failing to load at all, which
 *     is a different failure and one where a `config(..., true)` fallback would
 *     silently re-enable everything the env default just turned off.
 *
 * The second is the one that actually bit: every gate read its config with
 * `true` as the fallback, so a config file that did not load read as ENABLED.
 *
 * Nothing here asserts the feature is broken when off. It asserts that when off
 * it is SILENT — no Bridge request, from any entry point.
 */
class MlsSyncActivationSafetyTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'ACTIVATION-KEY';
    private const MLS = 'ACTIVATION-MLS';

    private int $sourceRequests = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(function () {
            $this->sourceRequests++;

            return Http::response(['value' => [$this->raw()]], 200);
        });

        // Credentials and the import surfaces are present and working. The ONLY
        // thing under examination here is the activation posture — otherwise a
        // test could pass because the lookup was broken rather than because the
        // gate held.
        config([
            'bridge.dataset'                         => 'test-dataset',
            'bridge.token'                           => 'test-token',
            'mls_direct_import.prefill_enabled'      => true,
            'mls_direct_import.quick_import_enabled' => true,
            'mls_direct_import.prefill_roles'        => ['seller', 'landlord'],
            'mls_media.enabled'                      => true,
            'mls_media.license_acknowledged'         => true,
            'mls_media.roles'                        => ['seller', 'landlord'],
        ]);

        app(MlsSyncDemandQueue::class)->flush();
    }

    // =====================================================================
    // Harness
    // =====================================================================

    private function raw(array $overrides = []): array
    {
        return array_merge([
            'ListingKey'                     => self::KEY,
            'ListingId'                      => self::MLS,
            'StandardStatus'                 => 'Active',
            'MlsStatus'                      => 'Active',
            'PropertyType'                   => 'Residential',
            'UnparsedAddress'                => '1 Activation Way',
            'City'                           => 'ST PETERSBURG',
            'StateOrProvince'                => 'FL',
            'PostalCode'                     => '33710',
            'ListPrice'                      => 200000,
            'BedroomsTotal'                  => 3,
            'ModificationTimestamp'          => '2026-09-08T10:00:00.000Z',
            'IDXParticipationYN'             => true,
            'InternetEntireListingDisplayYN' => true,
            'InternetAddressDisplayYN'       => true,
            'Media'                          => [],
        ], $overrides);
    }

    /**
     * An MLS-linked, stale listing.
     *
     * Built with sync temporarily enabled so the import itself works, then the
     * gates are restored by the caller. Its staleness is written directly, so
     * every test below starts from a listing that WOULD be synced if anything
     * were allowed to.
     */
    private function staleImportedListing(): SellerAgentAuction
    {
        config(['mls_sync.enabled' => true, 'mls_sync.roles' => ['seller', 'landlord']]);

        $raw = $this->raw();

        BridgeProperty::where('listing_key', $raw['ListingKey'])->delete();
        BridgeProperty::create([
            'listing_key'            => $raw['ListingKey'],
            'listing_id'             => $raw['ListingId'],
            'standard_status'        => $raw['StandardStatus'],
            'mls_status'             => $raw['MlsStatus'],
            'property_type'          => $raw['PropertyType'],
            'unparsed_address'       => $raw['UnparsedAddress'],
            'city'                   => $raw['City'],
            'state_or_province'      => $raw['StateOrProvince'],
            'postal_code'            => $raw['PostalCode'],
            'list_price'             => $raw['ListPrice'],
            'bedrooms_total'         => $raw['BedroomsTotal'],
            'modification_timestamp' => '2026-01-01T00:00:00.000Z',
            'raw_json'               => json_encode($raw),
            'imported_at'            => now(),
        ]);

        $result = app(MlsQuickImportService::class)->lookup(self::MLS, 'seller');
        $this->assertTrue($result->isFound(), 'Harness: the fixture import failed');

        $listing = app(Meta::class)->materialise('seller', User::factory()->create()->id, $result);

        $listing->saveMeta(Meta::META_SYNCED_AT, now()->subDays(30)->toIso8601String());
        $listing->is_draft    = false;
        $listing->is_approved = true;
        $listing->save();

        return $listing;
    }

    /** Restore the shipped, un-overridden config values for the three gates. */
    private function applyShippedDefaults(): void
    {
        $shipped = require base_path('config/mls_sync.php');

        config(['mls_sync' => $shipped]);
    }

    /** Every unattended and on-access entry point, exercised once. */
    private function exerciseEveryEntryPoint(SellerAgentAuction $listing): void
    {
        $this->artisan('mls:sync-listings')->assertSuccessful();
        $this->artisan('mls:sync-listings', ['--reconcile' => true])->assertSuccessful();
        $this->artisan('mls:sync-listings', ['--role' => 'seller', '--force' => true])->assertSuccessful();

        app(MlsListingSyncService::class)->sync($listing->fresh(), 'seller');
        app(MlsListingSyncService::class)->sync($listing->fresh(), 'seller', true);

        app(MlsStaleAccessRefresher::class)->onAccess($listing->fresh(), 'seller', true);
        app(MlsStaleAccessRefresher::class)->onAccess($listing->fresh(), 'seller', false);

        $this->get(route('offer.listing.seller.view', ['id' => $listing->id]))->assertSuccessful();
    }

    private function scheduledMlsCommands(): \Illuminate\Support\Collection
    {
        $schedule = new Schedule();
        $kernel   = app(\Illuminate\Contracts\Console\Kernel::class);

        $method = new \ReflectionMethod($kernel, 'schedule');
        $method->setAccessible(true);
        $method->invoke($kernel, $schedule);

        return collect($schedule->events())
            ->map(fn ($e) => (string) $e->command)
            ->filter(fn ($c) => str_contains($c, 'mls:sync-listings'))
            ->values();
    }

    // =====================================================================
    // 1. The shipped defaults are off
    // =====================================================================

    /**
     * @test
     *
     * Read from the file, not from the test environment, so a `.env.testing`
     * value cannot make this pass. This is the "environment supplies nothing"
     * case — a fresh container with no MLS_SYNC_* variables at all.
     */
    public function the_shipped_configuration_ships_all_three_activation_gates_off(): void
    {
        $shipped = require base_path('config/mls_sync.php');

        $this->assertFalse($shipped['enabled'], 'MLS sync must not activate itself on deploy');
        $this->assertFalse($shipped['lazy_refresh_enabled'], 'On-access refresh must not activate itself on deploy');
        $this->assertFalse($shipped['schedule']['enabled'], 'The unattended sweep must not activate itself on deploy');
    }

    /**
     * @test
     *
     * The cadence and the ceilings are NOT gates and must keep their values, so
     * that activation is one environment change rather than an archaeology
     * exercise in what the numbers were supposed to be.
     */
    public function the_shipped_configuration_still_carries_the_reviewed_cadence(): void
    {
        $shipped = require base_path('config/mls_sync.php');

        $this->assertSame(60, $shipped['freshness_minutes']);
        $this->assertSame(1440, $shipped['terminal_freshness_minutes']);
        $this->assertSame(30, $shipped['retry_after_minutes']);
        $this->assertSame(100, $shipped['batch_limit']);
        $this->assertSame(500, $shipped['reconcile_batch_limit']);
        $this->assertSame(15, $shipped['schedule']['sweep_minutes']);
        $this->assertSame('03:20', $shipped['schedule']['reconcile_at']);
        $this->assertSame(['seller', 'landlord'], $shipped['roles']);
    }

    // =====================================================================
    // 2. Absent activation => no source request, from any entry point
    // =====================================================================

    /**
     * @test
     *
     * THE HEADLINE ASSERTION. Every unattended and on-access path, exercised
     * against a stale MLS-linked listing, under the shipped defaults. Zero
     * Bridge requests.
     */
    public function under_the_shipped_defaults_nothing_reaches_bridge(): void
    {
        $listing = $this->staleImportedListing();

        $this->applyShippedDefaults();

        $before = $this->sourceRequests;

        $this->exerciseEveryEntryPoint($listing);

        $this->assertSame(
            $before,
            $this->sourceRequests,
            'Deploying this code with no MLS_SYNC_* environment variables started outbound Bridge traffic'
        );
    }

    /**
     * @test
     *
     * The other absence, and the one that actually bit. If `config/mls_sync.php`
     * fails to load, every `config('mls_sync.…')` returns its FALLBACK — and
     * every gate used to pass `true` there, so a config that did not load read
     * as fully enabled.
     */
    public function a_config_that_failed_to_load_entirely_reads_as_disabled(): void
    {
        $listing = $this->staleImportedListing();

        // Exactly what a failed load looks like to config(): the key is gone.
        config(['mls_sync' => null]);

        $before = $this->sourceRequests;

        $this->exerciseEveryEntryPoint($listing);

        $this->assertSame(
            $before,
            $this->sourceRequests,
            'A config file that did not load must read as OFF, never as on'
        );

        $this->assertSame(
            MlsSyncOutcome::DISABLED,
            app(MlsListingSyncService::class)->sync($listing->fresh(), 'seller')->status
        );
    }

    /** @test */
    public function under_the_shipped_defaults_a_listing_is_never_modified(): void
    {
        $listing = $this->staleImportedListing();
        $before  = $listing->fresh()->get->toArray();

        $this->applyShippedDefaults();

        $this->exerciseEveryEntryPoint($listing);

        $this->assertSame($before, $listing->fresh()->get->toArray(), 'A disabled sync wrote to the listing');
    }

    // =====================================================================
    // 3. Master gate off => the scheduled command is silent
    // =====================================================================

    /**
     * @test
     *
     * Even invoked directly — as a hand-run, or by a scheduler entry somebody
     * added elsewhere — the command obeys the master switch. It reports success
     * rather than failing, because "sync is switched off" is a correct state,
     * not an error.
     */
    public function with_the_master_gate_off_the_scheduled_command_sends_nothing(): void
    {
        $listing = $this->staleImportedListing();

        config([
            'mls_sync.enabled'          => false,
            'mls_sync.schedule.enabled' => true,
        ]);

        $before = $this->sourceRequests;

        // expectsOutput() — the exact-line matcher. `expectsOutputToContain()`
        // arrived in Laravel 9 and this application is on 8, and
        // `Artisan::output()` comes back empty under this harness.
        //
        // A disabled run must SAY so. Reporting a silent success is how an
        // operator concludes the sweep ran and found nothing to do.
        $this->artisan('mls:sync-listings')
            ->expectsOutput('MLS sync is disabled (mls_sync.enabled). Nothing was done.')
            ->assertSuccessful();

        $this->artisan('mls:sync-listings', ['--reconcile' => true])->assertSuccessful();
        $this->artisan('mls:sync-listings', ['--force' => true])->assertSuccessful();

        $this->assertSame($before, $this->sourceRequests);
    }

    /** @test */
    public function the_master_gate_off_also_removes_the_schedule_entries(): void
    {
        config([
            'mls_sync.enabled'          => false,
            'mls_sync.schedule.enabled' => true,
        ]);

        $this->assertCount(0, $this->scheduledMlsCommands());
    }

    // =====================================================================
    // 4. Schedule gate off => no unattended sweep, owner path still available
    // =====================================================================

    /**
     * @test
     *
     * With the schedule gate off the sweep is not registered at all, so the
     * scheduler cannot invoke it and no unattended request is possible.
     */
    public function with_the_schedule_gate_off_no_sweep_is_registered(): void
    {
        config([
            'mls_sync.enabled'          => true,
            'mls_sync.schedule.enabled' => false,
        ]);

        $this->assertCount(
            0,
            $this->scheduledMlsCommands(),
            'A disabled sweep must not appear in schedule:list as something that runs'
        );
    }

    /**
     * @test
     *
     * The useful middle posture, and the reason the two gates are separate:
     * sync available to a person who asks for it, nothing on a timer. If one
     * flag governed both, this state would be unreachable.
     */
    public function the_schedule_gate_does_not_disable_an_owners_own_refresh(): void
    {
        $listing = $this->staleImportedListing();

        config([
            'mls_sync.enabled'              => true,
            'mls_sync.schedule.enabled'     => false,
            'mls_sync.lazy_refresh_enabled' => true,
            'mls_sync.roles'                => ['seller', 'landlord'],
            'mls_sync.freshness_minutes'    => 60,
        ]);

        $outcome = app(MlsStaleAccessRefresher::class)->onAccess($listing->fresh(), 'seller', true);

        $this->assertTrue($outcome->isSynced(), 'Silencing the sweep must not silence a deliberate owner refresh');
    }

    // =====================================================================
    // 5. Explicitly enabled => the reviewed architecture works
    // =====================================================================

    /**
     * @test
     *
     * Activation is one environment change. With the three gates on and nothing
     * else altered, the sweep registers at the reviewed cadence and a stale
     * listing is actually reconciled.
     */
    public function explicit_activation_restores_the_full_reviewed_architecture(): void
    {
        $listing = $this->staleImportedListing();

        config([
            'mls_sync.enabled'                    => true,
            'mls_sync.lazy_refresh_enabled'       => true,
            'mls_sync.schedule.enabled'           => true,
            'mls_sync.schedule.sweep_minutes'     => 15,
            'mls_sync.schedule.reconcile_at'      => '03:20',
            'mls_sync.freshness_minutes'          => 60,
            'mls_sync.terminal_freshness_minutes' => 1440,
            'mls_sync.retry_after_minutes'        => 30,
            'mls_sync.roles'                      => ['seller', 'landlord'],
        ]);

        // The schedule comes back, at the reviewed cadence.
        $commands = $this->scheduledMlsCommands();
        $this->assertCount(2, $commands);

        $schedule = new Schedule();
        $kernel   = app(\Illuminate\Contracts\Console\Kernel::class);
        $method   = new \ReflectionMethod($kernel, 'schedule');
        $method->setAccessible(true);
        $method->invoke($kernel, $schedule);

        $events = collect($schedule->events())
            ->filter(fn ($e) => str_contains((string) $e->command, 'mls:sync-listings'));

        $sweep = $events->first(fn ($e) => ! str_contains((string) $e->command, '--reconcile'));
        $recon = $events->first(fn ($e) => str_contains((string) $e->command, '--reconcile'));

        $this->assertSame('*/15 * * * *', $sweep->expression);
        $this->assertSame('20 3 * * *', $recon->expression);

        // And the work actually happens.
        $before = $this->sourceRequests;

        $this->artisan('mls:sync-listings', ['--role' => 'seller'])->assertSuccessful();

        $this->assertSame(1, $this->sourceRequests - $before, 'One listing, one Bridge request');
        $this->assertSame('200000', (string) $listing->fresh()->get->toArray()[Meta::META_LIST_PRICE]);

        // 60-minute freshness now holds the next run off entirely.
        $before = $this->sourceRequests;
        $this->artisan('mls:sync-listings', ['--role' => 'seller'])->assertSuccessful();
        $this->assertSame($before, $this->sourceRequests, 'The 60-minute window did not hold');
    }

    // =====================================================================
    // 6. The diagnostic probe is independent of all of this
    // =====================================================================

    /**
     * @test
     *
     * `mls:probe-lifecycle` is an operator tool, not part of the sync feature.
     * It is governed by its own explicit `--force-probe`, is on no schedule, and
     * is reached from no application code — so activating or deactivating sync
     * neither enables nor disables it.
     */
    public function the_diagnostic_probe_refuses_without_its_own_explicit_force_flag(): void
    {
        foreach ([false, true] as $syncEnabled) {
            config(['mls_sync.enabled' => $syncEnabled, 'mls_sync.schedule.enabled' => $syncEnabled]);

            $before = $this->sourceRequests;

            $this->artisan('mls:probe-lifecycle')
                ->expectsOutput('Refusing to run without --force-probe. This command sends live Bridge requests.')
                ->assertSuccessful();

            $this->assertSame(
                $before,
                $this->sourceRequests,
                'The probe sent a request without --force-probe'
            );
        }
    }

    /** @test */
    public function the_diagnostic_probe_is_on_no_schedule(): void
    {
        config(['mls_sync.enabled' => true, 'mls_sync.schedule.enabled' => true]);

        $schedule = new Schedule();
        $kernel   = app(\Illuminate\Contracts\Console\Kernel::class);
        $method   = new \ReflectionMethod($kernel, 'schedule');
        $method->setAccessible(true);
        $method->invoke($kernel, $schedule);

        $this->assertFalse(
            collect($schedule->events())
                ->map(fn ($e) => (string) $e->command)
                ->contains(fn ($c) => str_contains($c, 'probe-lifecycle')),
            'A live-request diagnostic probe must never be on a timer'
        );
    }
}
