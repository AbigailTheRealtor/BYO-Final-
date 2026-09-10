<?php

namespace Tests\Feature\ListingImport;

use App\Models\BridgeProperty;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter as Meta;
use App\Services\ListingImport\QuickImport\MlsQuickImportService;
use App\Services\ListingImport\Sync\MlsSyncDemandQueue;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * AUTOMATIC SYNCHRONISATION ACTUALLY RUNS.
 *
 * The service existing is not the feature. The feature is that an MLS-linked
 * listing nobody opens still stops advertising last month's price, and that
 * only happens if something fires on a timer.
 *
 * These tests are about the SWEEP: that it is registered, at the cadence the
 * config claims, that it finds listings created long before it existed, that it
 * is bounded, and that one bad listing does not take the run down with it.
 *
 * NO LIVE BRIDGE REQUEST IS MADE — every source response is an Http::fake over
 * the real BridgeApiService.
 */
class MlsSyncSchedulingTest extends TestCase
{
    use RefreshDatabase;

    /** @var \Closure(): \GuzzleHttp\Promise\PromiseInterface */
    private $sourceResponder;

    private int $sourceRequests = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourceResponder = fn () => Http::response(['value' => []], 200);

        Http::fake(function () {
            $this->sourceRequests++;

            return ($this->sourceResponder)();
        });

        config([
            'bridge.dataset'                         => 'test-dataset',
            'bridge.token'                           => 'test-token',
            'mls_direct_import.prefill_enabled'      => true,
            'mls_direct_import.quick_import_enabled' => true,
            'mls_direct_import.prefill_roles'        => ['seller', 'landlord'],
            'mls_media.enabled'                      => true,
            'mls_media.license_acknowledged'         => true,
            'mls_media.roles'                        => ['seller', 'landlord'],
            'mls_sync.enabled'                       => true,
            'mls_sync.roles'                         => ['seller', 'landlord'],
            'mls_sync.freshness_minutes'             => 0,
            'mls_sync.terminal_freshness_minutes'    => 0,
            'mls_sync.retry_after_minutes'           => 0,
            'mls_sync.schedule.enabled'              => true,
            'mls_sync.schedule.sweep_minutes'        => 15,
            'mls_sync.schedule.reconcile_at'         => '03:20',
        ]);

