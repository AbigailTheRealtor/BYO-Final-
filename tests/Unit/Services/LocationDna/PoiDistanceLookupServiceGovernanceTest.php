<?php

namespace Tests\Unit\Services\LocationDna;

use Tests\TestCase;

/**
 * PoiDistanceLookupServiceGovernanceTest
 *
 * Governance assertions confirming that the existing property-side
 * LocationDnaPoiDistanceService was not touched by this task.
 *
 * Mirrors test #17 in the existing LocationDnaPoiDistanceServiceTest.
 */
class PoiDistanceLookupServiceGovernanceTest extends TestCase
{
    private const GOVERNED_FILE = 'app/Services/LocationDna/LocationDnaPoiDistanceService.php';

    /** The existing property-side service must still contain its GOVERNANCE BLOCK comment */
    public function test_location_dna_poi_distance_service_contains_governance_block(): void
    {
        $path = base_path(self::GOVERNED_FILE);
        $this->assertFileExists($path, self::GOVERNED_FILE . ' must still exist');

        $content = file_get_contents($path);
        $this->assertStringContainsString(
            'GOVERNANCE BLOCK',
            $content,
            self::GOVERNED_FILE . ' must contain the GOVERNANCE BLOCK comment',
        );
    }

    /** The existing property-side service must NOT import PoiLookupAdapterInterface */
    public function test_location_dna_poi_distance_service_does_not_import_poi_lookup_adapter_interface(): void
    {
        $path = base_path(self::GOVERNED_FILE);
        $this->assertFileExists($path, self::GOVERNED_FILE . ' must still exist');

        $content = file_get_contents($path);
        $this->assertStringNotContainsString(
            'PoiLookupAdapterInterface',
            $content,
            self::GOVERNED_FILE . ' must not reference PoiLookupAdapterInterface',
        );
    }
}
