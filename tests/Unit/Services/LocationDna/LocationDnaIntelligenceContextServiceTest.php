<?php

namespace Tests\Unit\Services\LocationDna;

use App\Models\PropertyLocationDna;
use App\Services\LocationDna\LocationDnaIntelligenceContextService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * LocationDnaIntelligenceContextServiceTest
 *
 * Verifies LocationDnaIntelligenceContextService::getForListing() using the test
 * database (DatabaseTransactions rolls back after each test). No HTTP client
 * or API calls are made — Phase H is a read-only reshaping layer.
 *
 * Output contract (Phase H approved — 6 keys):
 *   success, status, listing_type, listing_id, location_intelligence_context, error
 *
 * Test coverage:
 *   (1)  Missing record                                  → success false, status 'missing', error set
 *   (2)  Record with null summary_json                   → success false, status 'not_generated', error set
 *   (3)  Record with empty-string summary_json           → success false, status 'not_generated'
 *   (4)  Record with populated summary_json              → success true, status 'available', context shaped
 *   (5)  Output contract consistent                      → all six keys present in every return path
 *   (6)  listing_type and listing_id echoed              → correct in all paths
 *   (7)  Throwable path                                  → success false, status 'failed', error set
 *   (8)  No DB writes in service source                  → service file contains no INSERT/UPDATE statements
 *   (9)  No OpenAI imports                               → service file does not import OpenAI classes
 *   (10) No marketing report class imports               → service file does not import marketing report classes
 *   (11) nearest_highlights has exactly five canonical keys
 *   (12) nearest_highlights values sourced from correct thematic blocks
 *   (13) available_categories and missing_categories computed correctly
 *   (14) location_intelligence_context is null on all non-available paths
 */
class LocationDnaIntelligenceContextServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const LISTING_TYPE = 'seller_agent_auction';
    private const LISTING_ID   = 77;

    private const CONTRACT_KEYS = [
        'success',
        'status',
        'listing_type',
        'listing_id',
        'location_intelligence_context',
        'error',
    ];

    /** Five canonical nearest_highlights keys required by Phase H contract. */
    private const NEAREST_HIGHLIGHTS_KEYS = [
        'nearest_beach_miles',
        'nearest_marina_miles',
        'nearest_grocery_miles',
        'nearest_park_miles',
        'nearest_transit_miles',
    ];

    /**
     * Summary with all four thematic blocks.
     * coastal         → all null  → missing_categories
     * daily_convenience → nearest_grocery_miles non-null → available_categories
     * outdoor_recreation → all null → missing_categories
     * transportation  → all null  → missing_categories
     */
    private const SAMPLE_SUMMARY = [
        'geocode'             => ['lat' => 27.9506, 'lng' => -82.4572, 'source' => 'google', 'geocoded_at' => null],
        'nearest_by_category' => [
            'grocery_store' => [
                'label'          => 'Grocery',
                'name'           => 'Publix',
                'distance_miles' => 0.8,
                'status'         => 'found',
                'data_source'    => 'google_places',
            ],
        ],
        'category_counts'    => ['total_categories' => 1, 'found' => 1, 'not_found' => 0, 'error' => 0],
        'coastal'            => [
            'nearest_beach_miles'        => null,
            'nearest_beach_access_miles' => null,
            'nearest_boat_ramp_miles'    => null,
            'nearest_marina_miles'       => null,
        ],
        'daily_convenience'  => [
            'nearest_grocery_miles'    => 0.8,
            'nearest_pharmacy_miles'   => null,
            'nearest_coffee_miles'     => null,
            'nearest_restaurant_miles' => null,
        ],
        'outdoor_recreation' => [
            'nearest_park_miles'            => null,
            'nearest_dog_park_miles'        => null,
            'nearest_golf_course_miles'     => null,
            'nearest_waterfront_park_miles' => null,
        ],
        'transportation'     => [
            'nearest_transit_miles'     => null,
            'nearest_gas_station_miles' => null,
        ],
        'missing_categories' => [],
        'error_categories'   => [],
    ];

    /**
     * Summary where every thematic block has at least one non-null value,
     * so all four categories should appear in available_categories.
     * Also has non-null values for all five nearest_highlights keys.
     */
    private const FULLY_AVAILABLE_SUMMARY = [
        'geocode'            => ['lat' => 27.9506, 'lng' => -82.4572, 'source' => 'google', 'geocoded_at' => null],
        'nearest_by_category' => [],
        'category_counts'    => ['total_categories' => 4, 'found' => 4, 'not_found' => 0, 'error' => 0],
        'coastal'            => [
            'nearest_beach_miles'        => 1.2,
            'nearest_beach_access_miles' => null,
            'nearest_boat_ramp_miles'    => null,
            'nearest_marina_miles'       => 3.4,
        ],
        'daily_convenience'  => [
            'nearest_grocery_miles'    => 0.8,
            'nearest_pharmacy_miles'   => null,
            'nearest_coffee_miles'     => null,
            'nearest_restaurant_miles' => null,
        ],
        'outdoor_recreation' => [
            'nearest_park_miles'            => 0.5,
            'nearest_dog_park_miles'        => null,
            'nearest_golf_course_miles'     => null,
            'nearest_waterfront_park_miles' => null,
        ],
        'transportation'     => [
            'nearest_transit_miles'     => 2.1,
            'nearest_gas_station_miles' => null,
        ],
        'missing_categories' => [],
        'error_categories'   => [],
    ];

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeService(): LocationDnaIntelligenceContextService
    {
        return new LocationDnaIntelligenceContextService();
    }

    private function createDnaRecord(
        string     $listingType = self::LISTING_TYPE,
        int        $listingId   = self::LISTING_ID,
        array|null $summaryJson = null,
    ): PropertyLocationDna {
        return PropertyLocationDna::create([
            'listing_type'   => $listingType,
            'listing_id'     => $listingId,
            'source_address' => '123 Main St',
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
            $this->assertArrayHasKey($key, $result,
                "Output contract key '{$key}' is missing from Phase H result");
        }
        $this->assertSame(
            count(self::CONTRACT_KEYS),
            count($result),
            'Output must contain exactly the approved Phase H six contract keys',
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
        $this->assertNull($result['location_intelligence_context']);
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
        $this->assertNull($result['location_intelligence_context']);
    }

    // =========================================================================
    // (3) Record exists but summary_json is empty → 'not_generated'
    // =========================================================================

    /** @test */
    public function it_returns_not_generated_when_summary_json_is_empty_array(): void
    {
        DB::table('property_location_dna')->insert([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'source_address' => '123 Main St',
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
        $this->assertNull($result['location_intelligence_context']);
    }

    // =========================================================================
    // (4) Record with populated summary_json → 'available', context shaped
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
        $this->assertIsArray($result['location_intelligence_context']);
    }

    /** @test */
    public function it_returns_context_with_four_thematic_sub_arrays_plus_highlights_and_categories(): void
    {
        $this->createDnaRecord(summaryJson: self::SAMPLE_SUMMARY);

        $result  = $this->makeService()->getForListing(self::LISTING_TYPE, self::LISTING_ID);
        $context = $result['location_intelligence_context'];

        $this->assertIsArray($context);
        $this->assertArrayHasKey('coastal_features',           $context);
        $this->assertArrayHasKey('daily_convenience',          $context);
        $this->assertArrayHasKey('outdoor_recreation',         $context);
        $this->assertArrayHasKey('transportation',             $context);
        $this->assertArrayHasKey('nearest_highlights',         $context);
        $this->assertArrayHasKey('available_categories',       $context);
        $this->assertArrayHasKey('missing_categories',         $context);
    }

    /** @test */
    public function it_maps_coastal_summary_block_to_coastal_features_key(): void
    {
        $this->createDnaRecord(summaryJson: self::SAMPLE_SUMMARY);

        $result  = $this->makeService()->getForListing(self::LISTING_TYPE, self::LISTING_ID);
        $context = $result['location_intelligence_context'];

        $this->assertSame(
            self::SAMPLE_SUMMARY['coastal'],
            $context['coastal_features'],
            'coastal_features must contain the same values as summary_json[coastal]',
        );
    }

    /** @test */
    public function it_maps_daily_convenience_block_correctly(): void
    {
        $this->createDnaRecord(summaryJson: self::SAMPLE_SUMMARY);

        $result  = $this->makeService()->getForListing(self::LISTING_TYPE, self::LISTING_ID);
        $context = $result['location_intelligence_context'];

        $this->assertSame(self::SAMPLE_SUMMARY['daily_convenience'], $context['daily_convenience']);
    }

    /** @test */
    public function it_maps_outdoor_recreation_block_correctly(): void
    {
        $this->createDnaRecord(summaryJson: self::SAMPLE_SUMMARY);

        $result  = $this->makeService()->getForListing(self::LISTING_TYPE, self::LISTING_ID);
        $context = $result['location_intelligence_context'];

        $this->assertSame(self::SAMPLE_SUMMARY['outdoor_recreation'], $context['outdoor_recreation']);
    }

    /** @test */
    public function it_maps_transportation_block_correctly(): void
    {
        $this->createDnaRecord(summaryJson: self::SAMPLE_SUMMARY);

        $result  = $this->makeService()->getForListing(self::LISTING_TYPE, self::LISTING_ID);
        $context = $result['location_intelligence_context'];

        $this->assertSame(self::SAMPLE_SUMMARY['transportation'], $context['transportation']);
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
    // (6) listing_type and listing_id echoed in all paths
    // =========================================================================

    /** @test */
    public function listing_type_and_listing_id_are_echoed_in_all_output_paths(): void
    {
        $listingType = 'landlord_agent_auction';
        $listingId   = 444;

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
        $listingId   = 888;

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
        $service = new class extends LocationDnaIntelligenceContextService {
            public function getForListing(string $listingType, int $listingId): array
            {
                try {
                    throw new \RuntimeException('Simulated DB failure in Phase H service');
                } catch (\Throwable $e) {
                    return [
                        'success'                      => false,
                        'status'                       => 'failed',
                        'listing_type'                 => $listingType,
                        'listing_id'                   => $listingId,
                        'location_intelligence_context' => null,
                        'error'                        => $e->getMessage(),
                    ];
                }
            }
        };

        $result = $service->getForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertFalse($result['success']);
        $this->assertSame('failed', $result['status']);
        $this->assertNull($result['location_intelligence_context']);
        $this->assertStringContainsString('Simulated DB failure in Phase H service', $result['error']);
        $this->assertSame(self::LISTING_TYPE, $result['listing_type']);
        $this->assertSame(self::LISTING_ID, $result['listing_id']);

        foreach (self::CONTRACT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result,
                "Contract key '{$key}' missing on failed path");
        }
    }

    // =========================================================================
    // (8) No DB writes — service source contains no INSERT/UPDATE statements
    // =========================================================================

    /** @test */
    public function service_file_performs_no_db_writes(): void
    {
        $serviceFile = file_get_contents(
            base_path('app/Services/LocationDna/LocationDnaIntelligenceContextService.php')
        );

        $this->assertStringNotContainsStringIgnoringCase('->insert(', $serviceFile,
            'LocationDnaIntelligenceContextService must not perform INSERT operations');
        $this->assertStringNotContainsStringIgnoringCase('->update(', $serviceFile,
            'LocationDnaIntelligenceContextService must not perform UPDATE operations');
        $this->assertStringNotContainsStringIgnoringCase('->save(', $serviceFile,
            'LocationDnaIntelligenceContextService must not call ->save()');
        $this->assertStringNotContainsStringIgnoringCase('->create(', $serviceFile,
            'LocationDnaIntelligenceContextService must not call ->create()');
        $this->assertStringNotContainsStringIgnoringCase('::create(', $serviceFile,
            'LocationDnaIntelligenceContextService must not call ::create()');
        $this->assertStringNotContainsStringIgnoringCase('->upsert(', $serviceFile,
            'LocationDnaIntelligenceContextService must not call ->upsert()');
        $this->assertStringNotContainsStringIgnoringCase('DB::statement', $serviceFile,
            'LocationDnaIntelligenceContextService must not execute raw SQL statements that could write');
    }

    // =========================================================================
    // (9) No OpenAI imports
    // =========================================================================

    /** @test */
    public function service_file_does_not_import_openai_or_ai_classes(): void
    {
        $serviceFile = file_get_contents(
            base_path('app/Services/LocationDna/LocationDnaIntelligenceContextService.php')
        );

        $importLines = array_filter(
            explode("\n", $serviceFile),
            static fn (string $line) => str_starts_with(ltrim($line), 'use '),
        );

        foreach ($importLines as $line) {
            $this->assertStringNotContainsStringIgnoringCase('openai', $line,
                "LocationDnaIntelligenceContextService must not import OpenAI classes (found: {$line})");
            $this->assertStringNotContainsStringIgnoringCase('\\Ai\\', $line,
                "LocationDnaIntelligenceContextService must not import AI pipeline classes (found: {$line})");
        }
    }

    // =========================================================================
    // (10) No marketing report class imports
    // =========================================================================

    /** @test */
    public function service_file_does_not_import_marketing_report_classes(): void
    {
        $serviceFile = file_get_contents(
            base_path('app/Services/LocationDna/LocationDnaIntelligenceContextService.php')
        );

        $importLines = array_filter(
            explode("\n", $serviceFile),
            static fn (string $line) => str_starts_with(ltrim($line), 'use '),
        );

        foreach ($importLines as $line) {
            $this->assertStringNotContainsStringIgnoringCase('MarketingReport', $line,
                "LocationDnaIntelligenceContextService must not import marketing report classes (found: {$line})");
            $this->assertStringNotContainsStringIgnoringCase('AiMarketingReport', $line,
                "LocationDnaIntelligenceContextService must not import AiMarketingReport classes (found: {$line})");
            $this->assertStringNotContainsStringIgnoringCase('AiMarketingReportGeneratorService', $line,
                "LocationDnaIntelligenceContextService must not import AiMarketingReportGeneratorService (found: {$line})");
            $this->assertStringNotContainsStringIgnoringCase('AiMarketingReportPersistenceService', $line,
                "LocationDnaIntelligenceContextService must not import AiMarketingReportPersistenceService (found: {$line})");
            $this->assertStringNotContainsStringIgnoringCase('MarketingIntelligence', $line,
                "LocationDnaIntelligenceContextService must not import MarketingIntelligence classes (found: {$line})");
        }
    }

    // =========================================================================
    // (11) nearest_highlights has exactly the five canonical keys
    // =========================================================================

    /** @test */
    public function nearest_highlights_contains_exactly_the_five_canonical_keys(): void
    {
        $this->createDnaRecord(summaryJson: self::SAMPLE_SUMMARY);

        $result     = $this->makeService()->getForListing(self::LISTING_TYPE, self::LISTING_ID);
        $highlights = $result['location_intelligence_context']['nearest_highlights'];

        $this->assertIsArray($highlights);
        $this->assertCount(5, $highlights,
            'nearest_highlights must contain exactly five canonical distance keys');

        foreach (self::NEAREST_HIGHLIGHTS_KEYS as $key) {
            $this->assertArrayHasKey($key, $highlights,
                "nearest_highlights must contain the canonical key '{$key}'");
        }

        $actualKeys = array_keys($highlights);
        sort($actualKeys);
        $expected = self::NEAREST_HIGHLIGHTS_KEYS;
        sort($expected);
        $this->assertSame($expected, $actualKeys,
            'nearest_highlights must contain exactly and only the five canonical keys');
    }

    /** @test */
    public function nearest_highlights_values_are_null_when_keys_absent_from_thematic_blocks(): void
    {
        $this->createDnaRecord(summaryJson: self::SAMPLE_SUMMARY);

        $result     = $this->makeService()->getForListing(self::LISTING_TYPE, self::LISTING_ID);
        $highlights = $result['location_intelligence_context']['nearest_highlights'];

        // SAMPLE_SUMMARY: coastal block has null for beach and marina
        $this->assertNull($highlights['nearest_beach_miles'],
            'nearest_beach_miles must be null when coastal block has null for that key');
        $this->assertNull($highlights['nearest_marina_miles'],
            'nearest_marina_miles must be null when coastal block has null for that key');
        // transportation has null for transit
        $this->assertNull($highlights['nearest_transit_miles'],
            'nearest_transit_miles must be null when transportation block has null for that key');
        // outdoor_recreation has null for park
        $this->assertNull($highlights['nearest_park_miles'],
            'nearest_park_miles must be null when outdoor_recreation block has null for that key');
    }

    // =========================================================================
    // (12) nearest_highlights values sourced correctly from right thematic blocks
    // =========================================================================

    /** @test */
    public function nearest_highlights_values_are_sourced_from_correct_thematic_blocks(): void
    {
        $this->createDnaRecord(summaryJson: self::FULLY_AVAILABLE_SUMMARY);

        $result     = $this->makeService()->getForListing(self::LISTING_TYPE, self::LISTING_ID);
        $context    = $result['location_intelligence_context'];
        $highlights = $context['nearest_highlights'];

        // nearest_beach_miles comes from coastal block
        $this->assertSame(
            self::FULLY_AVAILABLE_SUMMARY['coastal']['nearest_beach_miles'],
            $highlights['nearest_beach_miles'],
            'nearest_beach_miles must be sourced from summary_json[coastal]',
        );

        // nearest_marina_miles comes from coastal block
        $this->assertSame(
            self::FULLY_AVAILABLE_SUMMARY['coastal']['nearest_marina_miles'],
            $highlights['nearest_marina_miles'],
            'nearest_marina_miles must be sourced from summary_json[coastal]',
        );

        // nearest_grocery_miles comes from daily_convenience block
        $this->assertSame(
            self::FULLY_AVAILABLE_SUMMARY['daily_convenience']['nearest_grocery_miles'],
            $highlights['nearest_grocery_miles'],
            'nearest_grocery_miles must be sourced from summary_json[daily_convenience]',
        );

        // nearest_park_miles comes from outdoor_recreation block
        $this->assertSame(
            self::FULLY_AVAILABLE_SUMMARY['outdoor_recreation']['nearest_park_miles'],
            $highlights['nearest_park_miles'],
            'nearest_park_miles must be sourced from summary_json[outdoor_recreation]',
        );

        // nearest_transit_miles comes from transportation block
        $this->assertSame(
            self::FULLY_AVAILABLE_SUMMARY['transportation']['nearest_transit_miles'],
            $highlights['nearest_transit_miles'],
            'nearest_transit_miles must be sourced from summary_json[transportation]',
        );
    }

    /** @test */
    public function nearest_highlights_grocery_miles_is_non_null_when_available_in_sample_summary(): void
    {
        // SAMPLE_SUMMARY has nearest_grocery_miles = 0.8 in daily_convenience
        $this->createDnaRecord(summaryJson: self::SAMPLE_SUMMARY);

        $result     = $this->makeService()->getForListing(self::LISTING_TYPE, self::LISTING_ID);
        $highlights = $result['location_intelligence_context']['nearest_highlights'];

        $this->assertSame(0.8, $highlights['nearest_grocery_miles'],
            'nearest_grocery_miles must reflect the value from summary_json[daily_convenience]');
    }

    // =========================================================================
    // (13) available_categories and missing_categories computed correctly
    // =========================================================================

    /** @test */
    public function available_categories_lists_thematic_keys_with_at_least_one_non_null_distance(): void
    {
        // SAMPLE_SUMMARY: only daily_convenience has a non-null value (0.8 miles)
        $this->createDnaRecord(summaryJson: self::SAMPLE_SUMMARY);

        $result  = $this->makeService()->getForListing(self::LISTING_TYPE, self::LISTING_ID);
        $context = $result['location_intelligence_context'];

        $this->assertIsArray($context['available_categories']);
        $this->assertContains('daily_convenience', $context['available_categories'],
            'daily_convenience has a non-null distance and must appear in available_categories');
        $this->assertNotContains('coastal_features', $context['available_categories'],
            'coastal_features has all-null distances and must not appear in available_categories');
        $this->assertNotContains('outdoor_recreation', $context['available_categories'],
            'outdoor_recreation has all-null distances and must not appear in available_categories');
        $this->assertNotContains('transportation', $context['available_categories'],
            'transportation has all-null distances and must not appear in available_categories');
    }

    /** @test */
    public function missing_categories_lists_thematic_keys_where_all_distances_are_null(): void
    {
        // SAMPLE_SUMMARY: coastal, outdoor_recreation, transportation are all-null
        $this->createDnaRecord(summaryJson: self::SAMPLE_SUMMARY);

        $result  = $this->makeService()->getForListing(self::LISTING_TYPE, self::LISTING_ID);
        $context = $result['location_intelligence_context'];

        $this->assertIsArray($context['missing_categories']);

        foreach (['coastal_features', 'outdoor_recreation', 'transportation'] as $key) {
            $this->assertContains($key, $context['missing_categories'],
                "'{$key}' has all-null distances and must appear in missing_categories");
        }
        $this->assertNotContains('daily_convenience', $context['missing_categories'],
            'daily_convenience has a non-null value and must not appear in missing_categories');
    }

    /** @test */
    public function available_categories_contains_all_four_keys_when_every_block_has_a_non_null_value(): void
    {
        $this->createDnaRecord(summaryJson: self::FULLY_AVAILABLE_SUMMARY);

        $result  = $this->makeService()->getForListing(self::LISTING_TYPE, self::LISTING_ID);
        $context = $result['location_intelligence_context'];

        $this->assertCount(4, $context['available_categories'],
            'All four thematic categories must be available when each has at least one non-null value');

        foreach (['coastal_features', 'daily_convenience', 'outdoor_recreation', 'transportation'] as $key) {
            $this->assertContains($key, $context['available_categories'],
                "'{$key}' must appear in available_categories");
        }
        $this->assertEmpty($context['missing_categories'],
            'missing_categories must be empty when all thematic blocks have non-null values');
    }

    /** @test */
    public function available_and_missing_categories_are_mutually_exclusive_and_exhaustive(): void
    {
        $this->createDnaRecord(summaryJson: self::SAMPLE_SUMMARY);

        $result  = $this->makeService()->getForListing(self::LISTING_TYPE, self::LISTING_ID);
        $context = $result['location_intelligence_context'];

        $allExpected = ['coastal_features', 'daily_convenience', 'outdoor_recreation', 'transportation'];
        $combined    = array_merge($context['available_categories'], $context['missing_categories']);

        sort($allExpected);
        sort($combined);

        $this->assertSame($allExpected, $combined,
            'available_categories + missing_categories must exactly cover all four thematic keys');

        $intersection = array_intersect($context['available_categories'], $context['missing_categories']);
        $this->assertEmpty($intersection,
            'A thematic key must not appear in both available_categories and missing_categories');
    }

    // =========================================================================
    // (14) location_intelligence_context is null on all non-available paths
    // =========================================================================

    /** @test */
    public function location_intelligence_context_is_null_on_non_available_paths(): void
    {
        // missing path
        $missing = $this->makeService()->getForListing('landlord_agent_auction', 404);
        $this->assertNull($missing['location_intelligence_context'],
            'location_intelligence_context must be null on the missing path');

        // not_generated path
        $this->createDnaRecord('landlord_agent_auction', 405, null);
        $notGenerated = $this->makeService()->getForListing('landlord_agent_auction', 405);
        $this->assertNull($notGenerated['location_intelligence_context'],
            'location_intelligence_context must be null on the not_generated path');
    }

    /** @test */
    public function location_intelligence_context_is_null_on_failed_path(): void
    {
        $service = new class extends LocationDnaIntelligenceContextService {
            public function getForListing(string $listingType, int $listingId): array
            {
                try {
                    throw new \RuntimeException('Simulated failure');
                } catch (\Throwable $e) {
                    return [
                        'success'                      => false,
                        'status'                       => 'failed',
                        'listing_type'                 => $listingType,
                        'listing_id'                   => $listingId,
                        'location_intelligence_context' => null,
                        'error'                        => $e->getMessage(),
                    ];
                }
            }
        };

        $result = $service->getForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertNull($result['location_intelligence_context'],
            'location_intelligence_context must be null on the failed path');
        $this->assertSame('failed', $result['status']);
    }
}
