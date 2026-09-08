<?php

namespace Tests\Feature\LocationDna;

use App\Contracts\NearbyPoiFetcherInterface;
use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\GooglePlacesPoiAdapter;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use App\Services\LocationDna\LocationDnaVersionService;
use App\Services\LocationDna\OvertureCorpusPoiAdapter;
use App\Services\LocationDna\Providers\LocationProviderRegistry;
use App\Services\LocationDna\Providers\NearbyPoiFetcherFactory;
use App\Services\LocationDna\StubNearbyPoiFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Events\QueryExecuted;
use RuntimeException;
use Tests\TestCase;

/**
 * Failure posture: exactly where a stub stands in, and exactly where an error propagates.
 *
 * THE DISTINCTION THIS FILE PROTECTS
 * ----------------------------------
 * There are two different moments at which the corpus can be unusable, and they must not
 * be answered the same way:
 *
 *   SELECTION TIME — the factory is asked for a fetcher and the corpus cannot serve (flag
 *   off, version unpinned, cluster unreachable). Nothing has been attempted yet, so the
 *   honest answer is an inert fetcher. Every category then records `not_found`: we looked,
 *   with a provider that had nothing, and found nothing.
 *
 *   RUN TIME — a fetcher was already selected and the corpus fails mid-run (connection
 *   dropped, cluster restarted). Something WAS attempted and it failed. Returning `[]`
 *   here would record `not_found`, which says "there is no grocery store near this home"
 *   when the truth is "we could not ask". So the exception propagates and the caller
 *   records `status = 'error'` with the message.
 *
 * `NearbyPoiFetcherInterface` states this contract explicitly and calls it load-bearing.
 * The adapter honours it; this file proves it end to end, and proves that in neither case
 * does anything fall through to Google.
 */
class CorpusPoiFailurePostureTest extends TestCase
{
    use RefreshDatabase;

    private const LISTING_TYPE = 'seller_agent';
    private const LISTING_ID   = 5150;

    private const LAT = 27.788945;
    private const LNG = -82.735144;

