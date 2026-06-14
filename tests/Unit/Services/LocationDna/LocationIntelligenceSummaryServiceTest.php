<?php

namespace Tests\Unit\Services\LocationDna;

use App\Services\LocationDna\LocationIntelligenceSummaryService;
use Tests\TestCase;

/**
 * LocationIntelligenceSummaryServiceTest
 *
 * Covers the eight cases required by Phase 4A:
 *   (1) Flood-only payload
 *   (2) School-only payload
 *   (3) POI-only payload
 *   (4) Commute-only payload
 *   (5) Combined multi-section payload
 *   (6) Empty payload → empty summary_lines, no exception
 *   (7) Malformed/unexpected payload shape → graceful skip, no exception
 *   (8) Duplicate district names are deduplicated
 *
 * Plus eight new cases required by Phase 4D (alternate-key / dual-shape):
 *   (9)  Flood zone via zone_designation key
 *   (10) School district via district_name key
 *   (11) POI via category key (name stays as-is)
 *   (12) Commute via destination_label + travel_time_minutes keys
 *   (13) Mixed old and new key payload across all four sections
 *   (14) Malformed entries with new key shapes are still skipped
 *   (15) Duplicate district_name entries are deduplicated
 *   (16) Governance: no DB or Eloquent imports in the service file
 *
 * No database, no factories, no HTTP calls — purely in-memory fixture arrays.
 */
class LocationIntelligenceSummaryServiceTest extends TestCase
{
    private function makeService(): LocationIntelligenceSummaryService
    {
        return new LocationIntelligenceSummaryService();
    }

    private function assertSummaryShape(array $result): void
    {
        $this->assertIsArray($result);
        $this->assertArrayHasKey('summary_lines', $result);
        $this->assertIsArray($result['summary_lines']);
    }

    // =========================================================================
    // (1) Flood-only payload
    // =========================================================================

    /** @test */
    public function it_formats_flood_zone_lines_from_flood_only_payload(): void
    {
        $payload = [
            'floodZones' => [
                ['zone' => 'X'],
                ['zone' => 'AE'],
            ],
        ];

        $result = $this->makeService()->summarize($payload);

        $this->assertSummaryShape($result);
        $this->assertContains('Flood Zone: X', $result['summary_lines']);
        $this->assertContains('Flood Zone: AE', $result['summary_lines']);
        $this->assertCount(2, $result['summary_lines']);
    }

    // =========================================================================
    // (2) School-only payload
    // =========================================================================

    /** @test */
    public function it_formats_school_district_lines_from_school_only_payload(): void
    {
        $payload = [
            'schoolDistricts' => [
                ['name' => 'Pinellas County Schools'],
                ['name' => 'Hillsborough County Schools'],
            ],
        ];

        $result = $this->makeService()->summarize($payload);

        $this->assertSummaryShape($result);
        $this->assertContains('School District: Pinellas County Schools', $result['summary_lines']);
        $this->assertContains('School District: Hillsborough County Schools', $result['summary_lines']);
        $this->assertCount(2, $result['summary_lines']);
    }

    // =========================================================================
    // (3) POI-only payload
    // =========================================================================

    /** @test */
    public function it_formats_nearby_poi_lines_from_poi_only_payload(): void
    {
        $payload = [
            'pois' => [
                ['label' => 'Hospital', 'name' => 'Bay Pines VA Medical Center'],
                ['label' => 'Park',     'name' => 'Weedon Island Preserve'],
            ],
        ];

        $result = $this->makeService()->summarize($payload);

        $this->assertSummaryShape($result);
        $this->assertContains('Nearby Hospital: Bay Pines VA Medical Center', $result['summary_lines']);
        $this->assertContains('Nearby Park: Weedon Island Preserve', $result['summary_lines']);
        $this->assertCount(2, $result['summary_lines']);
    }

    // =========================================================================
    // (4) Commute-only payload
    // =========================================================================

