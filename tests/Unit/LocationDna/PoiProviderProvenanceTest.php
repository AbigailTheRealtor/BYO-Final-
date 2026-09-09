<?php

namespace Tests\Unit\LocationDna;

use App\Contracts\NearbyPoiFetcherInterface;
use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use App\Support\LocationDna\LocationDataAttribution;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * What a persisted POI row says about where it came from.
 *
 * THE DEFECT THIS PINS. `data_source` was written as the literal `'google_places'`
 * on every row regardless of which adapter answered — it predates the provider
 * registry. Left alone, activating the corpus would have written rows that stated,
 * in the database, that Foursquare/Overture places came from Google: a false
 * provenance record about a third party's data, contradicting `provenance_json`
 * on the same row, and the input to any later attribution or audit.
 *
 * Deterministic throughout: the fetcher is injected, so no corpus cluster is
 * queried and no HTTP request is made under either provider.
 */
class PoiProviderProvenanceTest extends TestCase
{
    use DatabaseTransactions;

    private const LISTING_TYPE = 'seller_agent_auction';
    private const LISTING_ID   = 90210;
    private const LAT          = 27.9506;
    private const LNG          = -82.4572;

    /** The corpus's own upstream identifier, passed through where a place_id would be. */
    private const GERS_ID = '08f2a1b3c4d5e6f7-corpus-ref';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'location_dna.poi.tile_precision' => null, // no tile cache between runs
            'cache.default'                   => 'array',
        ]);

        Cache::flush();
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function geocodedRecord(): PropertyLocationDna
    {
        return PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'source_address' => '123 Main St',
            'source_city'    => 'Tampa',
            'source_state'   => 'FL',
            'geocoded_lat'   => self::LAT,
            'geocoded_lng'   => self::LNG,
            'geocode_source' => 'google',
            'geocode_status' => 'geocoded',
            'geocoded_at'    => now(),
        ]);
    }

    /**
     * A fetcher returning one provider-native row for every category.
     *
     * The shape is the one both adapters return — the corpus adapter maps its rows
     * into it precisely so the persistence layer does not need to know which
     * provider produced them.
     */
    private function fetcherReturning(string $placeId, string $name): NearbyPoiFetcherInterface
    {
        return new class ($placeId, $name) implements NearbyPoiFetcherInterface {
            public function __construct(private string $placeId, private string $name) {}

            public function fetchNearby(float $lat, float $lng, array $meta): array
            {
                return [[
                    'place_id' => $this->placeId,
                    'name'     => $this->name,
                    'vicinity' => null,
                    'geometry' => ['location' => ['lat' => 27.96, 'lng' => -82.46]],
                    'types'    => ['point_of_interest', 'establishment'],
                ]];
            }
        };
    }

    /** Point the registry's `poi.default` effective base at the corpus. */
    private function selectCorpusProvider(): void
    {
        config([
            'location_providers.providers.overture_corpus.enabled' => true,
            'location_providers.providers.google_places.enabled'   => false,
            'overture_corpus_poi.enabled'                          => true,
            'overture_corpus_poi.corpus_version'                   => 'overture-2026-06-17.0-fl',
        ]);
    }

    /** Point it back at Google, with the credential guards satisfied. */
    private function selectGoogleProvider(): void
    {
        config([
            'location_providers.providers.overture_corpus.enabled' => false,
            'location_providers.providers.google_places.enabled'   => true,
            'google_places.enabled'                                => true,
            'services.google.places_key'                           => 'test-poi-api-key',
        ]);
    }

    private function runPipeline(NearbyPoiFetcherInterface $fetcher): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('request')->willReturn(new Response(200, [], json_encode([
            'status' => 'ZERO_RESULTS', 'results' => [],
        ])));

        (new LocationDnaPoiDistanceService(
            httpClient: $client,
            nearbyFetcher: $fetcher,
        ))->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, PropertyLocationPoi> */
    private function rows()
    {
        return PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->get();
    }

    private function foundRows()
    {
        return $this->rows()->where('status', 'found');
    }

    // ── A. Overture ─────────────────────────────────────────────────────────

    /** @test */
    public function a_corpus_backed_row_records_the_corpus_as_its_provider(): void
    {
        $this->geocodedRecord();
        $this->selectCorpusProvider();

        $this->runPipeline($this->fetcherReturning(self::GERS_ID, 'Corpus Grocery'));

        $found = $this->foundRows();

        $this->assertNotEmpty($found, 'No found rows were persisted, so the assertions below would be vacuous.');

        foreach ($found as $row) {
            $this->assertSame('overture_corpus', $row->provenance_json['provider'] ?? null);
            $this->assertSame('overture_corpus', $row->data_source);
        }
    }

    /** @test */
    public function a_corpus_backed_row_is_never_stamped_google_places(): void
    {
        $this->geocodedRecord();
        $this->selectCorpusProvider();

        $this->runPipeline($this->fetcherReturning(self::GERS_ID, 'Corpus Grocery'));

        foreach ($this->rows() as $row) {
            $this->assertNotSame('google_places', $row->data_source, 'A corpus row was stamped as Google.');
            $this->assertNotSame('google_places', $row->provenance_json['provider'] ?? null);
        }
    }

    /** @test */
    public function a_corpus_backed_row_carries_the_overture_licence_and_its_upstream_ref(): void
    {
        $this->geocodedRecord();
        $this->selectCorpusProvider();

        $this->runPipeline($this->fetcherReturning(self::GERS_ID, 'Corpus Grocery'));

        $row = $this->foundRows()->first();

        $this->assertNotNull($row);

        // The compound token from config/location_providers.php — the aggregate, not a
        // member. Crucially not `odbl`: Places is not ODbL, and a stored provenance
        // saying otherwise would be the wrong licence recorded against real rows.
        $licence = $row->provenance_json['license'] ?? null;

        $this->assertIsString($licence);
        $this->assertStringNotContainsString('odbl', $licence);
        $this->assertStringContainsString('cdla-permissive-2.0', $licence);
        $this->assertStringContainsString('apache-2.0', $licence);

        // The corpus's own upstream identifier, stored as an opaque reference exactly
        // as a place_id would be — never content.
        $this->assertSame(self::GERS_ID, $row->provenance_json['raw_ref'] ?? null);
        $this->assertSame(['overture_corpus'], $row->provenance_json['contributors'] ?? null);
    }

    /** @test */
    public function a_corpus_backed_row_is_version_stamped_for_the_corpus_it_came_from(): void
    {
        $this->geocodedRecord();
        $this->selectCorpusProvider();
        $this->runPipeline($this->fetcherReturning(self::GERS_ID, 'Corpus Grocery'));

        $underV1 = $this->foundRows()->first()->pois_fetch_version;

        $this->assertNotEmpty($underV1);

        // Re-pinning the corpus must move the fetch stamp, or rows from the previous
        // import read as current and are never refetched — the tile-cache defect one
        // layer down. Both read the one CorpusSurface definition.
        PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)->delete();

        config(['overture_corpus_poi.corpus_version' => 'overture-2026-09-01.0-fl']);

        $this->runPipeline($this->fetcherReturning(self::GERS_ID, 'Corpus Grocery'));

        $underV2 = $this->foundRows()->first()->pois_fetch_version;

        $this->assertNotSame($underV1, $underV2, 'A new corpus version did not move pois_fetch_version.');
    }

    // ── B. Google ───────────────────────────────────────────────────────────

    /** @test */
    public function a_google_backed_row_still_records_google(): void
    {
        // The existing behaviour, preserved. The fix must make the column truthful,
        // not merely change what it lies about.
        $this->geocodedRecord();
        $this->selectGoogleProvider();

        $this->runPipeline($this->fetcherReturning('ChIJgoogleTestPlaceId', 'Google Grocery'));

        $found = $this->foundRows();

        $this->assertNotEmpty($found);

        foreach ($found as $row) {
            $this->assertSame('google_places', $row->data_source);
            $this->assertSame('google_places', $row->provenance_json['provider'] ?? null);
        }
    }

    // ── C. No contradiction ─────────────────────────────────────────────────

    /** @test */
    public function no_persisted_row_may_contradict_its_own_provenance(): void
    {
        // The invariant that matters more than either individual value: whatever the
        // two fields say, they must say the same thing. They are written from one
        // resolved identity precisely so this cannot drift.
        foreach ([fn () => $this->selectCorpusProvider(), fn () => $this->selectGoogleProvider()] as $select) {
            PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
                ->where('listing_id', self::LISTING_ID)->delete();
            PropertyLocationDna::where('listing_type', self::LISTING_TYPE)
                ->where('listing_id', self::LISTING_ID)->delete();

            $this->geocodedRecord();
            $select();

            $this->runPipeline($this->fetcherReturning('ref-' . uniqid(), 'Somewhere'));

            foreach ($this->rows() as $row) {
                $this->assertSame(
                    $row->provenance_json['provider'] ?? null,
                    $row->data_source,
                    'data_source and provenance_json.provider disagree on a persisted row.'
                );
            }
        }
    }

    // ── D. Attribution follows the rows ─────────────────────────────────────

    /** @test */
    public function attribution_is_derived_from_the_persisted_rows_not_the_active_provider(): void
    {
        $this->geocodedRecord();
        $this->selectCorpusProvider();

        $this->runPipeline($this->fetcherReturning(self::GERS_ID, 'Corpus Grocery'));

        $rows = $this->rows();

        // Now switch the ACTIVE provider back to Google, as a later deactivation would.
        // The rows on the page are unchanged, so what they owe is unchanged: a POI row
        // outlives the switch that fetched it.
        $this->selectGoogleProvider();

        $ids = array_column(LocationDataAttribution::forPois($rows), 'id');

        $this->assertContains('overture_places', $ids, 'Attribution followed the active provider instead of the rows.');
        $this->assertNotContains('google_places', $ids);
    }
}
