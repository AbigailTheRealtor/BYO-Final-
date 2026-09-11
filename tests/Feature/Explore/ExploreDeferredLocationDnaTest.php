<?php

namespace Tests\Feature\Explore;

use App\Jobs\ComputeLocationDna;
use App\Models\BridgeCriteriaFetchCache;
use App\Models\BridgeProperty;
use App\Models\PropertyLocationDna;
use App\Services\Bridge\BridgeApiService;
use App\Services\Bridge\BridgeListingLookupService;
use App\Services\Bridge\LazyBridgeImportService;
use App\Services\Explore\ExploreInventoryService;
use App\Services\Explore\ExploreTransactionType;
use App\Services\Explore\ExploreViewport;
use App\Services\LocationDna\LocationDnaGeocodeService;
use App\Services\LocationDna\LocationDnaPipelineRunner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Explore\Concerns\MakesExploreListings;
use Tests\Feature\Explore\Support\FakeBridgeApi;
use Tests\TestCase;

/**
 * Explore DEFERS Location DNA. It must not SUPPRESS it for everyone after.
 *
 * Explore imports with `dispatchDna: false`, so a map pass cannot fan out into
 * Google Places. But a row it imported first is no longer new by the time a
 * normal import reaches it, and the normalizer's rule — dispatch for a new or
 * re-addressed row — would then never fire. These tests pin the repair: a normal
 * caller schedules DNA for a row that has never had it requested for its current
 * address, once, and never for a row whose DNA is already on record.
 *
 * "On record" is proved with the REAL first step of the pipeline, fed the
 * address the REAL pipeline builds for a Bridge row — not a hand-written row —
 * because the storm this could otherwise become is a stored address that never
 * matches the live one, and only the real writer can show that it does.
 *
 * ZERO live Bridge requests (FakeBridgeApi). ZERO live Google requests
 * (Http::fake() records anything that tries; the DNA job itself is faked, and
 * the geocode step is fed MLS coordinates, so no geocoder is asked).
 */
class ExploreDeferredLocationDnaTest extends TestCase
{
    use DatabaseTransactions;
    use MakesExploreListings;