    /** @test */
    public function it_formats_commute_time_lines_from_commute_only_payload(): void
    {
        $payload = [
            'commuteTimes' => [
                ['destination' => 'Downtown Tampa', 'minutes' => 24],
                ['destination' => 'Tampa International Airport', 'minutes' => 18],
            ],
        ];

        $result = $this->makeService()->summarize($payload);

        $this->assertSummaryShape($result);
        $this->assertContains('Downtown Tampa: 24 minutes', $result['summary_lines']);
        $this->assertContains('Tampa International Airport: 18 minutes', $result['summary_lines']);
        $this->assertCount(2, $result['summary_lines']);
    }

    // =========================================================================
    // (5) Combined multi-section payload
    // =========================================================================

    /** @test */
    public function it_formats_all_sections_in_combined_payload(): void
    {
        $payload = [
            'floodZones' => [
                ['zone' => 'X'],
            ],
            'schoolDistricts' => [
                ['name' => 'Pinellas County Schools'],
            ],
            'pois' => [
                ['label' => 'Beach', 'name' => 'Clearwater Beach'],
            ],
            'commuteTimes' => [
                ['destination' => 'Downtown Tampa', 'minutes' => 30],
            ],
        ];

        $result = $this->makeService()->summarize($payload);

        $this->assertSummaryShape($result);
        $this->assertContains('Flood Zone: X', $result['summary_lines']);
        $this->assertContains('School District: Pinellas County Schools', $result['summary_lines']);
        $this->assertContains('Nearby Beach: Clearwater Beach', $result['summary_lines']);
        $this->assertContains('Downtown Tampa: 30 minutes', $result['summary_lines']);
        $this->assertCount(4, $result['summary_lines']);
    }

    // =========================================================================
    // (6) Empty payload → empty summary_lines, no exception
    // =========================================================================

    /** @test */
    public function it_returns_empty_summary_lines_for_empty_payload(): void
    {
        $result = $this->makeService()->summarize([]);

        $this->assertSummaryShape($result);
        $this->assertEmpty($result['summary_lines']);
    }

    // =========================================================================
    // (7) Malformed/unexpected payload shape → graceful skip, no exception
    // =========================================================================

    /** @test */
    public function it_skips_malformed_sections_gracefully_without_throwing(): void
    {
        $payload = [
            'floodZones'      => 'not-an-array',
            'schoolDistricts' => null,
            'pois'            => [
                'not-an-array-entry',
                ['label' => 'Hospital', 'name' => 'St. Joseph Hospital'],
                ['label' => 'Park'],
                ['name' => 'Only Name No Label'],
            ],
            'commuteTimes'    => [
                ['destination' => 'Tampa', 'minutes' => null],
                ['destination' => '', 'minutes' => 10],
                ['destination' => 'Airport', 'minutes' => 15],
            ],
            'unexpectedKey'   => ['some' => 'data'],
        ];

        $result = $this->makeService()->summarize($payload);

        $this->assertSummaryShape($result);

        $this->assertNotContains('Flood Zone: not-an-array', $result['summary_lines']);
        $this->assertContains('Nearby Hospital: St. Joseph Hospital', $result['summary_lines']);
        $this->assertContains('Airport: 15 minutes', $result['summary_lines']);

        $this->assertNotContains('Nearby Park: ', $result['summary_lines']);
        $this->assertNotContains('Tampa: 10 minutes', $result['summary_lines']);
    }

    // =========================================================================
    // (8) Duplicate district names are deduplicated
    // =========================================================================

    /** @test */
    public function it_deduplicates_school_district_names(): void
    {
        $payload = [
            'schoolDistricts' => [
                ['name' => 'Pinellas County Schools'],
                ['name' => 'Hillsborough County Schools'],
                ['name' => 'Pinellas County Schools'],
                ['name' => 'Pinellas County Schools'],
            ],
        ];

        $result = $this->makeService()->summarize($payload);

        $this->assertSummaryShape($result);

        $districtLines = array_values(array_filter(
            $result['summary_lines'],
            fn (string $line) => str_starts_with($line, 'School District:'),
        ));

        $this->assertCount(2, $districtLines);
        $this->assertContains('School District: Pinellas County Schools', $districtLines);
        $this->assertContains('School District: Hillsborough County Schools', $districtLines);
    }

    // =========================================================================
    // Static governance check — no AI/OpenAI imports in the service file
    // =========================================================================

