<?php

namespace Tests\Unit\Services\LocationDna;

use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use App\Services\LocationDna\PoiConfidenceScorer;
use App\Services\LocationDna\Providers\CanonicalField;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * PoiCanonicalEnvelopePersistenceTest — Batch 3.
 *
 * Proves the single POI writer (LocationDnaPoiDistanceService::createPoiRow) now populates the
 * canonical-field envelope metadata on every persisted row — confidence, provenance_json,
 * last_refreshed (docs/canonical-field-mapping-spec.md §1–§4) — and that it does so under the
 * owner-approved rules:
 *
 *   found      → confidence from PoiConfidenceScorer; provenance raw_ref = place_id
 *   not_found  → confidence NULL; provenance present, raw_ref NULL
 *   error      → confidence NULL; provenance present, raw_ref NULL
 *
 * It also pins the two invariants the batch must not break: one shared last_refreshed per run,
 * and provenance that never carries Place content (spec §8 — only the opaque place_id ref).
 */
class PoiCanonicalEnvelopePersistenceTest extends TestCase
{
    use DatabaseTransactions;

    private const LISTING_TYPE = 'seller_agent_auction';
    private const LISTING_ID   = 777;
    private const SOURCE_LAT   = 27.9506;
    private const SOURCE_LNG   = -82.4572;

    private const PLACE_ID     = 'ChIJbatch3TestPlaceId';
    private const POI_NAME     = 'Batch3 Test Place';
    private const POI_ADDRESS  = '456 Envelope Ave, Tampa';
    private const RATING       = 4.6;
    private const REVIEWS      = 250; // saturates the scorer → confidence 0.9