        app(MlsSyncDemandQueue::class)->flush();
    }

    // =====================================================================
    // Harness
    // =====================================================================

    private function raw(string $key, array $overrides = []): array
    {
        return array_merge([
            'ListingKey'                     => $key,
            'ListingId'                      => 'MLS-' . $key,
            'StandardStatus'                 => 'Active',
            'MlsStatus'                      => 'Active',
            'PropertyType'                   => 'Residential',
            'UnparsedAddress'                => '1 Test Way',
            'City'                           => 'ST PETERSBURG',
            'StateOrProvince'                => 'FL',
            'PostalCode'                     => '33710',
            'ListPrice'                      => 200000,
            'BedroomsTotal'                  => 3,
            'ModificationTimestamp'          => '2026-09-01T10:00:00.000Z',
            'IDXParticipationYN'             => true,
            'InternetEntireListingDisplayYN' => true,
            'InternetAddressDisplayYN'       => true,
            'Media'                          => [],
        ], $overrides);
    }

    private function seedCache(array $raw): void
    {
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
            'bedrooms_total'         => $raw['BedroomsTotal'] ?? null,
            'modification_timestamp' => $raw['ModificationTimestamp'],
            'raw_json'               => json_encode($raw),
            'imported_at'            => now(),
        ]);
    }

    /** Import one listing so it carries real MLS provenance. */
    private function importListing(string $key): SellerAgentAuction
    {
        $raw = $this->raw($key);
        $this->seedCache($raw);

        $result = app(MlsQuickImportService::class)->lookup($raw['ListingId'], 'seller');
        $this->assertTrue($result->isFound());

        return app(Meta::class)->materialise('seller', User::factory()->create()->id, $result);
    }

    private function sourceReturns(array $raw): void
    {
        $this->sourceResponder = fn () => Http::response(['value' => [$raw]], 200);
    }

    /**
     * The events the REAL kernel registers under the config currently in force.
     *
     * Deliberately not `app(Schedule::class)->events()`. That container instance
     * was populated once, during bootstrap, when the shipped defaults still
     * applied — and the shipped defaults now register nothing, because
     * activation is off until an environment explicitly turns it on. Reading it
     * would report an empty schedule regardless of what this test just
     * configured, which is a test that passes or fails for a reason unrelated to
     * its subject.
     *
     * Reflection on the application's own kernel, rather than a hand-built
     * subclass: the subject is what the SHIPPED kernel does under a given
     * config, and a subclass constructed here is a different object that could
     * drift from it.
     */
    private function freshSchedule(): Schedule
    {
        $schedule = new Schedule();
        $kernel   = app(\Illuminate\Contracts\Console\Kernel::class);

        $method = new \ReflectionMethod($kernel, 'schedule');
        $method->setAccessible(true);
        $method->invoke($kernel, $schedule);

        return $schedule;
    }

    /** Every scheduled event's command string, under the current config. */
    private function scheduledCommands(): array
    {
        return collect($this->freshSchedule()->events())
            ->map(fn ($e) => (string) $e->command)
            ->all();
    }

    private function scheduledEventFor(string $needle): ?object
    {
        return collect($this->freshSchedule()->events())
            ->first(fn ($e) => str_contains((string) $e->command, $needle));
    }

    // =====================================================================
    // 1. The schedule exists, at the cadence the config states
    // =====================================================================

    /** @test */
    public function the_sweep_and_the_daily_reconcile_pass_are_both_scheduled(): void
    {
        $commands = $this->scheduledCommands();

        $this->assertTrue(
            collect($commands)->contains(fn ($c) => str_contains($c, 'mls:sync-listings') && ! str_contains($c, '--reconcile')),
            'The frequent MLS sync sweep is not scheduled — nothing keeps listings current unattended'
        );

        $this->assertTrue(
            collect($commands)->contains(fn ($c) => str_contains($c, 'mls:sync-listings --reconcile')),
            'The daily reconciliation pass — the owner-required minimum — is not scheduled'
        );
    }

    /** @test */
    public function the_sweep_runs_every_fifteen_minutes_and_the_reconcile_pass_daily(): void
    {
        $sweep = $this->scheduledEventFor('mls:sync-listings');
        $this->assertNotNull($sweep);
        $this->assertSame('*/15 * * * *', $sweep->expression);

        $reconcile = $this->scheduledEventFor('mls:sync-listings --reconcile');
        $this->assertNotNull($reconcile);
        $this->assertSame('20 3 * * *', $reconcile->expression);
    }

    /**
     * @test
     *
     * Worst-case ordinary staleness is the SUM of the freshness window and the
     * sweep interval, so the two are only meaningful together. This pins the
     * arithmetic the config's comment claims.
     */
    public function the_documented_worst_case_staleness_is_what_the_two_dials_actually_produce(): void
    {
        config(['mls_sync.freshness_minutes' => 60]);

        $sweepMinutes = (int) config('mls_sync.schedule.sweep_minutes');

        $this->assertSame(15, $sweepMinutes);
        $this->assertSame(75, (int) config('mls_sync.freshness_minutes') + $sweepMinutes);
    }

    /** @test */
    public function a_sweep_that_cannot_overlap_itself_is_what_stops_a_slow_run_doubling_the_traffic(): void
    {
        $sweep = $this->scheduledEventFor('mls:sync-listings');

        $this->assertTrue($sweep->withoutOverlapping, 'The sweep must not be able to run on top of itself');
    }

    /**
     * Re-run the real Kernel's schedule() against a fresh Schedule.
     *
     * Reflection on the application's own kernel rather than a hand-built
     * subclass: the point of these two tests is what the SHIPPED kernel does
     * under a given config, and a subclass constructed in the test is a
     * different object that could drift from it.
     */
    private function freshlyScheduledCommands(): \Illuminate\Support\Collection
    {
        return collect($this->freshSchedule()->events())->map(fn ($e) => (string) $e->command);
    }

    /** @test */
    public function turning_the_schedule_gate_off_removes_the_entries_rather_than_leaving_inert_ones(): void
    {
        // A row in `schedule:list` saying a command fires every fifteen minutes,
        // when it in fact returns immediately, is a false statement in the one
        // place an operator looks to find out what runs unattended.
        $this->assertTrue(
            $this->freshlyScheduledCommands()->contains(fn ($c) => str_contains($c, 'mls:sync-listings')),
            'Guard assertion: the sweep should be registered while the gate is on'
        );

        config(['mls_sync.schedule.enabled' => false]);

        $this->assertFalse(
            $this->freshlyScheduledCommands()->contains(fn ($c) => str_contains($c, 'mls:sync-listings')),
            'A disabled sweep must not be registered at all'
        );
    }

    /** @test */
    public function the_master_gate_also_removes_the_schedule(): void
    {
        config(['mls_sync.enabled' => false]);

        $this->assertFalse(
            $this->freshlyScheduledCommands()->contains(fn ($c) => str_contains($c, 'mls:sync-listings')),
        );
    }

    // =====================================================================
    // 2. Discovery — the existing-listing requirement
    // =====================================================================

    /**
     * @test
     *
     * THE REQUIREMENT THAT IS EASIEST TO MISS. A listing imported long before
     * live sync existed carries no sync metadata at all — no last-synced stamp,
     * no source-modified marker, no flag an import had to set. It must still be
     * picked up, because the identifier it has always carried is the whole
     * qualification.
     */
    public function a_listing_imported_before_sync_existed_is_discovered_by_the_sweep(): void
    {
        $listing = $this->importListing('OLD-KEY');

        // Strip every trace of the sync feature, leaving only what an old import
        // would have left behind.
        foreach ([
            Meta::META_SYNCED_AT, Meta::META_SYNC_ATTEMPTED_AT, Meta::META_SYNC_ERROR,
            Meta::META_SOURCE_MODIFIED_AT, Meta::META_STANDARD_STATUS, Meta::META_LIST_PRICE,
        ] as $key) {
            $listing->saveMeta($key, null);
        }

        $this->assertSame('', (string) ($listing->fresh()->get->toArray()[Meta::META_SYNCED_AT] ?? ''));

        $this->sourceReturns($this->raw('OLD-KEY', [
            'ListPrice'             => 175000,
            'ModificationTimestamp' => '2026-09-08T10:00:00.000Z',
        ]));

        $this->artisan('mls:sync-listings', ['--role' => 'seller'])->assertSuccessful();

        $meta = $listing->fresh()->get->toArray();

        $this->assertSame('175000', (string) $meta[Meta::META_LIST_PRICE]);
        $this->assertNotEmpty($meta[Meta::META_SYNCED_AT]);
    }

    /**
     * @test
     *
     * Discovery must not require a ListingKey. A listing imported before that
     * column was persisted carries only the MLS number, and it is exactly the
     * kind of old record most likely to be stale.
     */
    public function a_listing_carrying_only_an_mls_number_is_still_discovered(): void
    {
        $listing = $this->importListing('NOKEY');
        $listing->saveMeta(Meta::META_LISTING_KEY, null);
        $listing->saveMeta(Meta::META_SYNCED_AT, null);

        $this->sourceReturns($this->raw('NOKEY', [
            'ListPrice'             => 165000,
            'ModificationTimestamp' => '2026-09-08T10:00:00.000Z',
        ]));

        $this->artisan('mls:sync-listings', ['--role' => 'seller'])->assertSuccessful();

        $this->assertSame('165000', (string) $listing->fresh()->get->toArray()[Meta::META_LIST_PRICE]);

        // And it resolved by ListingId, not by address.
        Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), "ListingId eq 'MLS-NOKEY'"));
    }

    /** @test */
    public function a_manual_listing_is_never_a_candidate(): void
    {
        $manual = SellerAgentAuction::create([
            'user_id'  => User::factory()->create()->id,
            'address'  => '99 Handtyped Road',
            'is_draft' => false,
        ]);

        $before = $this->sourceRequests;

        $this->artisan('mls:sync-listings', ['--role' => 'seller'])->assertSuccessful();

        $this->assertSame($before, $this->sourceRequests, 'A manual listing must never cause a Bridge request');
    }

    // =====================================================================
    // 3. Bounding
    // =====================================================================

    /** @test */
    public function the_per_run_ceiling_is_respected(): void
    {
        foreach (['B1', 'B2', 'B3'] as $key) {
            $this->importListing($key);
        }

        $this->sourceResponder = fn () => Http::response(['value' => []], 200);

        $before = $this->sourceRequests;

        $this->artisan('mls:sync-listings', ['--role' => 'seller', '--limit' => 2])->assertSuccessful();

        $this->assertSame(
            2,
            $this->sourceRequests - $before,
            'The ceiling — not the cadence — is what bounds worst-case traffic, so it must actually bound it'
        );
    }

    /** @test */
    public function the_reconcile_pass_uses_the_raised_ceiling(): void
    {
        config([
            'mls_sync.batch_limit'           => 1,
            'mls_sync.reconcile_batch_limit' => 50,
        ]);

        foreach (['R1', 'R2', 'R3'] as $key) {
            $this->importListing($key);
        }

        $before = $this->sourceRequests;
        $this->artisan('mls:sync-listings', ['--role' => 'seller'])->assertSuccessful();
        $this->assertSame(1, $this->sourceRequests - $before, 'The ordinary sweep should honour the ordinary ceiling');

        $before = $this->sourceRequests;
        $this->artisan('mls:sync-listings', ['--role' => 'seller', '--reconcile' => true])->assertSuccessful();
        $this->assertSame(3, $this->sourceRequests - $before, 'The daily pass exists to reach everything');
    }

    // =====================================================================
    // 4. One bad listing does not take the run down
    // =====================================================================

    /** @test */
    public function one_listings_provider_failure_does_not_stop_the_others_being_reconciled(): void
    {
        $bad  = $this->importListing('BAD');
        $good = $this->importListing('GOOD');

        // The faked endpoint answers per-request: a fault for one key, a good
        // record for the other.
        $this->sourceResponder = function () {
            static $call = 0;
            $call++;

            return $call === 1
                ? Http::response('', 503)
                : Http::response(['value' => [$this->raw('GOOD', [
                    'ListPrice'             => 123456,
                    'ModificationTimestamp' => '2026-09-08T10:00:00.000Z',
                ])]], 200);
        };

        $this->artisan('mls:sync-listings', ['--role' => 'seller'])->assertSuccessful();

        $badMeta  = $bad->fresh()->get->toArray();
        $goodMeta = $good->fresh()->get->toArray();

        // The failure was recorded and nothing was destroyed. The imported
        // price is still there — a provider that did not answer must never be
        // able to blank a figure the listing already had.
        $this->assertNotEmpty($badMeta[Meta::META_SYNC_ERROR]);
        $this->assertSame('200000', (string) $badMeta['maximum_budget']);

        // …and the other listing was still brought up to date.
        $this->assertSame('123456', (string) $goodMeta[Meta::META_LIST_PRICE]);
    }

    /** @test */
    public function a_failed_listing_is_retried_on_a_later_run(): void
    {
        $listing = $this->importListing('RETRY');

        $this->sourceFails();
        $this->artisan('mls:sync-listings', ['--role' => 'seller'])->assertSuccessful();

        $this->assertNotEmpty($listing->fresh()->get->toArray()[Meta::META_SYNC_ERROR]);

        // The next run finds it again and, this time, succeeds — and the error
        // is cleared rather than lingering as a permanent mark.
        $this->sourceReturns($this->raw('RETRY', [
            'ListPrice'             => 111000,
            'ModificationTimestamp' => '2026-09-08T10:00:00.000Z',
        ]));

        $this->artisan('mls:sync-listings', ['--role' => 'seller'])->assertSuccessful();

        $meta = $listing->fresh()->get->toArray();

        $this->assertSame('111000', (string) $meta[Meta::META_LIST_PRICE]);
        $this->assertEmpty($meta[Meta::META_SYNC_ERROR] ?? null);
    }

    private function sourceFails(): void
    {
        $this->sourceResponder = fn () => Http::response('', 503);
    }

    // =====================================================================
    // 5. Demand priority
    // =====================================================================

    /**
     * @test
     *
     * A listing somebody is looking at right now, whose data is stale, is where
     * visible staleness costs most — so it goes first when the ceiling means not
     * everything fits in one run.
     */
    public function a_listing_viewed_while_stale_is_reconciled_before_the_rest(): void
    {
        $first  = $this->importListing('FIRST');
        $second = $this->importListing('SECOND');

        // `second` is the newer row, so oldest-first ordering would take `first`.
        app(MlsSyncDemandQueue::class)->note('seller', $second->id);

        $this->sourceResponder = fn () => Http::response(['value' => [$this->raw('SECOND', [
            'ListPrice'             => 999000,
            'ModificationTimestamp' => '2026-09-08T10:00:00.000Z',
        ])]], 200);

        $this->artisan('mls:sync-listings', ['--role' => 'seller', '--limit' => 1])->assertSuccessful();

        $this->assertSame(
            '999000',
            (string) $second->fresh()->get->toArray()[Meta::META_LIST_PRICE],
            'The viewed listing should have been the one the single-slot run picked'
        );
    }

    /** @test */
    public function a_demand_hint_can_never_make_a_manual_listing_eligible(): void
    {
        $manual = SellerAgentAuction::create([
            'user_id'  => User::factory()->create()->id,
            'address'  => '99 Handtyped Road',
            'is_draft' => false,
        ]);

        app(MlsSyncDemandQueue::class)->note('seller', $manual->id);

        $before = $this->sourceRequests;

        $this->artisan('mls:sync-listings', ['--role' => 'seller'])->assertSuccessful();

        $this->assertSame($before, $this->sourceRequests);
    }

    /** @test */
    public function the_reconcile_pass_ignores_demand_priority(): void
    {
        $first  = $this->importListing('RFIRST');
        $second = $this->importListing('RSECOND');

        app(MlsSyncDemandQueue::class)->note('seller', $second->id);

        $this->sourceResponder = fn () => Http::response(['value' => []], 200);

        // With a one-slot reconcile run, oldest-first wins and the hint is
        // ignored: the daily pass exists to reach everything in a stable order,
        // not to serve whoever happens to be looking.
        $this->artisan('mls:sync-listings', [
            '--role'      => 'seller',
            '--reconcile' => true,
            '--limit'     => 1,
        ])->assertSuccessful();

        $this->assertNotEmpty(
            $first->fresh()->get->toArray()[Meta::META_SYNC_ATTEMPTED_AT] ?? null,
            'The reconcile pass should have taken the oldest listing, not the viewed one'
        );
    }

    // =====================================================================
    // 6. Terminal statuses stop consuming the frequent budget
    // =====================================================================

    /** @test */
    public function a_closed_listing_stops_being_polled_on_the_frequent_cadence(): void
    {
        config([
            'mls_sync.freshness_minutes'          => 60,
            'mls_sync.terminal_freshness_minutes' => 1440,
        ]);

        $listing = $this->importListing('SOLD');

        // Bring it to Closed through a real sync.
        $this->sourceReturns($this->raw('SOLD', [
            'StandardStatus'        => 'Closed',
            'MlsStatus'             => 'Sold',
            'ModificationTimestamp' => '2026-09-08T10:00:00.000Z',
        ]));

        $this->artisan('mls:sync-listings', ['--role' => 'seller'])->assertSuccessful();
        $this->assertSame('Closed', $listing->fresh()->get->toArray()[Meta::META_STANDARD_STATUS]);

        // Two hours later a live listing would be due again. This one is not.
        $this->travel(2)->hours();

        $before = $this->sourceRequests;
        $this->artisan('mls:sync-listings', ['--role' => 'seller'])->assertSuccessful();

        $this->assertSame(
            $before,
            $this->sourceRequests,
            'A sold property should not keep spending a request an hour to re-learn that it is sold'
        );

        // But it is reduced, never abandoned: a day later it is checked again,
        // so a status Stellar reverses is picked up.
        $this->travel(25)->hours();

        $before = $this->sourceRequests;
        $this->artisan('mls:sync-listings', ['--role' => 'seller'])->assertSuccessful();

        $this->assertSame($before + 1, $this->sourceRequests);
    }
}