    /** @test */
    public function service_file_contains_no_openai_or_scoring_imports(): void
    {
        $serviceFile = file_get_contents(
            base_path('app/Services/LocationDna/LocationIntelligenceSummaryService.php'),
        );

        $this->assertDoesNotMatchRegularExpression('/^use\s+.*[Oo]pen[Aa][Ii]/m', $serviceFile, 'Must not import OpenAI classes');
        $this->assertDoesNotMatchRegularExpression('/^use\s+.*LifestyleScore/m', $serviceFile, 'Must not import scoring classes');
        $this->assertDoesNotMatchRegularExpression('/^use\s+.*MarketingContext/m', $serviceFile, 'Must not import marketing report classes');
        $this->assertStringNotContainsString('DB::', $serviceFile, 'Must not make DB calls');
        $this->assertDoesNotMatchRegularExpression('/^use\s+Illuminate\\\\Database\\\\Eloquent/m', $serviceFile, 'Must not import Eloquent');
    }

    // =========================================================================
    // Phase 4D — Alternate-key / dual-shape tests
    // =========================================================================

    // (9) Flood zone via zone_designation key

    /** @test */
    public function it_formats_flood_zone_from_zone_designation_key(): void
    {
        $payload = [
            'floodZones' => [
                ['zone_designation' => 'X'],
                ['zone_designation' => 'AE'],
            ],
        ];

        $result = $this->makeService()->summarize($payload);

        $this->assertSummaryShape($result);
        $this->assertContains('Flood Zone: X', $result['summary_lines']);
        $this->assertContains('Flood Zone: AE', $result['summary_lines']);
        $this->assertCount(2, $result['summary_lines']);
    }

    // (10) School district via district_name key

    /** @test */
    public function it_formats_school_district_from_district_name_key(): void
    {
        $payload = [
            'schoolDistricts' => [
                ['district_name' => 'Pinellas County Schools'],
                ['district_name' => 'Hillsborough County Schools'],
            ],
        ];

        $result = $this->makeService()->summarize($payload);

        $this->assertSummaryShape($result);
        $this->assertContains('School District: Pinellas County Schools', $result['summary_lines']);
        $this->assertContains('School District: Hillsborough County Schools', $result['summary_lines']);
        $this->assertCount(2, $result['summary_lines']);
    }

    // (11) POI via category key (name stays as-is)

    /** @test */
    public function it_formats_poi_from_category_and_name_keys(): void
    {
        $payload = [
            'pois' => [
                ['category' => 'Hospital', 'name' => 'Bay Pines VA Medical Center'],
                ['category' => 'Park',     'name' => 'Weedon Island Preserve'],
            ],
        ];

        $result = $this->makeService()->summarize($payload);

        $this->assertSummaryShape($result);
        $this->assertContains('Nearby Hospital: Bay Pines VA Medical Center', $result['summary_lines']);
        $this->assertContains('Nearby Park: Weedon Island Preserve', $result['summary_lines']);
        $this->assertCount(2, $result['summary_lines']);
    }

    // (12) Commute via destination_label + travel_time_minutes keys

    /** @test */
    public function it_formats_commute_from_destination_label_and_travel_time_minutes_keys(): void
    {
        $payload = [
            'commuteTimes' => [
                ['destination_label' => 'Downtown Tampa',             'travel_time_minutes' => 24],
                ['destination_label' => 'Tampa International Airport', 'travel_time_minutes' => 18],
            ],
        ];

        $result = $this->makeService()->summarize($payload);

        $this->assertSummaryShape($result);
        $this->assertContains('Downtown Tampa: 24 minutes', $result['summary_lines']);
        $this->assertContains('Tampa International Airport: 18 minutes', $result['summary_lines']);
        $this->assertCount(2, $result['summary_lines']);
    }

    // (13) Mixed old and new key payload across all four sections

