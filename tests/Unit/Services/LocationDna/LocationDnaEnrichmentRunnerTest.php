<?php

namespace Tests\Unit\Services\LocationDna;

use App\Services\LocationDna\CommuteTimeLookupService;
use App\Services\LocationDna\FloodZoneLookupService;
use App\Services\LocationDna\LocationDnaEnrichmentRunner;
use App\Services\LocationDna\PoiDistanceLookupService;
use App\Services\LocationDna\SchoolDistrictLookupService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

/**
 * LocationDnaEnrichmentRunnerTest
 *
 * Verifies that LocationDnaEnrichmentRunner::run() fans out to all four
 * enrichment services independently and returns a unified payload.
 *
 * Test coverage (8 cases):
 *   1. All four services succeed — payload has all four populated keys
 *   2. Flood failure does not stop the other three
 *   3. School failure does not stop the other three
 *   4. POI failure does not stop the other three
 *   5. Commute failure does not stop the other three
 *   6. All four services fail — all keys return empty/fallback values
 *   7. Correct payload structure (all four keys present regardless of outcome)
 *   8. Warning logged on each service failure
 */
class LocationDnaEnrichmentRunnerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Fixtures
    // -------------------------------------------------------------------------

    /**
     * Boundary data with no usable geometry and fallback=true.
     * Flood/School services will return their unavailable fallback payloads when
     * called with this data (their own guard logic fires). We need this so that
     * mocks that do NOT expect calls use ->andReturn() rather than ->never().
     */
    private function emptyBoundaryData(): array
    {
        return ['geojson_polygons' => [], 'fallback' => true];
    }

    /**
     * Preferences containing radius_searches (used by flood/school/POI geometry
     * derivation), a commute_origin, and commute_destinations.
     *
     * This ensures all four services are actually invoked in tests that need all
     * paths to be called.
     */
    private function fullPreferences(): array
    {
        return [
            'radius_searches' => [
                [
                    'center'       => ['lat' => 27.9, 'lng' => -82.4],
                    'radius_miles' => 5,
                    'label'        => 'Tampa',
                ],
            ],
            'commute_origin' => ['lat' => 27.9, 'lng' => -82.4],
            'commute_destinations' => [
                ['label' => 'Office', 'address' => '123 Main', 'lat' => 27.95, 'lng' => -82.45],
            ],
            'travel_modes' => ['driving'],
        ];
    }

    /** Empty preferences — no geometry, no commute data. */
    private function emptyPreferences(): array
    {
        return [];
    }

    // -------------------------------------------------------------------------
    // Mock factories
    // -------------------------------------------------------------------------

    private function mockFlood(): MockInterface
    {
        return Mockery::mock(FloodZoneLookupService::class);
    }

    private function mockSchool(): MockInterface
    {
        return Mockery::mock(SchoolDistrictLookupService::class);
    }

    private function mockPoi(): MockInterface
    {
        return Mockery::mock(PoiDistanceLookupService::class);
    }

    private function mockCommute(): MockInterface
    {
        return Mockery::mock(CommuteTimeLookupService::class);
    }

    private function makeRunner(
        MockInterface $flood,
        MockInterface $school,
        MockInterface $poi,
        MockInterface $commute,
    ): LocationDnaEnrichmentRunner {
        return new LocationDnaEnrichmentRunner($flood, $school, $poi, $commute);
    }

    /** Standard success payloads returned by mocks in happy-path tests. */
    private function floodSuccess(): array
    {
        return ['flood_zones' => [['zone_designation' => 'AE', 'rings' => []]], 'available' => true];
    }

    private function schoolSuccess(): array
    {
        return ['school_districts' => [['district_name' => 'Hillsborough County', 'rings' => []]], 'available' => true];
    }

    private function poiSuccess(): array
    {
        return ['results' => [['category' => 'parks', 'name' => 'City Park', 'distance_miles' => 0.5]], 'error' => null, 'source_lat' => 27.9, 'source_lng' => -82.4];
    }

    private function commuteSuccess(): array
    {
        return [['destination_label' => 'Office', 'travel_mode' => 'driving', 'travel_time_minutes' => 15, 'distance_miles' => 8.2, 'destination_address' => '123 Main', 'destination_lat' => 27.95, 'destination_lng' => -82.45, 'source' => 'stub']];
    }

    // -------------------------------------------------------------------------
    // Required payload structure constants
    // -------------------------------------------------------------------------

    private const REQUIRED_KEYS = ['floodZones', 'schoolDistricts', 'pois', 'commuteTimes'];

    private function assertPayloadStructure(array $result): void
    {
        foreach (self::REQUIRED_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Payload missing required key '{$key}'");
        }
        $this->assertCount(count(self::REQUIRED_KEYS), $result, 'Payload must have exactly the 4 required keys');
    }

    // -------------------------------------------------------------------------
    // Test 1: All four services succeed — payload has all four populated keys
    // -------------------------------------------------------------------------

    public function test_all_services_succeed_returns_all_four_populated_keys(): void
    {
        $flood   = $this->mockFlood();
        $school  = $this->mockSchool();
        $poi     = $this->mockPoi();
        $commute = $this->mockCommute();

        $flood->shouldReceive('resolve')->once()->andReturn($this->floodSuccess());
        $school->shouldReceive('resolve')->once()->andReturn($this->schoolSuccess());
        $poi->shouldReceive('lookup')->once()->andReturn($this->poiSuccess());
        $commute->shouldReceive('resolve')->once()->andReturn($this->commuteSuccess());

        $runner = $this->makeRunner($flood, $school, $poi, $commute);
        $result = $runner->run($this->emptyBoundaryData(), $this->fullPreferences());

        $this->assertPayloadStructure($result);
        $this->assertTrue($result['floodZones']['available']);
        $this->assertNotEmpty($result['floodZones']['flood_zones']);
        $this->assertTrue($result['schoolDistricts']['available']);
        $this->assertNotEmpty($result['schoolDistricts']['school_districts']);
        $this->assertNotEmpty($result['pois']['results']);
        $this->assertNotEmpty($result['commuteTimes']);
    }

    // -------------------------------------------------------------------------
    // Test 2: Flood failure does not stop the other three
    // -------------------------------------------------------------------------

    public function test_flood_failure_does_not_stop_remaining_services(): void
    {
        Log::shouldReceive('warning')->once()->with(
            Mockery::pattern('/FloodZone/'),
            Mockery::type('array')
        );

        $flood   = $this->mockFlood();
        $school  = $this->mockSchool();
        $poi     = $this->mockPoi();
        $commute = $this->mockCommute();

        $flood->shouldReceive('resolve')->once()->andThrow(new RuntimeException('FEMA adapter timeout'));
        $school->shouldReceive('resolve')->once()->andReturn($this->schoolSuccess());
        $poi->shouldReceive('lookup')->once()->andReturn($this->poiSuccess());
        $commute->shouldReceive('resolve')->once()->andReturn($this->commuteSuccess());

        $result = $this->makeRunner($flood, $school, $poi, $commute)
            ->run($this->emptyBoundaryData(), $this->fullPreferences());

        $this->assertPayloadStructure($result);
        $this->assertSame([], $result['floodZones']['flood_zones']);
        $this->assertFalse($result['floodZones']['available']);
        $this->assertTrue($result['schoolDistricts']['available']);
        $this->assertNotEmpty($result['pois']['results']);
        $this->assertNotEmpty($result['commuteTimes']);
    }

    // -------------------------------------------------------------------------
    // Test 3: School failure does not stop the other three
    // -------------------------------------------------------------------------

    public function test_school_failure_does_not_stop_remaining_services(): void
    {
        Log::shouldReceive('warning')->once()->with(
            Mockery::pattern('/SchoolDistrict/'),
            Mockery::type('array')
        );

        $flood   = $this->mockFlood();
        $school  = $this->mockSchool();
        $poi     = $this->mockPoi();
        $commute = $this->mockCommute();

        $flood->shouldReceive('resolve')->once()->andReturn($this->floodSuccess());
        $school->shouldReceive('resolve')->once()->andThrow(new RuntimeException('Census adapter error'));
        $poi->shouldReceive('lookup')->once()->andReturn($this->poiSuccess());
        $commute->shouldReceive('resolve')->once()->andReturn($this->commuteSuccess());

        $result = $this->makeRunner($flood, $school, $poi, $commute)
            ->run($this->emptyBoundaryData(), $this->fullPreferences());

        $this->assertPayloadStructure($result);
        $this->assertTrue($result['floodZones']['available']);
        $this->assertSame([], $result['schoolDistricts']['school_districts']);
        $this->assertFalse($result['schoolDistricts']['available']);
        $this->assertNotEmpty($result['pois']['results']);
        $this->assertNotEmpty($result['commuteTimes']);
    }

    // -------------------------------------------------------------------------
    // Test 4: POI failure does not stop the other three
    // -------------------------------------------------------------------------

    public function test_poi_failure_does_not_stop_remaining_services(): void
    {
        Log::shouldReceive('warning')->once()->with(
            Mockery::pattern('/PoiDistance/'),
            Mockery::type('array')
        );

        $flood   = $this->mockFlood();
        $school  = $this->mockSchool();
        $poi     = $this->mockPoi();
        $commute = $this->mockCommute();

        $flood->shouldReceive('resolve')->once()->andReturn($this->floodSuccess());
        $school->shouldReceive('resolve')->once()->andReturn($this->schoolSuccess());
        $poi->shouldReceive('lookup')->once()->andThrow(new RuntimeException('POI adapter unavailable'));
        $commute->shouldReceive('resolve')->once()->andReturn($this->commuteSuccess());

        $result = $this->makeRunner($flood, $school, $poi, $commute)
            ->run($this->emptyBoundaryData(), $this->fullPreferences());

        $this->assertPayloadStructure($result);
        $this->assertTrue($result['floodZones']['available']);
        $this->assertTrue($result['schoolDistricts']['available']);
        $this->assertSame([], $result['pois']['results']);
        $this->assertNull($result['pois']['error']);
        $this->assertNull($result['pois']['source_lat']);
        $this->assertNull($result['pois']['source_lng']);
        $this->assertNotEmpty($result['commuteTimes']);
    }

    // -------------------------------------------------------------------------
    // Test 5: Commute failure does not stop the other three
    // -------------------------------------------------------------------------

    public function test_commute_failure_does_not_stop_remaining_services(): void
    {
        Log::shouldReceive('warning')->once()->with(
            Mockery::pattern('/CommuteTime/'),
            Mockery::type('array')
        );

        $flood   = $this->mockFlood();
        $school  = $this->mockSchool();
        $poi     = $this->mockPoi();
        $commute = $this->mockCommute();

        $flood->shouldReceive('resolve')->once()->andReturn($this->floodSuccess());
        $school->shouldReceive('resolve')->once()->andReturn($this->schoolSuccess());
        $poi->shouldReceive('lookup')->once()->andReturn($this->poiSuccess());
        $commute->shouldReceive('resolve')->once()->andThrow(new RuntimeException('Commute adapter down'));

        $result = $this->makeRunner($flood, $school, $poi, $commute)
            ->run($this->emptyBoundaryData(), $this->fullPreferences());

        $this->assertPayloadStructure($result);
        $this->assertTrue($result['floodZones']['available']);
        $this->assertTrue($result['schoolDistricts']['available']);
        $this->assertNotEmpty($result['pois']['results']);
        $this->assertSame([], $result['commuteTimes']);
    }

    // -------------------------------------------------------------------------
    // Test 6: All four services fail — all keys return empty/fallback values
    // -------------------------------------------------------------------------

    public function test_all_services_fail_all_keys_return_empty_fallback(): void
    {
        Log::shouldReceive('warning')->times(4)->with(
            Mockery::type('string'),
            Mockery::type('array')
        );

        $flood   = $this->mockFlood();
        $school  = $this->mockSchool();
        $poi     = $this->mockPoi();
        $commute = $this->mockCommute();

        $flood->shouldReceive('resolve')->once()->andThrow(new RuntimeException('flood failed'));
        $school->shouldReceive('resolve')->once()->andThrow(new RuntimeException('school failed'));
        $poi->shouldReceive('lookup')->once()->andThrow(new RuntimeException('poi failed'));
        $commute->shouldReceive('resolve')->once()->andThrow(new RuntimeException('commute failed'));

        $result = $this->makeRunner($flood, $school, $poi, $commute)
            ->run($this->emptyBoundaryData(), $this->fullPreferences());

        $this->assertPayloadStructure($result);

        $this->assertSame([], $result['floodZones']['flood_zones']);
        $this->assertFalse($result['floodZones']['available']);

        $this->assertSame([], $result['schoolDistricts']['school_districts']);
        $this->assertFalse($result['schoolDistricts']['available']);

        $this->assertSame([], $result['pois']['results']);
        $this->assertNull($result['pois']['error']);

        $this->assertSame([], $result['commuteTimes']);
    }

    // -------------------------------------------------------------------------
    // Test 7: Correct payload structure always present
    // -------------------------------------------------------------------------

    public function test_payload_structure_is_always_present_regardless_of_outcome(): void
    {
        $flood   = $this->mockFlood();
        $school  = $this->mockSchool();
        $poi     = $this->mockPoi();
        $commute = $this->mockCommute();

        $flood->shouldReceive('resolve')->andReturn(['flood_zones' => [], 'available' => false]);
        $school->shouldReceive('resolve')->andReturn(['school_districts' => [], 'available' => false]);

        $result = $this->makeRunner($flood, $school, $poi, $commute)
            ->run($this->emptyBoundaryData(), $this->emptyPreferences());

        $this->assertPayloadStructure($result);

        $this->assertArrayHasKey('flood_zones', $result['floodZones']);
        $this->assertArrayHasKey('available', $result['floodZones']);

        $this->assertArrayHasKey('school_districts', $result['schoolDistricts']);
        $this->assertArrayHasKey('available', $result['schoolDistricts']);

        $this->assertArrayHasKey('results', $result['pois']);
        $this->assertArrayHasKey('error', $result['pois']);
        $this->assertArrayHasKey('source_lat', $result['pois']);
        $this->assertArrayHasKey('source_lng', $result['pois']);

        $this->assertIsArray($result['commuteTimes']);
    }

    // -------------------------------------------------------------------------
    // Test 8: Warning logged on each service failure (one warning per failure)
    // -------------------------------------------------------------------------

    public function test_warning_is_logged_for_each_individual_service_failure(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with(Mockery::pattern('/FloodZone/'), Mockery::type('array'));

        $flood   = $this->mockFlood();
        $school  = $this->mockSchool();
        $poi     = $this->mockPoi();
        $commute = $this->mockCommute();

        $flood->shouldReceive('resolve')->once()->andThrow(new RuntimeException('flood timeout'));
        $school->shouldReceive('resolve')->once()->andReturn($this->schoolSuccess());
        $poi->shouldReceive('lookup')->once()->andReturn($this->poiSuccess());
        $commute->shouldReceive('resolve')->once()->andReturn($this->commuteSuccess());

        $this->makeRunner($flood, $school, $poi, $commute)
            ->run($this->emptyBoundaryData(), $this->fullPreferences());

        // Mockery verifies the expectation at tearDown; addToAssertionCount keeps PHPUnit happy.
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // Teardown
    // -------------------------------------------------------------------------

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