    private ClientInterface $mockClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockClient = $this->createMock(ClientInterface::class);
        config([
            'google_places.enabled'           => true,
            'services.google.places_key'      => 'test-poi-api-key',
            'location_dna.poi.tile_precision' => null,
            'cache.default'                   => 'array',
        ]);
        Cache::flush();
    }

    private function createGeocodedDnaRecord(): PropertyLocationDna
    {
        return PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'source_address' => '123 Main St',
            'source_city'    => 'Tampa',
            'source_state'   => 'FL',
            'geocoded_lat'   => self::SOURCE_LAT,
            'geocoded_lng'   => self::SOURCE_LNG,
            'geocode_source' => 'google',
            'geocode_status' => 'geocoded',
            'geocoded_at'    => now(),
        ]);
    }

    /** A rich Nearby Search result carrying the fields the envelope depends on. */
    private function makeRichResponse(): Response
    {
        return new Response(200, [], json_encode([
            'status'  => 'OK',
            'results' => [[
                'place_id'           => self::PLACE_ID,
                'name'               => self::POI_NAME,
                'vicinity'           => self::POI_ADDRESS,
                'geometry'           => ['location' => ['lat' => 27.9600, 'lng' => -82.4600]],
                'types'              => ['point_of_interest', 'establishment'],
                'rating'             => self::RATING,
                'user_ratings_total' => self::REVIEWS,
            ]],
        ]));
    }

    private function makeZeroResultsResponse(): Response
    {
        return new Response(200, [], json_encode(['status' => 'ZERO_RESULTS', 'results' => []]));
    }

    private function service(): LocationDnaPoiDistanceService
    {
        return new LocationDnaPoiDistanceService($this->mockClient);
    }

    private function persistedRows()
    {
        return PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->get();
    }

    // =========================================================================
    // FOUND rows — confidence from the scorer, provenance with place_id ref
    // =========================================================================

    /** @test */
    public function found_rows_carry_scorer_confidence_and_place_id_provenance(): void
    {
        $this->createGeocodedDnaRecord();
        $this->mockClient->method('request')->willReturn($this->makeRichResponse());

        $this->service()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $found = $this->persistedRows()->where('status', 'found');
        $this->assertNotEmpty($found, 'Expected at least one found row on the happy path.');

        // The persistence path must reuse the SAME scorer definition as the adapter.
        $expectedConfidence = (new PoiConfidenceScorer())->score(self::RATING, self::REVIEWS);
        $this->assertSame(0.9, $expectedConfidence, 'Fixture sanity: 4.6★ / 250 reviews saturates to 0.9.');

        foreach ($found as $row) {
            $this->assertEqualsWithDelta(
                $expectedConfidence,
                (float) $row->confidence,
                1e-9,
                "confidence not derived by PoiConfidenceScorer for {$row->poi_category}",
            );

            $prov = $row->provenance_json;
            $this->assertSame(
                ['provider', 'method', 'raw_ref', 'license', 'contributors'],
                array_keys($prov),
                'provenance_json must use the canonical envelope key order',
            );
            $this->assertSame('google_places', $prov['provider']);
            $this->assertSame(CanonicalField::METHOD_API, $prov['method']);
            $this->assertSame('google-tos', $prov['license']);
            $this->assertSame(['google_places'], $prov['contributors']);
            $this->assertSame(self::PLACE_ID, $prov['raw_ref'], 'found row raw_ref must be the place_id');

            $this->assertNotNull($row->last_refreshed, 'found row must carry last_refreshed');
        }
    }

    /** @test */
    public function provenance_never_contains_place_content(): void
    {
        $this->createGeocodedDnaRecord();
        $this->mockClient->method('request')->willReturn($this->makeRichResponse());

        $this->service()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        foreach ($this->persistedRows() as $row) {
            $encoded = json_encode($row->provenance_json);
            $this->assertStringNotContainsString(self::POI_NAME, $encoded, 'provenance leaked the POI name');
            $this->assertStringNotContainsString(self::POI_ADDRESS, $encoded, 'provenance leaked the POI address');
            $this->assertStringNotContainsString((string) self::RATING, $encoded, 'provenance leaked the POI rating');
            // Only the opaque place_id reference is permitted (spec §8).
        }
    }

    /** @test */
    public function every_row_written_in_one_run_shares_a_single_last_refreshed(): void
    {
        $this->createGeocodedDnaRecord();
        $this->mockClient->method('request')->willReturn($this->makeRichResponse());

        $this->service()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $stamps = $this->persistedRows()
            ->map(fn ($row) => optional($row->last_refreshed)->toIso8601String())
            ->unique()
            ->values();

        $this->assertCount(1, $stamps, 'All rows written in one run must share exactly one last_refreshed timestamp.');
        $this->assertNotNull($stamps->first());
    }

    // =========================================================================
    // NOT_FOUND rows — null confidence, provenance present with null raw_ref
    // =========================================================================

    /** @test */
    public function not_found_rows_have_null_confidence_and_provenance_without_place_id(): void
    {
        $this->createGeocodedDnaRecord();
        $this->mockClient->method('request')->willReturn($this->makeZeroResultsResponse());

        $this->service()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $notFound = $this->persistedRows()->where('status', 'not_found');
        $this->assertNotEmpty($notFound, 'Zero-results run must persist not_found rows.');

        foreach ($notFound as $row) {
            $this->assertNull($row->confidence, "not_found confidence must be null for {$row->poi_category}");

            $prov = $row->provenance_json;
            $this->assertSame('google_places', $prov['provider']);
            $this->assertSame(CanonicalField::METHOD_API, $prov['method']);
            $this->assertSame('google-tos', $prov['license']);
            $this->assertSame(['google_places'], $prov['contributors']);
            $this->assertNull($prov['raw_ref'], 'not_found provenance raw_ref must be null');

            $this->assertNotNull($row->last_refreshed, 'not_found row must still carry last_refreshed');
        }
    }

    // =========================================================================
    // ERROR rows — null confidence, provenance present with null raw_ref
    // =========================================================================

    /** @test */
    public function error_rows_have_null_confidence_and_provenance_without_place_id(): void
    {
        $this->createGeocodedDnaRecord();
        $this->mockClient->method('request')
            ->willThrowException(new \RuntimeException('provider boom'));

        $this->service()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $errors = $this->persistedRows()->where('status', 'error');
        $this->assertNotEmpty($errors, 'A throwing provider must persist error rows.');

        foreach ($errors as $row) {
            $this->assertNull($row->confidence, "error confidence must be null for {$row->poi_category}");

            $prov = $row->provenance_json;
            $this->assertSame('google_places', $prov['provider']);
            $this->assertSame(CanonicalField::METHOD_API, $prov['method']);
            $this->assertSame('google-tos', $prov['license']);
            $this->assertSame(['google_places'], $prov['contributors']);
            $this->assertNull($prov['raw_ref'], 'error provenance raw_ref must be null');
        }
    }

    // =========================================================================
    // recomputeRankingsFromCache must NOT touch the three canonical fields
    // =========================================================================

    /** @test */
    public function recompute_from_cache_leaves_the_three_canonical_fields_unchanged(): void
    {
        $lastRefreshed = now()->subDay()->startOfSecond();
        $provenance    = CanonicalField::provenance(
            provider:     'google_places',
            method:       CanonicalField::METHOD_API,
            license:      'google-tos',
            rawRef:       'ChIJseededRef',
            contributors: ['google_places'],
        );

        // Two grocery_store rows that ranking will REORDER, seeded with the canonical fields
        // set and deliberately STALE scores so we can prove recompute ran without touching them.
        $seed = function (string $name, float $lat, array $types, float $rating, int $reviews, int $rank)
            use ($lastRefreshed, $provenance): PropertyLocationPoi {
            return PropertyLocationPoi::create([
                'listing_type'         => self::LISTING_TYPE,
                'listing_id'           => self::LISTING_ID,
                'poi_category'         => 'grocery_store',
                'poi_subtype'          => 'Grocery Store',
                'poi_name'             => $name,
                'poi_lat'              => $lat,
                'poi_lng'              => self::SOURCE_LNG,
                'source_lat'           => self::SOURCE_LAT,
                'source_lng'           => self::SOURCE_LNG,
                'distance_miles'       => 0.5,
                'rating'               => $rating,
                'user_ratings_total'   => $reviews,
                'types_json'           => $types,
                'data_source'          => 'google_places',
                'status'               => 'found',
                'calculated_at'        => now(),
                'rank'                 => $rank,
                'ranking_score'        => -1.0,
                'pois_scoring_version' => 'STALE_VERSION',
                // Canonical fields under test:
                'confidence'           => 0.9,
                'provenance_json'      => $provenance,
                'last_refreshed'       => $lastRefreshed,
            ]);
        };

        $near = $seed('Corner Mart',         27.9515, ['supermarket'], 3.4, 8, 98);
        $far  = $seed('Publix Super Market',  27.9058, ['supermarket', 'grocery_or_supermarket'], 4.7, 600, 99);

        $updated = $this->service()->recomputeRankingsFromCache(self::LISTING_TYPE, self::LISTING_ID);
        $this->assertSame(2, $updated, 'Both cached rows must be rescored.');

        foreach ([$near, $far] as $row) {
            $row->refresh();

            // Recompute DID run — scores/version moved off their stale sentinels.
            $this->assertNotSame(-1.0, (float) $row->ranking_score, 'recompute must have rescored the row');
            $this->assertNotSame('STALE_VERSION', $row->pois_scoring_version, 'recompute must re-stamp scoring version');

            // …but the three canonical fields are exactly as seeded.
            $this->assertEqualsWithDelta(0.9, (float) $row->confidence, 1e-9, 'recompute must not change confidence');
            $this->assertSame($provenance, $row->provenance_json, 'recompute must not change provenance_json');
            $this->assertSame(
                $lastRefreshed->toIso8601String(),
                $row->last_refreshed->toIso8601String(),
                'recompute must not change last_refreshed',
            );
        }
    }
}