    /** @test */
    public function it_handles_mixed_old_and_new_key_payload(): void
    {
        $payload = [
            'floodZones' => [
                ['zone'             => 'X'],
                ['zone_designation' => 'AE'],
            ],
            'schoolDistricts' => [
                ['name'          => 'Pinellas County Schools'],
                ['district_name' => 'Hillsborough County Schools'],
            ],
            'pois' => [
                ['label'    => 'Beach',    'name' => 'Clearwater Beach'],
                ['category' => 'Hospital', 'name' => 'Bay Pines VA Medical Center'],
            ],
            'commuteTimes' => [
                ['destination'       => 'Downtown Tampa',             'minutes'              => 30],
                ['destination_label' => 'Tampa International Airport', 'travel_time_minutes' => 18],
            ],
        ];

        $result = $this->makeService()->summarize($payload);

        $this->assertSummaryShape($result);
        $this->assertContains('Flood Zone: X', $result['summary_lines']);
        $this->assertContains('Flood Zone: AE', $result['summary_lines']);
        $this->assertContains('School District: Pinellas County Schools', $result['summary_lines']);
        $this->assertContains('School District: Hillsborough County Schools', $result['summary_lines']);
        $this->assertContains('Nearby Beach: Clearwater Beach', $result['summary_lines']);
        $this->assertContains('Nearby Hospital: Bay Pines VA Medical Center', $result['summary_lines']);
        $this->assertContains('Downtown Tampa: 30 minutes', $result['summary_lines']);
        $this->assertContains('Tampa International Airport: 18 minutes', $result['summary_lines']);
        $this->assertCount(8, $result['summary_lines']);
    }

    // (14) Malformed entries with new key shapes are still skipped

    /** @test */
    public function it_skips_malformed_entries_with_new_key_shapes(): void
    {
        $payload = [
            'floodZones' => [
                ['zone_designation' => ''],
                ['zone_designation' => null],
                ['zone_designation' => 'X'],
            ],
            'schoolDistricts' => [
                ['district_name' => ''],
                ['district_name' => null],
                ['district_name' => 'Pinellas County Schools'],
            ],
            'pois' => [
                ['category' => '',        'name' => 'Some Place'],
                ['category' => 'Hospital', 'name' => ''],
                ['category' => 'Hospital', 'name' => 'Bay Pines VA Medical Center'],
            ],
            'commuteTimes' => [
                ['destination_label' => '',        'travel_time_minutes' => 10],
                ['destination_label' => 'Airport', 'travel_time_minutes' => null],
                ['destination_label' => 'Airport', 'travel_time_minutes' => 15],
            ],
        ];

        $result = $this->makeService()->summarize($payload);

        $this->assertSummaryShape($result);
        $this->assertContains('Flood Zone: X', $result['summary_lines']);
        $this->assertContains('School District: Pinellas County Schools', $result['summary_lines']);
        $this->assertContains('Nearby Hospital: Bay Pines VA Medical Center', $result['summary_lines']);
        $this->assertContains('Airport: 15 minutes', $result['summary_lines']);
        $this->assertCount(4, $result['summary_lines']);
    }

    // (15) Duplicate district_name entries are deduplicated

    /** @test */
    public function it_deduplicates_district_name_key_entries(): void
    {
        $payload = [
            'schoolDistricts' => [
                ['district_name' => 'Pinellas County Schools'],
                ['district_name' => 'Hillsborough County Schools'],
                ['district_name' => 'Pinellas County Schools'],
                ['district_name' => 'Pinellas County Schools'],
            ],
        ];

        $result = $this->makeService()->summarize($payload);

        $this->assertSummaryShape($result);

        $districtLines = array_values(array_filter(
            $result['summary_lines'],
            fn (string $line) => str_starts_with($line, 'School District:'),
        ));

        $this->assertCount(2, $districtLines);
        $this->assertContains('School District: Pinellas County Schools', $districtLines);
        $this->assertContains('School District: Hillsborough County Schools', $districtLines);
    }

    // (16) Governance: no DB or Eloquent imports in the service file

    /** @test */
    public function service_file_contains_no_db_or_eloquent_imports(): void
    {
        $serviceFile = file_get_contents(
            base_path('app/Services/LocationDna/LocationIntelligenceSummaryService.php'),
        );

        $this->assertStringNotContainsString('DB::', $serviceFile, 'Must not make DB calls');
        $this->assertDoesNotMatchRegularExpression('/^use\s+Illuminate\\\\Database\\\\Eloquent/m', $serviceFile, 'Must not import Eloquent');
        $this->assertDoesNotMatchRegularExpression('/^use\s+Illuminate\\\\Support\\\\Facades\\\\DB/m', $serviceFile, 'Must not import DB facade');
    }
}
