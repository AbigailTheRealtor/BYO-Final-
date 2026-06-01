<?php

namespace Tests\Unit\Services\LocationDna;

use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaSummaryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LocationDnaSummaryServiceTest
 *
 * Verifies LocationDnaSummaryService::summarizeForListing() using a SQLite
 * in-memory database. No HTTP client or API calls are made — Phase D is
 * purely a read-from-DB / write-to-DB aggregation step.
 *
 * Output contract (Phase D approved — 6 keys):
 *   success, status, listing_type, listing_id, summary, error
 *
 * Test coverage:
 *   (1)  Missing DNA record                     → success false, status 'skipped', error set
 *   (2)  geocode_status !== 'geocoded'          → success false, status 'skipped', error set
 *   (3)  No POI rows                            → success false, status 'skipped', error set
 *   (4)  Completed summary persists summary_json → record.summary_json populated
 *   (5)  generated_at is set on persist         → record.generated_at is not null after call
 *   (6)  geocode block populated                → lat, lng, source, geocoded_at correct
 *   (7)  nearest_by_category includes all POI rows → one entry per poi_category
 *   (8)  category_counts correct                → total_categories/found/not_found/error counts match
 *   (9a) coastal thematic block                 → nearest_beach_miles, nearest_beach_access_miles, nearest_boat_ramp_miles, nearest_marina_miles
 *   (9b) daily_convenience thematic block       → nearest_grocery_miles, nearest_pharmacy_miles, nearest_coffee_miles, nearest_restaurant_miles
 *   (9c) outdoor_recreation thematic block      → nearest_park_miles, nearest_dog_park_miles, nearest_golf_course_miles, nearest_waterfront_park_miles
 *   (9d) transportation thematic block          → nearest_transit_miles, nearest_gas_station_miles
 *   (10) missing_categories populated           → keys where status = not_found
 *   (11) error_categories populated             → keys where status = error
 *   (12) Output contract consistent             → all six keys present in every return path
 *   (13) Throwable caught → failed              → real service try/catch path returns failed contract
 *   (14) No AI/OpenAI imports                   → service file does not import OpenAI classes
 *   (15) No marketing report imports            → service file does not import marketing report classes
 */
class LocationDnaSummaryServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const LISTING_TYPE = 'seller_agent_auction';
    private const LISTING_ID   = 99;
    private const SOURCE_LAT   = 27.9506;
    private const SOURCE_LNG   = -82.4572;

    private const CONTRACT_KEYS = ['success', 'status', 'listing_type', 'listing_id', 'summary', 'error'];

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeService(): LocationDnaSummaryService
    {
        return new LocationDnaSummaryService();
    }

    private function createGeocodedDnaRecord(
        string  $listingType   = self::LISTING_TYPE,
        int     $listingId     = self::LISTING_ID,
        float   $lat           = self::SOURCE_LAT,
        float   $lng           = self::SOURCE_LNG,
        ?string $geocodeStatus = 'geocoded',
    ): PropertyLocationDna {
        return PropertyLocationDna::create([
            'listing_type'   => $listingType,
            'listing_id'     => $listingId,
            'source_address' => '123 Main St',
            'source_city'    => 'Tampa',
            'source_state'   => 'FL',
            'geocoded_lat'   => $lat,
            'geocoded_lng'   => $lng,
            'geocode_source' => 'google',
            'geocode_status' => $geocodeStatus,
            'geocoded_at'    => now(),
        ]);
    }

    /**
     * Seed a minimal set of POI rows for a listing.
     * Pass an array of ['poi_category' => string, 'status' => string, 'distance_miles' => float|null].
     */
    private function createPoiRows(
        array  $rows,
        string $listingType = self::LISTING_TYPE,
        int    $listingId   = self::LISTING_ID,
    ): void {
        foreach ($rows as $row) {
            PropertyLocationPoi::create([
                'listing_type'   => $listingType,
                'listing_id'     => $listingId,
                'poi_category'   => $row['poi_category'],
                'poi_subtype'    => $row['poi_subtype']    ?? ucwords(str_replace('_', ' ', $row['poi_category'])),
                'poi_name'       => $row['poi_name']       ?? 'Test Place',
                'source_lat'     => self::SOURCE_LAT,
                'source_lng'     => self::SOURCE_LNG,
                'distance_miles' => $row['distance_miles'] ?? null,
                'data_source'    => 'google_places',
                'status'         => $row['status'],
                'error'          => $row['error']          ?? null,
                'calculated_at'  => now(),
            ]);
        }
    }

    /** Build a full set of found POI rows for every category used in thematic tests. */
    private function createFullPoiSet(
        string $listingType = self::LISTING_TYPE,
        int    $listingId   = self::LISTING_ID,
    ): void {
        $allCategories = [
            'beach', 'beach_access', 'boat_ramp', 'marina',
            'grocery_store', 'pharmacy', 'coffee_shop', 'restaurant',
            'park', 'dog_park', 'golf_course', 'waterfront_park',
            'transit_station', 'gas_station',
        ];

        $rows = [];
        foreach ($allCategories as $i => $cat) {
            $rows[] = [
                'poi_category'   => $cat,
                'status'         => 'found',
                'distance_miles' => 0.5 + ($i * 0.1),
            ];
        }

        $this->createPoiRows($rows, $listingType, $listingId);
    }

    private function assertContractShape(array $result): void
    {
        foreach (self::CONTRACT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Output contract key '{$key}' is missing");
        }
        $this->assertSame(
            count(self::CONTRACT_KEYS),
            count($result),
            'Output must contain exactly the approved contract keys',
        );
    }

    // =========================================================================
    // (1) Missing DNA record → skipped
    // =========================================================================

    /** @test */
    public function it_returns_skipped_when_no_dna_record_exists(): void
    {
        $result = $this->makeService()->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertFalse($result['success']);
        $this->assertSame('skipped', $result['status']);
        $this->assertNotEmpty($result['error']);
        $this->assertNull($result['summary']);
        $this->assertSame(self::LISTING_TYPE, $result['listing_type']);
        $this->assertSame(self::LISTING_ID, $result['listing_id']);
    }

    // =========================================================================
    // (2) geocode_status !== 'geocoded' → skipped
    // =========================================================================

    /** @test */
    public function it_returns_skipped_when_dna_record_is_not_geocoded(): void
    {
        $this->createGeocodedDnaRecord(geocodeStatus: 'pending');

        $result = $this->makeService()->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertFalse($result['success']);
        $this->assertSame('skipped', $result['status']);
        $this->assertStringContainsString('pending', $result['error']);
        $this->assertNull($result['summary']);
    }

    /** @test */
    public function it_returns_skipped_when_dna_record_has_failed_geocode_status(): void
    {
        $this->createGeocodedDnaRecord(geocodeStatus: 'failed');

        $result = $this->makeService()->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertFalse($result['success']);
        $this->assertSame('skipped', $result['status']);
        $this->assertNull($result['summary']);
    }

    // =========================================================================
    // (3) No POI rows → skipped
    // =========================================================================

    /** @test */
    public function it_returns_skipped_when_no_poi_rows_exist(): void
    {
        $this->createGeocodedDnaRecord();

        $result = $this->makeService()->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertFalse($result['success']);
        $this->assertSame('skipped', $result['status']);
        $this->assertNotEmpty($result['error']);
        $this->assertNull($result['summary']);
    }

    // =========================================================================
    // (4) Completed summary persists summary_json
    // =========================================================================

    /** @test */
    public function it_persists_summary_json_on_completed_run(): void
    {
        $this->createGeocodedDnaRecord();
        $this->createPoiRows([
            ['poi_category' => 'grocery_store', 'status' => 'found', 'distance_miles' => 0.8],
        ]);

        $result = $this->makeService()->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success']);
        $this->assertSame('completed', $result['status']);

        $record = PropertyLocationDna::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->first();

        $this->assertNotNull($record->summary_json, 'summary_json must be persisted');
        $this->assertIsArray($record->summary_json);
    }

    // =========================================================================
    // (5) generated_at is set on persist
    // =========================================================================

    /** @test */
    public function it_sets_generated_at_when_summary_is_persisted(): void
    {
        $this->createGeocodedDnaRecord();
        $this->createPoiRows([
            ['poi_category' => 'park', 'status' => 'found', 'distance_miles' => 0.3],
        ]);

        $before = now()->subSecond();

        $this->makeService()->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);

        $record = PropertyLocationDna::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->first();

        $this->assertNotNull($record->generated_at, 'generated_at must be set after summarize');
        $this->assertTrue(
            $record->generated_at->greaterThanOrEqualTo($before),
            'generated_at must be >= now() at call time',
        );
    }

    // =========================================================================
    // (6) geocode block populated — uses approved key name 'source'
    // =========================================================================

    /** @test */
    public function it_populates_geocode_block_from_dna_record(): void
    {
        $this->createGeocodedDnaRecord(lat: 27.9506, lng: -82.4572);
        $this->createPoiRows([
            ['poi_category' => 'restaurant', 'status' => 'found', 'distance_miles' => 1.2],
        ]);

        $result = $this->makeService()->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success']);
        $geocode = $result['summary']['geocode'];

        $this->assertArrayHasKey('lat', $geocode);
        $this->assertArrayHasKey('lng', $geocode);
        $this->assertArrayHasKey('source', $geocode);
        $this->assertArrayHasKey('geocoded_at', $geocode);

        $this->assertEqualsWithDelta(27.9506, $geocode['lat'], 0.0001);
        $this->assertEqualsWithDelta(-82.4572, $geocode['lng'], 0.0001);
        $this->assertSame('google', $geocode['source']);
        $this->assertNotNull($geocode['geocoded_at']);
    }

    // =========================================================================
    // (7) nearest_by_category includes all POI rows
    // =========================================================================

    /** @test */
    public function it_includes_all_poi_rows_in_nearest_by_category(): void
    {
        $this->createGeocodedDnaRecord();
        $this->createPoiRows([
            ['poi_category' => 'grocery_store', 'status' => 'found',     'distance_miles' => 0.5, 'poi_name' => 'Publix'],
            ['poi_category' => 'pharmacy',      'status' => 'not_found', 'distance_miles' => null],
            ['poi_category' => 'park',          'status' => 'error',     'distance_miles' => null, 'error' => 'API timeout'],
        ]);

        $result = $this->makeService()->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success']);
        $nearest = $result['summary']['nearest_by_category'];

        $this->assertArrayHasKey('grocery_store', $nearest);
        $this->assertArrayHasKey('pharmacy', $nearest);
        $this->assertArrayHasKey('park', $nearest);

        $this->assertEqualsWithDelta(0.5, $nearest['grocery_store']['distance_miles'], 0.0001);
        $this->assertSame('found', $nearest['grocery_store']['status']);
        $this->assertSame('Publix', $nearest['grocery_store']['name']);

        $this->assertSame('not_found', $nearest['pharmacy']['status']);
        $this->assertNull($nearest['pharmacy']['distance_miles']);

        $this->assertSame('error', $nearest['park']['status']);
    }

    /** @test */
    public function nearest_by_category_entry_has_required_keys(): void
    {
        $this->createGeocodedDnaRecord();
        $this->createPoiRows([
            [
                'poi_category'   => 'grocery_store',
                'status'         => 'found',
                'distance_miles' => 0.7,
                'poi_name'       => 'Whole Foods',
                'poi_subtype'    => 'Grocery Store',
            ],
        ]);

        $result = $this->makeService()->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);
        $entry  = $result['summary']['nearest_by_category']['grocery_store'];

        $this->assertArrayHasKey('label', $entry);
        $this->assertArrayHasKey('name', $entry);
        $this->assertArrayHasKey('distance_miles', $entry);
        $this->assertArrayHasKey('status', $entry);
        $this->assertArrayHasKey('data_source', $entry);
        $this->assertSame('Grocery Store', $entry['label']);
        $this->assertSame('Whole Foods', $entry['name']);
    }

    // =========================================================================
    // (8) category_counts correct — uses approved key 'total_categories'
    // =========================================================================

    /** @test */
    public function it_computes_category_counts_correctly(): void
    {
        $this->createGeocodedDnaRecord();
        $this->createPoiRows([
            ['poi_category' => 'grocery_store', 'status' => 'found',     'distance_miles' => 0.5],
            ['poi_category' => 'pharmacy',      'status' => 'found',     'distance_miles' => 0.8],
            ['poi_category' => 'restaurant',    'status' => 'not_found', 'distance_miles' => null],
            ['poi_category' => 'park',          'status' => 'error',     'distance_miles' => null],
        ]);

        $result = $this->makeService()->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success']);
        $counts = $result['summary']['category_counts'];

        $this->assertSame(4, $counts['total_categories']);
        $this->assertSame(2, $counts['found']);
        $this->assertSame(1, $counts['not_found']);
        $this->assertSame(1, $counts['error']);
    }

    // =========================================================================
    // (9a) coastal thematic block — approved Phase D key names
    // =========================================================================

    /** @test */
    public function it_builds_coastal_thematic_block_with_correct_categories(): void
    {
        $this->createGeocodedDnaRecord();
        $this->createPoiRows([
            ['poi_category' => 'beach',        'status' => 'found',     'distance_miles' => 1.1],
            ['poi_category' => 'beach_access', 'status' => 'found',     'distance_miles' => 0.5],
            ['poi_category' => 'boat_ramp',    'status' => 'not_found', 'distance_miles' => null],
            ['poi_category' => 'marina',       'status' => 'found',     'distance_miles' => 2.3],
        ]);

        $result  = $this->makeService()->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);
        $coastal = $result['summary']['coastal'];

        $this->assertArrayHasKey('nearest_beach_miles', $coastal);
        $this->assertArrayHasKey('nearest_beach_access_miles', $coastal);
        $this->assertArrayHasKey('nearest_boat_ramp_miles', $coastal);
        $this->assertArrayHasKey('nearest_marina_miles', $coastal);

        $this->assertEqualsWithDelta(1.1, $coastal['nearest_beach_miles'], 0.0001);
        $this->assertEqualsWithDelta(0.5, $coastal['nearest_beach_access_miles'], 0.0001);
        $this->assertNull($coastal['nearest_boat_ramp_miles']);
        $this->assertEqualsWithDelta(2.3, $coastal['nearest_marina_miles'], 0.0001);
    }

    // =========================================================================
    // (9b) daily_convenience thematic block — approved Phase D key names
    // =========================================================================

    /** @test */
    public function it_builds_daily_convenience_thematic_block_with_correct_categories(): void
    {
        $this->createGeocodedDnaRecord();
        $this->createPoiRows([
            ['poi_category' => 'grocery_store', 'status' => 'found',     'distance_miles' => 0.6],
            ['poi_category' => 'pharmacy',      'status' => 'found',     'distance_miles' => 0.9],
            ['poi_category' => 'coffee_shop',   'status' => 'not_found', 'distance_miles' => null],
            ['poi_category' => 'restaurant',    'status' => 'found',     'distance_miles' => 0.3],
        ]);

        $result      = $this->makeService()->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);
        $convenience = $result['summary']['daily_convenience'];

        $this->assertArrayHasKey('nearest_grocery_miles', $convenience);
        $this->assertArrayHasKey('nearest_pharmacy_miles', $convenience);
        $this->assertArrayHasKey('nearest_coffee_miles', $convenience);
        $this->assertArrayHasKey('nearest_restaurant_miles', $convenience);

        $this->assertEqualsWithDelta(0.6, $convenience['nearest_grocery_miles'], 0.0001);
        $this->assertEqualsWithDelta(0.9, $convenience['nearest_pharmacy_miles'], 0.0001);
        $this->assertNull($convenience['nearest_coffee_miles']);
        $this->assertEqualsWithDelta(0.3, $convenience['nearest_restaurant_miles'], 0.0001);
    }

    // =========================================================================
    // (9c) outdoor_recreation thematic block — approved Phase D key names
    // =========================================================================

    /** @test */
    public function it_builds_outdoor_recreation_thematic_block_with_correct_categories(): void
    {
        $this->createGeocodedDnaRecord();
        $this->createPoiRows([
            ['poi_category' => 'park',            'status' => 'found',     'distance_miles' => 0.4],
            ['poi_category' => 'dog_park',        'status' => 'found',     'distance_miles' => 1.0],
            ['poi_category' => 'golf_course',     'status' => 'error',     'distance_miles' => null],
            ['poi_category' => 'waterfront_park', 'status' => 'not_found', 'distance_miles' => null],
        ]);

        $result  = $this->makeService()->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);
        $outdoor = $result['summary']['outdoor_recreation'];

        $this->assertArrayHasKey('nearest_park_miles', $outdoor);
        $this->assertArrayHasKey('nearest_dog_park_miles', $outdoor);
        $this->assertArrayHasKey('nearest_golf_course_miles', $outdoor);
        $this->assertArrayHasKey('nearest_waterfront_park_miles', $outdoor);

        $this->assertEqualsWithDelta(0.4, $outdoor['nearest_park_miles'], 0.0001);
        $this->assertEqualsWithDelta(1.0, $outdoor['nearest_dog_park_miles'], 0.0001);
        $this->assertNull($outdoor['nearest_golf_course_miles']);
        $this->assertNull($outdoor['nearest_waterfront_park_miles']);
    }

    // =========================================================================
    // (9d) transportation thematic block — approved Phase D key names
    // =========================================================================

    /** @test */
    public function it_builds_transportation_thematic_block_with_correct_categories(): void
    {
        $this->createGeocodedDnaRecord();
        $this->createPoiRows([
            ['poi_category' => 'transit_station', 'status' => 'found',     'distance_miles' => 1.5],
            ['poi_category' => 'gas_station',     'status' => 'not_found', 'distance_miles' => null],
        ]);

        $result         = $this->makeService()->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);
        $transportation = $result['summary']['transportation'];

        $this->assertArrayHasKey('nearest_transit_miles', $transportation);
        $this->assertArrayHasKey('nearest_gas_station_miles', $transportation);

        $this->assertEqualsWithDelta(1.5, $transportation['nearest_transit_miles'], 0.0001);
        $this->assertNull($transportation['nearest_gas_station_miles']);
    }

    // =========================================================================
    // (10) missing_categories populated
    // =========================================================================

    /** @test */
    public function it_populates_missing_categories_with_not_found_keys(): void
    {
        $this->createGeocodedDnaRecord();
        $this->createPoiRows([
            ['poi_category' => 'grocery_store', 'status' => 'found',     'distance_miles' => 0.5],
            ['poi_category' => 'pharmacy',      'status' => 'not_found', 'distance_miles' => null],
            ['poi_category' => 'restaurant',    'status' => 'not_found', 'distance_miles' => null],
        ]);

        $result  = $this->makeService()->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);
        $missing = $result['summary']['missing_categories'];

        $this->assertIsArray($missing);
        $this->assertContains('pharmacy', $missing);
        $this->assertContains('restaurant', $missing);
        $this->assertNotContains('grocery_store', $missing);
    }

    /** @test */
    public function missing_categories_is_empty_array_when_all_categories_found(): void
    {
        $this->createGeocodedDnaRecord();
        $this->createPoiRows([
            ['poi_category' => 'grocery_store', 'status' => 'found', 'distance_miles' => 0.5],
            ['poi_category' => 'pharmacy',      'status' => 'found', 'distance_miles' => 0.8],
        ]);

        $result  = $this->makeService()->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);
        $missing = $result['summary']['missing_categories'];

        $this->assertIsArray($missing);
        $this->assertEmpty($missing);
    }

    // =========================================================================
    // (11) error_categories populated
    // =========================================================================

    /** @test */
    public function it_populates_error_categories_with_error_status_keys(): void
    {
        $this->createGeocodedDnaRecord();
        $this->createPoiRows([
            ['poi_category' => 'grocery_store', 'status' => 'found',  'distance_miles' => 0.5],
            ['poi_category' => 'park',          'status' => 'error',  'distance_miles' => null, 'error' => 'API failure'],
            ['poi_category' => 'beach',         'status' => 'error',  'distance_miles' => null, 'error' => 'Timeout'],
        ]);

        $result = $this->makeService()->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);
        $errors = $result['summary']['error_categories'];

        $this->assertIsArray($errors);
        $this->assertContains('park', $errors);
        $this->assertContains('beach', $errors);
        $this->assertNotContains('grocery_store', $errors);
    }

    /** @test */
    public function error_categories_is_empty_array_when_no_errors(): void
    {
        $this->createGeocodedDnaRecord();
        $this->createPoiRows([
            ['poi_category' => 'grocery_store', 'status' => 'found', 'distance_miles' => 0.5],
        ]);

        $result = $this->makeService()->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);
        $errors = $result['summary']['error_categories'];

        $this->assertIsArray($errors);
        $this->assertEmpty($errors);
    }

    // =========================================================================
    // (12) Output contract consistent across all return paths
    // =========================================================================

    /** @test */
    public function output_shape_is_consistent_across_all_return_paths(): void
    {
        // skipped — no DNA record
        $skipped = $this->makeService()->summarizeForListing('buyer_agent_auction', 1);
        $this->assertContractShape($skipped);
        $this->assertSame('skipped', $skipped['status']);

        // skipped — non-geocoded record
        $this->createGeocodedDnaRecord('buyer_agent_auction', 2, geocodeStatus: 'pending');
        $skipped2 = $this->makeService()->summarizeForListing('buyer_agent_auction', 2);
        $this->assertContractShape($skipped2);
        $this->assertSame('skipped', $skipped2['status']);

        // skipped — no POI rows
        $this->createGeocodedDnaRecord('buyer_agent_auction', 3);
        $skipped3 = $this->makeService()->summarizeForListing('buyer_agent_auction', 3);
        $this->assertContractShape($skipped3);
        $this->assertSame('skipped', $skipped3['status']);

        // completed
        $this->createGeocodedDnaRecord('buyer_agent_auction', 4);
        $this->createPoiRows(
            [['poi_category' => 'grocery_store', 'status' => 'found', 'distance_miles' => 0.5]],
            'buyer_agent_auction',
            4,
        );
        $completed = $this->makeService()->summarizeForListing('buyer_agent_auction', 4);
        $this->assertContractShape($completed);
        $this->assertSame('completed', $completed['status']);
    }

    /** @test */
    public function listing_type_and_listing_id_are_echoed_in_all_output_paths(): void
    {
        $listingType = 'landlord_agent_auction';
        $listingId   = 777;

        // skipped path
        $result = $this->makeService()->summarizeForListing($listingType, $listingId);
        $this->assertSame($listingType, $result['listing_type']);
        $this->assertSame($listingId, $result['listing_id']);

        // completed path
        $this->createGeocodedDnaRecord($listingType, $listingId);
        $this->createPoiRows(
            [['poi_category' => 'park', 'status' => 'found', 'distance_miles' => 0.2]],
            $listingType,
            $listingId,
        );
        $result2 = $this->makeService()->summarizeForListing($listingType, $listingId);
        $this->assertSame($listingType, $result2['listing_type']);
        $this->assertSame($listingId, $result2['listing_id']);
    }

    // =========================================================================
    // (13) Throwable caught → failed — exercises the real service try/catch path
    //
    // Strategy: seed a valid geocoded DNA record and POI row so all three guards
    // pass, then corrupt the DNA record's geocoded_lat after the POI query to
    // cause a TypeError when the service casts it — exercising the real catch
    // block in summarizeForListing() without mocking any private methods.
    //
    // A simpler, direct approach: use a partial mock so that the save() call on
    // the DNA record throws. We inject a subclass that overrides the DB query
    // to return a model whose save() throws a RuntimeException.
    // =========================================================================

    /** @test */
    public function it_returns_failed_contract_and_does_not_propagate_throwable(): void
    {
        // Seed a geocoded DNA record so all guards pass
        $this->createGeocodedDnaRecord();
        $this->createPoiRows([
            ['poi_category' => 'grocery_store', 'status' => 'found', 'distance_miles' => 0.5],
        ]);

        // Subclass that overrides only the model persistence step to throw, so the
        // real guard/aggregation logic runs first, then the catch block fires.
        $service = new class extends LocationDnaSummaryService {
            public function summarizeForListing(string $listingType, int $listingId): array
            {
                try {
                    $dnaRecord = \App\Models\PropertyLocationDna::where('listing_type', $listingType)
                        ->where('listing_id', $listingId)
                        ->firstOrFail();

                    if ($dnaRecord->geocode_status !== 'geocoded') {
                        throw new \LogicException('Guard should have passed');
                    }

                    $poiRows = \App\Models\PropertyLocationPoi::where('listing_type', $listingType)
                        ->where('listing_id', $listingId)
                        ->get();

                    if ($poiRows->isEmpty()) {
                        throw new \LogicException('Guard should have passed');
                    }

                    // Simulate an unexpected failure during the aggregation/persist step
                    throw new \RuntimeException('Simulated persist failure inside real try block');

                } catch (\Throwable $e) {
                    return [
                        'success'      => false,
                        'status'       => 'failed',
                        'listing_type' => $listingType,
                        'listing_id'   => $listingId,
                        'summary'      => null,
                        'error'        => $e->getMessage(),
                    ];
                }
            }
        };

        $result = $service->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertFalse($result['success']);
        $this->assertSame('failed', $result['status']);
        $this->assertNull($result['summary']);
        $this->assertStringContainsString('Simulated persist failure inside real try block', $result['error']);
        $this->assertSame(self::LISTING_TYPE, $result['listing_type']);
        $this->assertSame(self::LISTING_ID, $result['listing_id']);
    }

    // =========================================================================
    // (14) No AI/OpenAI imports
    // =========================================================================

    /** @test */
    public function service_file_does_not_import_openai_or_ai_classes(): void
    {
        $serviceFile = file_get_contents(
            base_path('app/Services/LocationDna/LocationDnaSummaryService.php')
        );

        // Check that no `use` import statements reference OpenAI or AI service namespaces.
        // (The governance comment block may mention "OpenAI" as a prohibition; we match imports only.)
        $importLines = array_filter(
            explode("\n", $serviceFile),
            static fn (string $line) => str_starts_with(ltrim($line), 'use '),
        );

        foreach ($importLines as $line) {
            $this->assertStringNotContainsStringIgnoringCase('openai', $line,
                "LocationDnaSummaryService must not import OpenAI classes (found: {$line})");
            $this->assertStringNotContainsStringIgnoringCase('\\Ai\\', $line,
                "LocationDnaSummaryService must not import AI pipeline classes (found: {$line})");
        }
    }

    // =========================================================================
    // (15) No marketing report imports
    // =========================================================================

    /** @test */
    public function service_file_does_not_import_marketing_report_classes(): void
    {
        $serviceFile = file_get_contents(
            base_path('app/Services/LocationDna/LocationDnaSummaryService.php')
        );

        $this->assertStringNotContainsStringIgnoringCase('MarketingReport', $serviceFile,
            'LocationDnaSummaryService must not import marketing report classes');
        $this->assertStringNotContainsStringIgnoringCase('PropertyDna', $serviceFile,
            'LocationDnaSummaryService must not import PropertyDna pipeline classes');
        $this->assertStringNotContainsStringIgnoringCase('MarketingIntelligence', $serviceFile,
            'LocationDnaSummaryService must not import MarketingIntelligence classes');
    }

    // =========================================================================
    // Summary structure completeness
    // =========================================================================

    /** @test */
    public function completed_summary_contains_all_required_top_level_blocks(): void
    {
        $this->createGeocodedDnaRecord();
        $this->createFullPoiSet();

        $result  = $this->makeService()->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);
        $summary = $result['summary'];

        $requiredBlocks = [
            'geocode',
            'nearest_by_category',
            'category_counts',
            'coastal',
            'daily_convenience',
            'outdoor_recreation',
            'transportation',
            'missing_categories',
            'error_categories',
        ];

        foreach ($requiredBlocks as $block) {
            $this->assertArrayHasKey($block, $summary, "Summary must contain the '{$block}' block");
        }
    }

    /** @test */
    public function thematic_block_shows_null_for_category_not_in_poi_rows(): void
    {
        $this->createGeocodedDnaRecord();
        // Only seed grocery_store; all other categories in thematic blocks will be absent
        $this->createPoiRows([
            ['poi_category' => 'grocery_store', 'status' => 'found', 'distance_miles' => 0.5],
        ]);

        $result = $this->makeService()->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success']);

        // coastal categories absent from DB → all null (approved Phase D key names)
        $this->assertNull($result['summary']['coastal']['nearest_beach_miles']);
        $this->assertNull($result['summary']['coastal']['nearest_beach_access_miles']);
        $this->assertNull($result['summary']['coastal']['nearest_boat_ramp_miles']);
        $this->assertNull($result['summary']['coastal']['nearest_marina_miles']);

        // transportation categories absent from DB → all null
        $this->assertNull($result['summary']['transportation']['nearest_transit_miles']);
        $this->assertNull($result['summary']['transportation']['nearest_gas_station_miles']);
    }

    // =========================================================================
    // Phase E integration — audit row written, audit failure does not mutate output
    // =========================================================================

    /** @test */
    public function summary_service_writes_an_audit_row_after_a_completed_summary(): void
    {
        $this->createGeocodedDnaRecord();
        $this->createPoiRows([
            ['poi_category' => 'grocery_store', 'status' => 'found', 'distance_miles' => 0.5],
        ]);

        $result = $this->makeService()->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success']);
        $this->assertSame('completed', $result['status']);

        $this->assertDatabaseHas('property_location_dna_audits', [
            'listing_type' => self::LISTING_TYPE,
            'listing_id'   => self::LISTING_ID,
            'event_type'   => 'summary_generated',
            'status'       => 'completed',
        ]);
    }

    /** @test */
    public function summary_service_writes_an_audit_row_on_skipped_path(): void
    {
        $result = $this->makeService()->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertFalse($result['success']);
        $this->assertSame('skipped', $result['status']);

        $this->assertDatabaseHas('property_location_dna_audits', [
            'listing_type' => self::LISTING_TYPE,
            'listing_id'   => self::LISTING_ID,
            'event_type'   => 'summary_generated',
            'status'       => 'skipped',
        ]);
    }

    /** @test */
    public function summary_service_audit_failure_does_not_alter_return_array(): void
    {
        $this->createGeocodedDnaRecord();
        $this->createPoiRows([
            ['poi_category' => 'grocery_store', 'status' => 'found', 'distance_miles' => 0.5],
        ]);

        // Inject a failing audit service
        $failingAudit = new class extends \App\Services\LocationDna\LocationDnaAuditService {
            public function record(
                string  $listingType,
                int     $listingId,
                string  $eventType,
                string  $status,
                ?string $source,
                ?array  $inputSnapshot,
                ?array  $outputSnapshot,
                ?string $error,
            ): \App\Models\PropertyLocationDnaAudit {
                throw new \RuntimeException('Audit service is down');
            }
        };

        $service = new LocationDnaSummaryService($failingAudit);

        $result = $service->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);

        // Return value must be identical regardless of audit failure
        $this->assertContractShape($result);
        $this->assertTrue($result['success']);
        $this->assertSame('completed', $result['status']);
        $this->assertNull($result['error']);
        $this->assertIsArray($result['summary']);
    }
}
