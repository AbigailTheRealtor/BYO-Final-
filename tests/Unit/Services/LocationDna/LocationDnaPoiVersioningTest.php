<?php

namespace Tests\Unit\Services\LocationDna;

use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use App\Services\LocationDna\LocationDnaVersionService;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Stage E0 — fetch-version-aware POI cache invalidation.
 *
 * Proves:
 *   - Behavior-neutral when versions match: cached rows stamped with the current
 *     fetch version are reused with NO Google API call.
 *   - A fetch-version mismatch (stale/NULL) forces a full refetch, and the new
 *     rows are stamped with the current fetch + scoring versions.
 *
 * The version-defining inputs are unchanged in this stage, so a freshly-computed
 * LocationDnaVersionService yields the same version the service computes at run
 * time — matching the post-`ldna:stamp-versions` production state.
 */
class LocationDnaPoiVersioningTest extends TestCase
{
    use DatabaseTransactions;

    private const LISTING_TYPE = 'seller_agent_auction';
    private const LISTING_ID   = 77;
    private const SOURCE_LAT   = 27.9506;
    private const SOURCE_LNG   = -82.4572;

    private ClientInterface $mockClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockClient = $this->createMock(ClientInterface::class);
        config([
            'services.google.places_key'      => 'test-poi-api-key',
            'location_dna.poi.tile_precision' => null,
            'cache.default'                   => 'array',
        ]);
        Cache::flush();

        // Self-healing against a shared/non-transactional database: clear any
        // leftover fixture rows before seeding.
        PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)->where('listing_id', self::LISTING_ID)->delete();
        PropertyLocationDna::where('listing_type', self::LISTING_TYPE)->where('listing_id', self::LISTING_ID)->delete();

        PropertyLocationDna::create([
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

    private function currentFetchVersion(): string
    {
        return (new LocationDnaVersionService())->fetchVersion();
    }

    private function seedAllCategories(?string $fetchVersion): void
    {
        foreach (array_keys(LocationDnaPoiDistanceService::CATEGORIES) as $category) {
            PropertyLocationPoi::create([
                'listing_type'       => self::LISTING_TYPE,
                'listing_id'         => self::LISTING_ID,
                'poi_category'       => $category,
                'poi_subtype'        => 'Test',
                'poi_name'           => 'Clean Place',
                'source_lat'         => self::SOURCE_LAT,
                'source_lng'         => self::SOURCE_LNG,
                'rank'               => 1,
                'distance_miles'     => 0.5,
                'types_json'         => null,
                'data_source'        => 'google_places',
                'pois_fetch_version' => $fetchVersion,
                'status'             => 'found',
                'calculated_at'      => now()->subDay(),
            ]);
        }
    }

    /** Versions match → cached reuse, no API call (behavior-neutral). */
    public function test_matching_fetch_version_reuses_cache_with_no_api_call(): void
    {
        $this->seedAllCategories($this->currentFetchVersion());

        $this->mockClient->expects($this->never())->method('request');

        $result = (new LocationDnaPoiDistanceService($this->mockClient))
            ->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success']);
        $this->assertSame('cached', $result['status']);
    }

    /** Stale fetch version → full refetch, and new rows are stamped current. */
    public function test_stale_fetch_version_forces_refetch_and_restamps(): void
    {
        $this->seedAllCategories('stale-version');

        // A stale version invalidates every stored category → fresh Google calls occur.
        $this->mockClient
            ->expects($this->atLeastOnce())
            ->method('request')
            ->willReturn(new Response(200, [], json_encode([
                'status'  => 'OK',
                'results' => [[
                    'name'     => 'Refetched Place',
                    'vicinity' => '456 Test Ave, Tampa',
                    'geometry' => ['location' => ['lat' => 27.96, 'lng' => -82.46]],
                ]],
            ])));

        $result = (new LocationDnaPoiDistanceService($this->mockClient))
            ->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success']);

        // No row retains the stale version; every fresh row carries current versions.
        $this->assertDatabaseMissing('property_location_pois', [
            'listing_type'       => self::LISTING_TYPE,
            'listing_id'         => self::LISTING_ID,
            'pois_fetch_version' => 'stale-version',
        ]);

        $current = $this->currentFetchVersion();
        $stampedFresh = PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->where('poi_name', 'Refetched Place')
            ->get();

        $this->assertNotEmpty($stampedFresh);
        foreach ($stampedFresh as $row) {
            $this->assertSame($current, $row->pois_fetch_version);
            $this->assertNotNull($row->pois_scoring_version);
        }
    }
}
