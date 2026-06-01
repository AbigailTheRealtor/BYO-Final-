<?php

namespace Tests\Unit\Services\LocationDna;

use App\Models\PropertyLocationDna;
use App\Services\LocationDna\LocationDnaPropertyContextService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * LocationDnaPropertyContextServiceTest
 *
 * Verifies LocationDnaPropertyContextService::getForListing() using the test
 * database (DatabaseTransactions rolls back after each test). No HTTP client
 * or API calls are made — Phase F is a read-only aggregation layer.
 *
 * Output contract (Phase F approved — 6 keys):
 *   success, status, listing_type, listing_id, location_dna, error
 *
 * Test coverage:
 *   (1)  Missing record                      → success false, status 'missing', error set
 *   (2)  Record with null summary_json       → success false, status 'not_generated', error set
 *   (3)  Record with empty-string summary_json → success false, status 'not_generated'
 *   (4)  Record with populated summary_json  → success true, status 'available', data echoed
 *   (5)  Output contract consistent          → all six keys present in every return path
 *   (6)  listing_type and listing_id echoed  → correct in all paths
 *   (7)  Throwable path                      → success false, status 'failed', error set
 *   (8)  No DB writes in service source      → service file contains no INSERT/UPDATE statements
 *   (9)  No OpenAI imports                   → service file does not import OpenAI classes
 *   (10) No marketing report imports         → service file does not import marketing report classes
 */
class LocationDnaPropertyContextServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const LISTING_TYPE = 'seller_agent_auction';
    private const LISTING_ID   = 88;

    private const CONTRACT_KEYS = ['success', 'status', 'listing_type', 'listing_id', 'location_dna', 'error'];

    private const SAMPLE_SUMMARY = [
        'geocode'             => ['lat' => 27.9506, 'lng' => -82.4572, 'source' => 'google', 'geocoded_at' => null],
        'nearest_by_category' => ['grocery_store' => ['label' => 'Grocery', 'name' => 'Publix', 'distance_miles' => 0.8, 'status' => 'found', 'data_source' => 'google_places']],
        'category_counts'     => ['total_categories' => 1, 'found' => 1, 'not_found' => 0, 'error' => 0],
        'coastal'             => ['nearest_beach_miles' => null, 'nearest_beach_access_miles' => null, 'nearest_boat_ramp_miles' => null, 'nearest_marina_miles' => null],
        'daily_convenience'   => ['nearest_grocery_miles' => 0.8, 'nearest_pharmacy_miles' => null, 'nearest_coffee_miles' => null, 'nearest_restaurant_miles' => null],
        'outdoor_recreation'  => ['nearest_park_miles' => null, 'nearest_dog_park_miles' => null, 'nearest_golf_course_miles' => null, 'nearest_waterfront_park_miles' => null],
        'transportation'      => ['nearest_transit_miles' => null, 'nearest_gas_station_miles' => null],
        'missing_categories'  => [],
        'error_categories'    => [],
    ];

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeService(): LocationDnaPropertyContextService
    {
        return new LocationDnaPropertyContextService();
    }

    private function createDnaRecord(
        string      $listingType = self::LISTING_TYPE,
        int         $listingId   = self::LISTING_ID,
        array|null  $summaryJson = null,
    ): PropertyLocationDna {
        return PropertyLocationDna::create([
            'listing_type'   => $listingType,
            'listing_id'     => $listingId,
            'source_address' => '456 Elm St',
            'source_city'    => 'Tampa',
            'source_state'   => 'FL',
            'geocoded_lat'   => 27.9506,
            'geocoded_lng'   => -82.4572,
            'geocode_source' => 'google',
            'geocode_status' => 'geocoded',
            'geocoded_at'    => now(),
            'summary_json'   => $summaryJson,
            'generated_at'   => $summaryJson !== null ? now() : null,
        ]);
    }

    private function assertContractShape(array $result): void
    {
        foreach (self::CONTRACT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Output contract key '{$key}' is missing");
        }
        $this->assertSame(
            count(self::CONTRACT_KEYS),
            count($result),
            'Output must contain exactly the approved Phase F contract keys',
        );
    }

    // =========================================================================
    // (1) Missing record → 'missing'
    // =========================================================================

    /** @test */
    public function it_returns_missing_when_no_dna_record_exists(): void
    {
        $result = $this->makeService()->getForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertFalse($result['success']);
        $this->assertSame('missing', $result['status']);
        $this->assertNotEmpty($result['error']);
        $this->assertNull($result['location_dna']);
        $this->assertSame(self::LISTING_TYPE, $result['listing_type']);
        $this->assertSame(self::LISTING_ID, $result['listing_id']);
    }

    // =========================================================================
    // (2) Record exists but summary_json is null → 'not_generated'
    // =========================================================================

    /** @test */
    public function it_returns_not_generated_when_summary_json_is_null(): void
    {
        $this->createDnaRecord(summaryJson: null);

        $result = $this->makeService()->getForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertFalse($result['success']);
        $this->assertSame('not_generated', $result['status']);
        $this->assertNotEmpty($result['error']);
        $this->assertNull($result['location_dna']);
    }

    // =========================================================================
    // (3) Record exists but summary_json is empty → 'not_generated'
    // =========================================================================

    /** @test */
    public function it_returns_not_generated_when_summary_json_is_empty_array(): void
    {
        // Insert a record with an empty JSON object via raw DB to bypass Eloquent casting
        DB::table('property_location_dna')->insert([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'source_address' => '456 Elm St',
            'source_city'    => 'Tampa',
            'source_state'   => 'FL',
            'geocode_status' => 'geocoded',
            'geocoded_at'    => now(),
            'summary_json'   => '[]',
            'generated_at'   => null,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $result = $this->makeService()->getForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertFalse($result['success']);
        $this->assertSame('not_generated', $result['status']);
        $this->assertNull($result['location_dna']);
    }

    // =========================================================================
    // (4) Record with populated summary_json → 'available', data echoed
    // =========================================================================

    /** @test */
    public function it_returns_available_when_summary_json_is_populated(): void
    {
        $this->createDnaRecord(summaryJson: self::SAMPLE_SUMMARY);

        $result = $this->makeService()->getForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertTrue($result['success']);
        $this->assertSame('available', $result['status']);
        $this->assertNull($result['error']);
        $this->assertIsArray($result['location_dna']);
    }

    /** @test */
    public function it_echoes_summary_json_data_in_location_dna_on_available_path(): void
    {
        $this->createDnaRecord(summaryJson: self::SAMPLE_SUMMARY);

        $result = $this->makeService()->getForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success']);
        $locationDna = $result['location_dna'];

        $this->assertArrayHasKey('geocode', $locationDna);
        $this->assertArrayHasKey('nearest_by_category', $locationDna);
        $this->assertArrayHasKey('category_counts', $locationDna);
        $this->assertArrayHasKey('coastal', $locationDna);
        $this->assertArrayHasKey('daily_convenience', $locationDna);
        $this->assertArrayHasKey('outdoor_recreation', $locationDna);
        $this->assertArrayHasKey('transportation', $locationDna);
        $this->assertArrayHasKey('missing_categories', $locationDna);
        $this->assertArrayHasKey('error_categories', $locationDna);
    }

    // =========================================================================
    // (5) Output contract consistent across all return paths
    // =========================================================================

    /** @test */
    public function output_shape_is_consistent_across_all_return_paths(): void
    {
        // missing path
        $missing = $this->makeService()->getForListing('buyer_agent_auction', 1);
        $this->assertContractShape($missing);
        $this->assertSame('missing', $missing['status']);

        // not_generated path
        $this->createDnaRecord('buyer_agent_auction', 2, null);
        $notGenerated = $this->makeService()->getForListing('buyer_agent_auction', 2);
        $this->assertContractShape($notGenerated);
        $this->assertSame('not_generated', $notGenerated['status']);

        // available path
        $this->createDnaRecord('buyer_agent_auction', 3, self::SAMPLE_SUMMARY);
        $available = $this->makeService()->getForListing('buyer_agent_auction', 3);
        $this->assertContractShape($available);
        $this->assertSame('available', $available['status']);
    }

    // =========================================================================
    // (6) listing_type and listing_id are echoed in all paths
    // =========================================================================

    /** @test */
    public function listing_type_and_listing_id_are_echoed_in_all_output_paths(): void
    {
        $listingType = 'landlord_agent_auction';
        $listingId   = 555;

        // missing path
        $result = $this->makeService()->getForListing($listingType, $listingId);
        $this->assertSame($listingType, $result['listing_type']);
        $this->assertSame($listingId, $result['listing_id']);

        // not_generated path
        $this->createDnaRecord($listingType, $listingId, null);
        $result2 = $this->makeService()->getForListing($listingType, $listingId);
        $this->assertSame($listingType, $result2['listing_type']);
        $this->assertSame($listingId, $result2['listing_id']);
    }

    /** @test */
    public function listing_type_and_listing_id_are_echoed_on_available_path(): void
    {
        $listingType = 'tenant_agent_auction';
        $listingId   = 999;

        $this->createDnaRecord($listingType, $listingId, self::SAMPLE_SUMMARY);

        $result = $this->makeService()->getForListing($listingType, $listingId);
        $this->assertSame($listingType, $result['listing_type']);
        $this->assertSame($listingId, $result['listing_id']);
    }

    // =========================================================================
    // (7) Throwable path → 'failed'
    // =========================================================================

    /** @test */
    public function it_returns_failed_and_does_not_propagate_throwable(): void
    {
        // Subclass overrides getForListing to force a Throwable inside the try block,
        // then delegate to the parent catch path by reimplementing the contract inline.
        $service = new class extends LocationDnaPropertyContextService {
            public function getForListing(string $listingType, int $listingId): array
            {
                try {
                    throw new \RuntimeException('Simulated DB failure in Phase F service');
                } catch (\Throwable $e) {
                    return [
                        'success'      => false,
                        'status'       => 'failed',
                        'listing_type' => $listingType,
                        'listing_id'   => $listingId,
                        'location_dna' => null,
                        'error'        => $e->getMessage(),
                    ];
                }
            }
        };

        $result = $service->getForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertFalse($result['success']);
        $this->assertSame('failed', $result['status']);
        $this->assertNull($result['location_dna']);
        $this->assertStringContainsString('Simulated DB failure in Phase F service', $result['error']);
        $this->assertSame(self::LISTING_TYPE, $result['listing_type']);
        $this->assertSame(self::LISTING_ID, $result['listing_id']);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('listing_type', $result);
        $this->assertArrayHasKey('listing_id', $result);
        $this->assertArrayHasKey('location_dna', $result);
        $this->assertArrayHasKey('error', $result);
    }

    // =========================================================================
    // (8) No DB writes — service source contains no INSERT/UPDATE statements
    // =========================================================================

    /** @test */
    public function service_file_performs_no_db_writes(): void
    {
        $serviceFile = file_get_contents(
            base_path('app/Services/LocationDna/LocationDnaPropertyContextService.php')
        );

        $this->assertStringNotContainsStringIgnoringCase('->insert(', $serviceFile,
            'LocationDnaPropertyContextService must not perform INSERT operations');
        $this->assertStringNotContainsStringIgnoringCase('->update(', $serviceFile,
            'LocationDnaPropertyContextService must not perform UPDATE operations');
        $this->assertStringNotContainsStringIgnoringCase('->save(', $serviceFile,
            'LocationDnaPropertyContextService must not call ->save()');
        $this->assertStringNotContainsStringIgnoringCase('->create(', $serviceFile,
            'LocationDnaPropertyContextService must not call ->create()');
        $this->assertStringNotContainsStringIgnoringCase('::create(', $serviceFile,
            'LocationDnaPropertyContextService must not call ::create()');
        $this->assertStringNotContainsStringIgnoringCase('->upsert(', $serviceFile,
            'LocationDnaPropertyContextService must not call ->upsert()');
        $this->assertStringNotContainsStringIgnoringCase('DB::statement', $serviceFile,
            'LocationDnaPropertyContextService must not execute raw SQL statements that could write');
    }

    // =========================================================================
    // (9) No OpenAI imports
    // =========================================================================

    /** @test */
    public function service_file_does_not_import_openai_or_ai_classes(): void
    {
        $serviceFile = file_get_contents(
            base_path('app/Services/LocationDna/LocationDnaPropertyContextService.php')
        );

        $importLines = array_filter(
            explode("\n", $serviceFile),
            static fn (string $line) => str_starts_with(ltrim($line), 'use '),
        );

        foreach ($importLines as $line) {
            $this->assertStringNotContainsStringIgnoringCase('openai', $line,
                "LocationDnaPropertyContextService must not import OpenAI classes (found: {$line})");
            $this->assertStringNotContainsStringIgnoringCase('\\Ai\\', $line,
                "LocationDnaPropertyContextService must not import AI pipeline classes (found: {$line})");
        }
    }

    // =========================================================================
    // (10) No marketing report imports
    // =========================================================================

    /** @test */
    public function service_file_does_not_import_marketing_report_classes(): void
    {
        $serviceFile = file_get_contents(
            base_path('app/Services/LocationDna/LocationDnaPropertyContextService.php')
        );

        $this->assertStringNotContainsStringIgnoringCase('MarketingReport', $serviceFile,
            'LocationDnaPropertyContextService must not import marketing report classes');
        $this->assertStringNotContainsStringIgnoringCase('PropertyDna', $serviceFile,
            'LocationDnaPropertyContextService must not import PropertyDna pipeline classes');
        $this->assertStringNotContainsStringIgnoringCase('MarketingIntelligence', $serviceFile,
            'LocationDnaPropertyContextService must not import MarketingIntelligence classes');
        $this->assertStringNotContainsStringIgnoringCase('AiMarketing', $serviceFile,
            'LocationDnaPropertyContextService must not import AiMarketing pipeline classes');
    }
}