    protected function setUp(): void
    {
        parent::setUp();

        PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'source_address' => '6817 STONES THROW CIRCLE N UNIT 17208',
            'source_city'    => 'ST PETERSBURG',
            'source_state'   => 'FL',
            'source_zip'     => '33710',
            'geocoded_lat'   => self::LAT,
            'geocoded_lng'   => self::LNG,
            'geocode_status' => 'geocoded',
            'geocode_source' => 'saved_meta',
            'geocoded_at'    => now(),
        ]);
    }

    // ══ A. SHIPPED DEFAULT — parity with the pristine baseline ═══════════════

    /**
     * On the shipped config the corpus is not merely unselected — it is never touched.
     * Asserted by watching every query the run executes, because "the adapter was not
     * chosen" and "the adapter did not run a query" are different claims and only the
     * second one rules out a cost.
     */
    public function test_the_shipped_config_issues_no_spatial_query_at_all(): void
    {
        config(['google_places.enabled' => false]);

        $queries = [];
        Event::listen(QueryExecuted::class, function (QueryExecuted $q) use (&$queries): void {
            $queries[] = ['connection' => $q->connectionName, 'sql' => $q->sql];
        });

        (new LocationDnaPoiDistanceService())->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        foreach ($queries as $q) {
            $this->assertNotSame(
                'pgsql_spatial',
                $q['connection'],
                'The shipped config must not query the spatial cluster: ' . $q['sql']
            );
            $this->assertStringNotContainsString(
                'category_key',
                $q['sql'],
                'No corpus KNN may be issued on the shipped config: ' . $q['sql']
            );
        }
    }

    /** No Location DNA POI rows are created when the shipped guard refuses the run. */
    public function test_the_shipped_config_creates_no_poi_rows(): void
    {
        config(['google_places.enabled' => false]);

        $result = (new LocationDnaPoiDistanceService())->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertSame('google_places_disabled', $result['error']);
        $this->assertSame(0, PropertyLocationPoi::count());
    }

    /**
     * Ranking semantics must not move. `scoring_version` is the hash of the scoring
     * surface; it is byte-identical to the pristine 517c9327e value, so no persisted or
     * future ranking changes because of this phase.
     */
    public function test_the_scoring_version_is_unchanged_from_the_baseline(): void
    {
        $this->assertSame(
            '26cd495c846c59e1f29bcefb9a1ce1ab00c96d3f31a38423d8fc9ec691981934',
            (new LocationDnaVersionService())->scoringVersion(),
            'Ranking must be untouched by adding a provider. This value was captured from '
            . 'the pristine 517c9327e worktree.'
        );
    }

    /**
     * `fetch_version` DOES move, and that is correct rather than a regression — the fetch
     * surface genuinely gained a declared provider, and `capabilityHash()` hashes the whole
     * capability map so that cached candidates from a different provider surface are not
     * silently reused.
     *
     * Recorded here because the consequence is worth knowing: rows whose stored
     * `pois_fetch_version` differs are deleted and refetched. `property_location_pois` holds
     * zero rows today, so nothing is invalidated in practice — but the next phase should not
     * be surprised by it.
     */
    public function test_the_fetch_version_moves_and_the_baseline_value_no_longer_matches(): void
    {
        $this->assertNotSame(
            '06275e916d362ed4f5b791285c80e9f00a602f317e25bc8e77844796521ce034',
            (new LocationDnaVersionService())->fetchVersion(),
            'Declaring a provider in the capability map is expected to move fetch_version.'
        );
    }

    // ══ B. CORPUS ENABLED — Google is neither required nor consulted ═════════

    /** No Google key, kill switch off, corpus on: the run proceeds and persists. */
    public function test_an_enabled_corpus_needs_no_google_key_and_ignores_the_kill_switch(): void
    {
        $this->enableCorpus();

        config([
            'google_places.enabled'      => false,
            'services.google.places_key' => null,
        ]);

        $result = (new LocationDnaPoiDistanceService(nearbyFetcher: $this->workingCorpus()))
            ->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success'], 'corpus run failed: ' . json_encode($result));

        $row = PropertyLocationPoi::where('poi_category', 'grocery_store')->where('rank', 1)->firstOrFail();

        $this->assertSame('found', $row->status);
        $this->assertSame('Publix', $row->poi_name);
        $this->assertSame('overture_corpus', $row->provenance_json['provider']);
    }

    // ══ C. CORPUS FAILURE ════════════════════════════════════════════════════

    /**
     * SELECTION TIME. The corpus is chosen but cannot serve, so the factory yields the
     * inert fetcher — never Google, even with Google fully available.
     */
    public function test_selection_time_unavailability_yields_the_stub_never_google(): void
    {
        $this->enableCorpus();

        config([
            'overture_corpus_poi.enabled'        => true,
            'overture_corpus_poi.corpus_version' => 'overture-2026-06-17.0-fl',
            // The spatial connection is inert in CI, so isAvailable() is false.
            'google_places.enabled'              => true,
            'services.google.places_key'         => 'a-key-that-must-never-be-used',
        ]);

        $fetcher = (new NearbyPoiFetcherFactory((array) config('location_providers', [])))->make();

        $this->assertInstanceOf(StubNearbyPoiFetcher::class, $fetcher);
        $this->assertNotInstanceOf(GooglePlacesPoiAdapter::class, $fetcher);
    }

    /**
     * RUN TIME — the case that matters most. The fetcher was already selected and the
     * corpus fails mid-run. This must NOT be recorded as `not_found`.
     */
    public function test_a_mid_run_spatial_outage_is_recorded_as_an_error_not_as_not_found(): void
    {
        $this->enableCorpus();

        config(['google_places.enabled' => false]);

        $result = (new LocationDnaPoiDistanceService(nearbyFetcher: $this->failingCorpus()))
            ->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $row = PropertyLocationPoi::where('poi_category', 'grocery_store')->where('rank', 1)->firstOrFail();

        $this->assertSame(
            'error',
            $row->status,
            'A spatial outage must be recorded as an error. Recording not_found would assert '
            . 'that nothing is near this property, when the truth is that we could not ask.'
        );
        $this->assertStringContainsString(
            OvertureCorpusPoiAdapter::UNAVAILABLE_MESSAGE,
            (string) $row->error
        );
        $this->assertNull($row->poi_name);

        // And nothing was quietly served from Google in its place.
        $this->assertSame('overture_corpus', $row->provenance_json['provider']);
        $this->assertSame(0, PropertyLocationPoi::where('status', 'found')->count());

        // The run itself does not crash — one provider fault is a per-category outcome.
        $this->assertIsArray($result);
    }

    /**
     * The two outcomes are genuinely different rows, not the same row with different
     * words. This is the assertion that would fail if anyone "simplified" the adapter by
     * making `fetchNearby()` swallow like `search()` does.
     */
    public function test_an_outage_and_an_empty_corpus_produce_different_statuses(): void
    {
        $this->enableCorpus();
        config(['google_places.enabled' => false]);

        (new LocationDnaPoiDistanceService(nearbyFetcher: $this->failingCorpus()))
            ->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);
        $outage = PropertyLocationPoi::where('poi_category', 'grocery_store')->where('rank', 1)->firstOrFail()->status;

        PropertyLocationPoi::query()->delete();

        (new LocationDnaPoiDistanceService(nearbyFetcher: new StubNearbyPoiFetcher()))
            ->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);
        $empty = PropertyLocationPoi::where('poi_category', 'grocery_store')->where('rank', 1)->firstOrFail()->status;

        $this->assertSame('error', $outage);
        $this->assertSame('not_found', $empty);
        $this->assertNotSame($outage, $empty);
    }

    /**
     * The buyer/tenant seam takes the opposite stance ON PURPOSE: `search()` swallows and
     * returns [], so a cluster blip degrades a buyer's map instead of failing their page.
     * Both stances live in one adapter and neither may drift into the other.
     */
    public function test_the_buyer_tenant_seam_swallows_the_same_outage(): void
    {
        $this->enableCorpus();

        $adapter = $this->failingCorpus();

        // fetchNearby raises …
        try {
            $adapter->fetchNearby(self::LAT, self::LNG, LocationDnaPoiDistanceService::CATEGORIES['grocery_store']);
            $this->fail('fetchNearby() must propagate a corpus outage.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(OvertureCorpusPoiAdapter::UNAVAILABLE_MESSAGE, $e->getMessage());
        }

        // … while search() degrades.
        $this->assertSame([], $adapter->search(self::LAT, self::LNG, 'gyms', 10, 5));
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function enableCorpus(): void
    {
        config([
            'location_providers.providers.overture_corpus.enabled' => true,
            'overture_corpus_poi.enabled'                          => true,
            'overture_corpus_poi.corpus_version'                   => 'overture-2026-06-17.0-fl',
            'overture_corpus_poi.regions'                          => ['US-FL'],
            'overture_corpus_poi.region_bounds'                    => [
                'US-FL' => ['west' => -87.63, 'south' => 24.40, 'east' => -79.97, 'north' => 31.00],
            ],
        ]);
    }

    /** A corpus that answers. */
    private function workingCorpus(): NearbyPoiFetcherInterface
    {
        return new class extends OvertureCorpusPoiAdapter {
            public function isAvailable(): bool
            {
                return true;
            }

            protected function selectRows(string $sql, array $bindings): array
            {
                if (($bindings[2] ?? null) !== 'grocery_store') {
                    return [];
                }

                return [(object) [
                    'name' => 'Publix', 'brand' => 'Publix', 'confidence' => 1.0,
                    'source_ref' => 'overture:gers:aaa111', 'last_seen' => null, 'attrs' => null,
                    'poi_lat' => 27.79300, 'poi_lng' => -82.74010, 'meters' => 627.9,
                ]];
            }
        };
    }

    /**
     * A corpus that was selected and then fails — the connection drops after the fetcher
     * exists. Modelled by an adapter that reports itself available and raises on the read,
     * which is exactly the shape of a mid-run outage.
     */
    private function failingCorpus(): OvertureCorpusPoiAdapter
    {
        return new class extends OvertureCorpusPoiAdapter {
            public function isAvailable(): bool
            {
                return true;
            }

            protected function selectRows(string $sql, array $bindings): array
            {
                throw new RuntimeException(OvertureCorpusPoiAdapter::UNAVAILABLE_MESSAGE);
            }
        };
    }
}