    private FakeBridgeApi $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'explore.enabled'                       => true,
            'explore.discovery.enabled'             => true,
            'mls_media.enabled'                     => true,
            'mls_media.license_acknowledged'        => true,
            'explore.provider_budget.global_hourly' => 1000,
            'explore.provider_budget.global_daily'  => 5000,
            'explore.provider_budget.actor_hourly'  => 1000,
            'explore.provider_budget.actor_daily'   => 5000,
        ]);

        Cache::flush();
        Bus::fake([ComputeLocationDna::class]);
        Http::fake();

        $this->provider = new FakeBridgeApi();
        $this->app->instance(BridgeApiService::class, $this->provider);
    }

    /* ── helpers ────────────────────────────────────────────────────────── */

    private function exploreDiscovers(): void
    {
        BridgeCriteriaFetchCache::query()->delete();

        $this->getJson('/api/explore/listings?' . http_build_query([
            'bbox'             => $this->bboxAroundDefault(),
            'transaction_type' => 'sale',
        ]))->assertOk();
    }

    private function explorePanelRefreshes(string $listingKey): void
    {
        BridgeProperty::where('listing_key', $listingKey)->update(['imported_at' => now()->subDays(30)]);

        $this->getJson('/api/explore/listings/' . $listingKey)->assertOk();
    }

    /** A normal, non-Explore import of the same area — the buyer criteria search's own call. */
    private function normalImport(): void
    {
        // A fresh pass each time; otherwise the fetch cache would answer.
        BridgeCriteriaFetchCache::query()->delete();

        $payload = app(ExploreInventoryService::class)->payloadFor(
            ExploreViewport::fromString($this->bboxAroundDefault()),
            ExploreTransactionType::SALE->propertyTypes(),
        );

        app(LazyBridgeImportService::class)->importForCriteria($payload, 'buyer');
    }

    private function dispatchesFor(string $listingKey): int
    {
        $id = (int) BridgeProperty::where('listing_key', $listingKey)->value('id');

        return Bus::dispatched(
            ComputeLocationDna::class,
            fn (ComputeLocationDna $job): bool => $job->listingType === 'bridge' && $job->listingId === $id,
        )->count();
    }

    /**
     * What a DNA run leaves behind before it calls anything: the real geocode
     * step, fed the address the real pipeline builds for this Bridge row.
     */
    private function dnaRunRecorded(string $listingKey): void
    {
        $id = (int) BridgeProperty::where('listing_key', $listingKey)->value('id');

        $addressData = (fn (int $listingId): array => $this->resolveBridgeAddress($listingId))
            ->call(app(LocationDnaPipelineRunner::class), $id);

        app(LocationDnaGeocodeService::class)->geocodeForListing('bridge', $id, $addressData);

        $this->assertTrue(
            PropertyLocationDna::where(['listing_type' => 'bridge', 'listing_id' => $id])->exists(),
            'precondition: the pipeline recorded a DNA row for this listing'
        );
    }

    /* ── A · B · C — Explore first, then normal callers ─────────────────── */

    /** A — Explore's first import creates the row and schedules no DNA. @test */
    public function explore_first_import_does_not_dispatch_location_dna(): void
    {
        $this->provider->records = [$this->providerRecord(['listing_key' => 'DEFER-1'])];

        $this->exploreDiscovers();

        $this->assertTrue(BridgeProperty::where('listing_key', 'DEFER-1')->exists());
        $this->assertSame(0, $this->dispatchesFor('DEFER-1'));
        Http::assertNothingSent();
    }

    /** B — the same listing through a normal import afterwards DOES get DNA scheduled. @test */
    public function a_normal_import_after_explore_schedules_the_missing_dna(): void
    {
        $this->provider->records = [$this->providerRecord(['listing_key' => 'DEFER-1'])];

        $this->exploreDiscovers();
        $this->assertSame(0, $this->dispatchesFor('DEFER-1'));

        $this->normalImport();

        $this->assertSame(1, $this->dispatchesFor('DEFER-1'), 'deferred, not suppressed');
    }

    /** B, through the lookup service's API write — the other normal write path. @test */
    public function a_normal_lookup_refresh_after_explore_schedules_the_missing_dna(): void
    {
        $this->provider->records = [$this->providerRecord(['listing_key' => 'DEFER-LOOKUP'])];

        $this->exploreDiscovers();
        app(BridgeListingLookupService::class)->refreshByListingKey('DEFER-LOOKUP');

        $this->assertSame(1, $this->dispatchesFor('DEFER-LOOKUP'));
    }

    /**
     * C — once DNA is on record for the current address, normal imports do not
     * dispatch it again. The backfill claim is flushed first, so it is the DNA
     * row — the real state — that stops the repeat.
     *
     * @test
     */
    public function normal_imports_do_not_redispatch_dna_that_is_on_record(): void
    {
        $this->provider->records = [$this->providerRecord(['listing_key' => 'DEFER-1'])];

        $this->exploreDiscovers();
        $this->normalImport();
        $this->assertSame(1, $this->dispatchesFor('DEFER-1'));

        $this->dnaRunRecorded('DEFER-1');
        Cache::flush();

        $this->normalImport();
        $this->normalImport();
        app(BridgeListingLookupService::class)->refreshByListingKey('DEFER-1');

        $this->assertSame(1, $this->dispatchesFor('DEFER-1'), 'three more normal writes, no second dispatch');
    }

    /**
     * C — before the job has written its row (a queued job, or two imports
     * racing), a second normal import does not schedule a duplicate.
     *
     * @test
     */
    public function a_second_normal_import_before_the_job_runs_does_not_duplicate_it(): void
    {
        $this->provider->records = [$this->providerRecord(['listing_key' => 'DEFER-1'])];

        $this->exploreDiscovers();
        $this->normalImport();
        $this->normalImport();

        $this->assertSame(1, $this->dispatchesFor('DEFER-1'));
    }

    /**
     * C — a NEW row's own dispatch is not duplicated by the backfill before its
     * job has run and written a DNA record (here the job is faked, so it never
     * does — the queued-job case).
     *
     * @test
     */
    public function a_new_rows_own_dispatch_is_not_duplicated_before_its_job_runs(): void
    {
        $this->provider->records = [$this->providerRecord(['listing_key' => 'NEW-ONCE'])];

        $this->normalImport();
        $this->normalImport();

        $this->assertSame(1, $this->dispatchesFor('NEW-ONCE'));
    }

    /** A price or status change is not a reason to recompute Location DNA. @test */
    public function a_price_or_status_change_does_not_dispatch(): void
    {
        $this->provider->records = [$this->providerRecord(['listing_key' => 'PRICE-1'])];

        $this->normalImport();
        $this->assertSame(1, $this->dispatchesFor('PRICE-1'), 'new row: the original rule');

        $this->dnaRunRecorded('PRICE-1');
        Cache::flush();

        $this->provider->records = [$this->providerRecord([
            'listing_key' => 'PRICE-1',
            'list_price'  => 499000,
            'mls_status'  => 'Price Change',
        ])];

        $this->normalImport();

        $this->assertSame(1, $this->dispatchesFor('PRICE-1'));
    }

    /**
     * A row missing a field the geocoder requires is never backfilled: that run
     * would be skipped without writing a record, so dispatching it would repeat
     * on every import.
     *
     * @test
     */
    public function a_row_the_geocoder_would_skip_is_not_backfilled(): void
    {
        $this->provider->records = [$this->providerRecord(['listing_key' => 'NO-CITY', 'city' => ''])];

        $this->exploreDiscovers();
        $this->normalImport();
        $this->normalImport();

        $this->assertSame(0, $this->dispatchesFor('NO-CITY'));
    }

    /* ── D — address changes ────────────────────────────────────────────── */

    /** D — a normal import that moves the address recomputes DNA, exactly as before. @test */
    public function an_address_change_on_a_normal_import_still_dispatches(): void
    {
        $this->provider->records = [$this->providerRecord(['listing_key' => 'MOVE-1'])];

        $this->normalImport();
        $this->dnaRunRecorded('MOVE-1');

        $this->provider->records = [$this->providerRecord([
            'listing_key'      => 'MOVE-1',
            'unparsed_address' => '999 Relocated Avenue',
        ])];

        $this->normalImport();

        $this->assertSame(2, $this->dispatchesFor('MOVE-1'));
    }

    /**
     * D — when EXPLORE saw the address change first (and, correctly, dispatched
     * nothing), the next normal import still recomputes: the DNA on record is
     * for the old address, so it is not current.
     *
     * @test
     */
    public function an_address_change_explore_saw_first_is_still_recomputed_later(): void
    {
        $this->provider->records = [$this->providerRecord(['listing_key' => 'MOVE-2'])];

        $this->normalImport();
        $this->dnaRunRecorded('MOVE-2');
        $this->assertSame(1, $this->dispatchesFor('MOVE-2'));

        $this->provider->records = [$this->providerRecord([
            'listing_key'      => 'MOVE-2',
            'unparsed_address' => '1 Moved While Explore Watched',
        ])];

        $this->exploreDiscovers();
        $this->assertSame(1, $this->dispatchesFor('MOVE-2'), 'Explore dispatched nothing for the move');

        Cache::flush();
        $this->normalImport();

        $this->assertSame(2, $this->dispatchesFor('MOVE-2'), 'the DNA on record was for the old address');
    }

    /* ── E · F — Explore itself never dispatches ────────────────────────── */

    /** E — Explore rediscovering and panel-refreshing the same DNA-less row still never dispatches. @test */
    public function explore_again_never_dispatches(): void
    {
        $this->provider->records = [$this->providerRecord(['listing_key' => 'DEFER-E'])];

        $this->exploreDiscovers();
        $this->exploreDiscovers();
        $this->explorePanelRefreshes('DEFER-E');

        $this->assertSame(1, count($this->provider->singleCalls), 'the panel really did refresh the record');
        $this->assertSame(0, $this->dispatchesFor('DEFER-E'));
        Http::assertNothingSent();
    }

    /** F — with Google Places ON, Explore's own import path still dispatches no DNA. @test */
    public function explore_does_not_dispatch_even_with_google_places_enabled(): void
    {
        config(['google_places.enabled' => true]);

        $this->provider->records = [$this->providerRecord(['listing_key' => 'DEFER-F'])];

        $this->exploreDiscovers();
        $this->explorePanelRefreshes('DEFER-F');

        $this->assertSame(0, $this->dispatchesFor('DEFER-F'));
        Bus::assertNotDispatched(ComputeLocationDna::class);
        Http::assertNothingSent();
    }
}
