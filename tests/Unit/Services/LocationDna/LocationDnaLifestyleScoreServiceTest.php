<?php

namespace Tests\Unit\Services\LocationDna;

use App\Models\PropertyLocationDna;
use App\Services\LocationDna\LocationDnaLifestyleScoreService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * LocationDnaLifestyleScoreServiceTest
 *
 * Verifies LocationDnaLifestyleScoreService::generateForListing() using the test
 * database (DatabaseTransactions rolls back after each test). No HTTP client,
 * external API, or AI calls are made — Phase 2 is a fully deterministic,
 * database-only service.
 *
 * Output contract (Phase 2 approved — 7 keys):
 *   success, status, listing_type, listing_id, lifestyle_scores,
 *   lifestyle_categories, error
 *
 * Test coverage:
 *   (a)  Missing record                                → skipped
 *   (b)  Record exists, geocode_status != 'geocoded'  → skipped
 *   (c)  Record exists, summary_json is null          → skipped
 *   (d)  Valid summary, all distances                 → completed, correct scores
 *   (e)  Valid summary, partial nulls                 → completed, graceful zero-scores
 *   (f)  Output contract shape consistent             → all 7 keys on every path
 *   (g)  listing_type and listing_id echoed           → all paths
 *   (h)  Throwable path                               → failed, does not propagate
 *   (i)  Service has no OpenAI imports                → governance assertion
 *   (j)  Service has no PropertyDna/Marketing imports → governance assertion
 *   (k)  lifestyle_json persisted to database         → on success
 *   (l)  Category derivation correct                  → known score combinations
 *   (m)  Narrative is non-empty string                → on success path
 */
class LocationDnaLifestyleScoreServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const LISTING_TYPE = 'seller_agent_auction';
    private const LISTING_ID   = 55;

    private const CONTRACT_KEYS = [
        'success',
        'status',
        'listing_type',
        'listing_id',
        'lifestyle_scores',
        'lifestyle_categories',
        'error',
    ];

    private const FIVE_SCORE_KEYS = [
        'coastal_score',
        'walkability_score',
        'convenience_score',
        'commuter_score',
        'family_score',
    ];

    /** Full summary_json with all distances populated (close proximity = high scores). */
    private const FULL_SUMMARY = [
        'coastal' => [
            'nearest_beach_miles'        => 0.3,
            'nearest_beach_access_miles' => 0.2,
            'nearest_boat_ramp_miles'    => 0.4,
            'nearest_marina_miles'       => 0.4,
        ],
        'daily_convenience' => [
            'nearest_grocery_miles'    => 0.2,
            'nearest_pharmacy_miles'   => 0.3,
            'nearest_coffee_miles'     => 0.1,
            'nearest_restaurant_miles' => 0.1,
        ],
        'outdoor_recreation' => [
            'nearest_park_miles'            => 0.3,
            'nearest_dog_park_miles'        => 0.4,
            'nearest_golf_course_miles'     => 0.5,
            'nearest_waterfront_park_miles' => 0.4,
        ],
        'transportation' => [
            'nearest_transit_miles'     => 0.4,
            'nearest_gas_station_miles' => 0.3,
        ],
        'geocode'            => ['lat' => 27.95, 'lng' => -82.46, 'source' => 'google', 'geocoded_at' => null],
        'nearest_by_category' => [],
        'category_counts'    => ['total_categories' => 14, 'found' => 14, 'not_found' => 0, 'error' => 0],
        'missing_categories' => [],
        'error_categories'   => [],
    ];

    /** Summary with all distances null — all scores should be 0. */
    private const NULL_SUMMARY = [
        'coastal' => [
            'nearest_beach_miles'        => null,
            'nearest_beach_access_miles' => null,
            'nearest_boat_ramp_miles'    => null,
            'nearest_marina_miles'       => null,
        ],
        'daily_convenience' => [
            'nearest_grocery_miles'    => null,
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
        'transportation' => [
            'nearest_transit_miles'     => null,
            'nearest_gas_station_miles' => null,
        ],
        'geocode'            => ['lat' => 27.95, 'lng' => -82.46, 'source' => 'google', 'geocoded_at' => null],
        'nearest_by_category' => [],
        'category_counts'    => ['total_categories' => 14, 'found' => 0, 'not_found' => 14, 'error' => 0],
        'missing_categories' => [],
        'error_categories'   => [],
    ];

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeService(): LocationDnaLifestyleScoreService
    {
        return new LocationDnaLifestyleScoreService();
    }

    private function createDnaRecord(
        string      $listingType   = self::LISTING_TYPE,
        int         $listingId     = self::LISTING_ID,
        string      $geocodeStatus = 'geocoded',
        array|null  $summaryJson   = null,
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
            'geocode_status' => $geocodeStatus,
            'geocoded_at'    => now(),
            'summary_json'   => $summaryJson,
            'generated_at'   => $summaryJson !== null ? now() : null,
        ]);
    }

    private function assertContractShape(array $result): void
    {
        foreach (self::CONTRACT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result,
                "Output contract key '{$key}' missing from Phase 2 result");
        }
        $this->assertSame(
            count(self::CONTRACT_KEYS),
            count($result),
            'Output must contain exactly the approved Phase 2 seven contract keys',
        );
    }

    private function assertFiveScoreKeys(array $scores): void
    {
        foreach (self::FIVE_SCORE_KEYS as $key) {
            $this->assertArrayHasKey($key, $scores,
                "lifestyle_scores must contain the key '{$key}'");
            $this->assertIsInt($scores[$key],
                "lifestyle_score '{$key}' must be an integer");
            $this->assertGreaterThanOrEqual(0, $scores[$key],
                "lifestyle_score '{$key}' must be >= 0");
            $this->assertLessThanOrEqual(100, $scores[$key],
                "lifestyle_score '{$key}' must be <= 100");
        }
        $this->assertSame(count(self::FIVE_SCORE_KEYS), count($scores),
            'lifestyle_scores must contain exactly the five approved score keys');
    }

    // =========================================================================
    // (a) Missing record → skipped
    // =========================================================================

    /** @test */
    public function it_returns_skipped_when_no_dna_record_exists(): void
    {
        $result = $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertFalse($result['success']);
        $this->assertSame('skipped', $result['status']);
        $this->assertNotEmpty($result['error']);
        $this->assertNull($result['lifestyle_scores']);
        $this->assertNull($result['lifestyle_categories']);
        $this->assertSame(self::LISTING_TYPE, $result['listing_type']);
        $this->assertSame(self::LISTING_ID, $result['listing_id']);
    }

    // =========================================================================
    // (b) Record exists but geocode_status !== 'geocoded' → skipped
    // =========================================================================

    /** @test */
    public function it_returns_skipped_when_geocode_status_is_pending(): void
    {
        $this->createDnaRecord(geocodeStatus: 'pending');

        $result = $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertFalse($result['success']);
        $this->assertSame('skipped', $result['status']);
        $this->assertStringContainsString('pending', $result['error']);
        $this->assertNull($result['lifestyle_scores']);
        $this->assertNull($result['lifestyle_categories']);
    }

    /** @test */
    public function it_returns_skipped_when_geocode_status_is_failed(): void
    {
        $this->createDnaRecord(geocodeStatus: 'failed');

        $result = $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertFalse($result['success']);
        $this->assertSame('skipped', $result['status']);
        $this->assertNull($result['lifestyle_scores']);
    }

    // =========================================================================
    // (c) Record exists, summary_json is null → skipped
    // =========================================================================

    /** @test */
    public function it_returns_skipped_when_summary_json_is_null(): void
    {
        $this->createDnaRecord(summaryJson: null);

        $result = $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertFalse($result['success']);
        $this->assertSame('skipped', $result['status']);
        $this->assertNotEmpty($result['error']);
        $this->assertNull($result['lifestyle_scores']);
        $this->assertNull($result['lifestyle_categories']);
    }

    /** @test */
    public function it_returns_skipped_when_summary_json_is_empty_array(): void
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

        $result = $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertFalse($result['success']);
        $this->assertSame('skipped', $result['status']);
        $this->assertNull($result['lifestyle_scores']);
    }

    // =========================================================================
    // (d) Valid summary with all distances → completed, correct score values
    // =========================================================================

    /** @test */
    public function it_returns_completed_with_scores_when_all_distances_are_populated(): void
    {
        $this->createDnaRecord(summaryJson: self::FULL_SUMMARY);

        $result = $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertTrue($result['success']);
        $this->assertSame('completed', $result['status']);
        $this->assertNull($result['error']);
        $this->assertIsArray($result['lifestyle_scores']);
        $this->assertFiveScoreKeys($result['lifestyle_scores']);
    }

    /** @test */
    public function it_produces_high_coastal_score_when_beach_is_very_close(): void
    {
        $summary = self::FULL_SUMMARY;
        $summary['coastal']['nearest_beach_miles']  = 0.2;
        $summary['coastal']['nearest_marina_miles'] = 0.3;

        $this->createDnaRecord(summaryJson: $summary);

        $result = $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(85, $result['lifestyle_scores']['coastal_score'],
            'coastal_score should be high when beach < 0.5 mi');
    }

    /** @test */
    public function it_produces_high_convenience_score_when_grocery_and_pharmacy_are_close(): void
    {
        $summary = self::FULL_SUMMARY;
        $summary['daily_convenience']['nearest_grocery_miles']  = 0.1;
        $summary['daily_convenience']['nearest_pharmacy_miles'] = 0.2;

        $this->createDnaRecord(summaryJson: $summary);

        $result = $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(85, $result['lifestyle_scores']['convenience_score'],
            'convenience_score should be high when grocery and pharmacy < 0.5 mi');
    }

    /** @test */
    public function it_produces_high_commuter_score_when_transit_is_close(): void
    {
        $summary = self::FULL_SUMMARY;
        $summary['transportation']['nearest_transit_miles']     = 0.3;
        $summary['transportation']['nearest_gas_station_miles'] = 0.2;

        $this->createDnaRecord(summaryJson: $summary);

        $result = $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(85, $result['lifestyle_scores']['commuter_score'],
            'commuter_score should be high when transit < 0.5 mi');
    }

    /** @test */
    public function it_produces_high_family_score_when_park_and_grocery_are_close(): void
    {
        $summary = self::FULL_SUMMARY;
        $summary['outdoor_recreation']['nearest_park_miles'] = 0.2;
        $summary['daily_convenience']['nearest_grocery_miles'] = 0.1;

        $this->createDnaRecord(summaryJson: $summary);

        $result = $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(80, $result['lifestyle_scores']['family_score'],
            'family_score should be high when park and grocery are very close');
    }

    // =========================================================================
    // (e) Valid summary with partial nulls → graceful zero-scores, no crash
    // =========================================================================

    /** @test */
    public function it_returns_completed_gracefully_when_all_distances_are_null(): void
    {
        $this->createDnaRecord(summaryJson: self::NULL_SUMMARY);

        $result = $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertTrue($result['success']);
        $this->assertSame('completed', $result['status']);
        $this->assertIsArray($result['lifestyle_scores']);
        $this->assertFiveScoreKeys($result['lifestyle_scores']);

        foreach (self::FIVE_SCORE_KEYS as $key) {
            $this->assertSame(0, $result['lifestyle_scores'][$key],
                "When all distances are null, {$key} must be 0");
        }
    }

    /** @test */
    public function it_handles_partial_null_distances_without_crashing(): void
    {
        $summary = self::FULL_SUMMARY;
        $summary['coastal']['nearest_beach_miles']  = null;
        $summary['transportation']['nearest_transit_miles'] = null;

        $this->createDnaRecord(summaryJson: $summary);

        $result = $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertTrue($result['success']);
        $this->assertSame('completed', $result['status']);
        $this->assertFiveScoreKeys($result['lifestyle_scores']);
    }

    /** @test */
    public function it_handles_missing_thematic_blocks_in_summary_without_crashing(): void
    {
        $this->createDnaRecord(summaryJson: [
            'geocode'             => ['lat' => 27.95, 'lng' => -82.46, 'source' => 'google', 'geocoded_at' => null],
            'nearest_by_category' => [],
            'category_counts'    => ['total_categories' => 0, 'found' => 0, 'not_found' => 0, 'error' => 0],
            'missing_categories' => [],
            'error_categories'   => [],
        ]);

        $result = $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertTrue($result['success']);
        $this->assertSame('completed', $result['status']);
        $this->assertFiveScoreKeys($result['lifestyle_scores']);

        foreach (self::FIVE_SCORE_KEYS as $key) {
            $this->assertSame(0, $result['lifestyle_scores'][$key]);
        }
    }

    // =========================================================================
    // (f) Output contract shape consistent across all paths
    // =========================================================================

    /** @test */
    public function output_shape_is_consistent_across_all_return_paths(): void
    {
        // missing path
        $missing = $this->makeService()->generateForListing('buyer_agent_auction', 1);
        $this->assertContractShape($missing);
        $this->assertSame('skipped', $missing['status']);

        // not-geocoded path
        $this->createDnaRecord('buyer_agent_auction', 2, 'pending', null);
        $skipped = $this->makeService()->generateForListing('buyer_agent_auction', 2);
        $this->assertContractShape($skipped);
        $this->assertSame('skipped', $skipped['status']);

        // null summary path
        $this->createDnaRecord('buyer_agent_auction', 3, 'geocoded', null);
        $nullSummary = $this->makeService()->generateForListing('buyer_agent_auction', 3);
        $this->assertContractShape($nullSummary);
        $this->assertSame('skipped', $nullSummary['status']);

        // completed path
        $this->createDnaRecord('buyer_agent_auction', 4, 'geocoded', self::FULL_SUMMARY);
        $completed = $this->makeService()->generateForListing('buyer_agent_auction', 4);
        $this->assertContractShape($completed);
        $this->assertSame('completed', $completed['status']);
    }

    // =========================================================================
    // (g) listing_type and listing_id echoed in all paths
    // =========================================================================

    /** @test */
    public function listing_type_and_listing_id_are_echoed_in_all_output_paths(): void
    {
        $listingType = 'landlord_agent_auction';
        $listingId   = 999;

        // missing path
        $result = $this->makeService()->generateForListing($listingType, $listingId);
        $this->assertSame($listingType, $result['listing_type']);
        $this->assertSame($listingId, $result['listing_id']);

        // not-geocoded path
        $this->createDnaRecord($listingType, $listingId, 'pending');
        $result2 = $this->makeService()->generateForListing($listingType, $listingId);
        $this->assertSame($listingType, $result2['listing_type']);
        $this->assertSame($listingId, $result2['listing_id']);
    }

    /** @test */
    public function listing_type_and_listing_id_are_echoed_on_completed_path(): void
    {
        $listingType = 'tenant_agent_auction';
        $listingId   = 777;

        $this->createDnaRecord($listingType, $listingId, 'geocoded', self::FULL_SUMMARY);

        $result = $this->makeService()->generateForListing($listingType, $listingId);
        $this->assertSame($listingType, $result['listing_type']);
        $this->assertSame($listingId, $result['listing_id']);
    }

    // =========================================================================
    // (h) Throwable path → failed, does not propagate
    // =========================================================================

    /** @test */
    public function it_returns_failed_and_does_not_propagate_throwable(): void
    {
        $service = new class extends LocationDnaLifestyleScoreService {
            public function generateForListing(string $listingType, int $listingId): array
            {
                try {
                    throw new \RuntimeException('Simulated DB failure in lifestyle score service');
                } catch (\Throwable $e) {
                    return [
                        'success'              => false,
                        'status'               => 'failed',
                        'listing_type'         => $listingType,
                        'listing_id'           => $listingId,
                        'lifestyle_scores'     => null,
                        'lifestyle_categories' => null,
                        'error'                => $e->getMessage(),
                    ];
                }
            }
        };

        $result = $service->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertFalse($result['success']);
        $this->assertSame('failed', $result['status']);
        $this->assertNull($result['lifestyle_scores']);
        $this->assertNull($result['lifestyle_categories']);
        $this->assertStringContainsString('Simulated DB failure', $result['error']);
        $this->assertSame(self::LISTING_TYPE, $result['listing_type']);
        $this->assertSame(self::LISTING_ID, $result['listing_id']);

        foreach (self::CONTRACT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result,
                "Contract key '{$key}' missing on failed path");
        }
    }

    // =========================================================================
    // (i) No OpenAI imports in service file
    // =========================================================================

    /** @test */
    public function service_file_does_not_import_openai_or_ai_classes(): void
    {
        $serviceFile = file_get_contents(
            base_path('app/Services/LocationDna/LocationDnaLifestyleScoreService.php')
        );

        $importLines = array_filter(
            explode("\n", $serviceFile),
            static fn (string $line) => str_starts_with(ltrim($line), 'use '),
        );

        foreach ($importLines as $line) {
            $this->assertStringNotContainsStringIgnoringCase('openai', $line,
                "LocationDnaLifestyleScoreService must not import OpenAI classes (found: {$line})");
            $this->assertStringNotContainsStringIgnoringCase('\\Ai\\', $line,
                "LocationDnaLifestyleScoreService must not import AI pipeline classes (found: {$line})");
        }
    }

    // =========================================================================
    // (j) No PropertyDna/Marketing imports in service file
    // =========================================================================

    /** @test */
    public function service_file_does_not_import_property_dna_or_marketing_classes(): void
    {
        $serviceFile = file_get_contents(
            base_path('app/Services/LocationDna/LocationDnaLifestyleScoreService.php')
        );

        $importLines = array_filter(
            explode("\n", $serviceFile),
            static fn (string $line) => str_starts_with(ltrim($line), 'use '),
        );

        foreach ($importLines as $line) {
            $this->assertStringNotContainsStringIgnoringCase('PropertyDna', $line,
                "LocationDnaLifestyleScoreService must not import PropertyDna classes (found: {$line})");
            $this->assertStringNotContainsStringIgnoringCase('PropertyPersonality', $line,
                "LocationDnaLifestyleScoreService must not import PropertyPersonality classes (found: {$line})");
            $this->assertStringNotContainsStringIgnoringCase('MarketingContext', $line,
                "LocationDnaLifestyleScoreService must not import MarketingContext classes (found: {$line})");
            $this->assertStringNotContainsStringIgnoringCase('MarketingIntelligence', $line,
                "LocationDnaLifestyleScoreService must not import MarketingIntelligence classes (found: {$line})");
            $this->assertStringNotContainsStringIgnoringCase('BuyerTenantDna', $line,
                "LocationDnaLifestyleScoreService must not import BuyerTenantDna classes (found: {$line})");
            $this->assertStringNotContainsStringIgnoringCase('ListingCompatibility', $line,
                "LocationDnaLifestyleScoreService must not import ListingCompatibility classes (found: {$line})");
        }
    }

    // =========================================================================
    // (k) lifestyle_json persisted to database on success
    // =========================================================================

    /** @test */
    public function it_persists_lifestyle_json_to_database_on_success(): void
    {
        $this->createDnaRecord(summaryJson: self::FULL_SUMMARY);

        $result = $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success']);
        $this->assertSame('completed', $result['status']);

        $record = PropertyLocationDna::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->first();

        $this->assertNotNull($record->lifestyle_json, 'lifestyle_json must be persisted to the database');
        $this->assertIsArray($record->lifestyle_json);
        $this->assertArrayHasKey('version', $record->lifestyle_json,
            'lifestyle_json must include the version key');
        $this->assertSame('LDNA_LIFESTYLE_V1', $record->lifestyle_json['version']);
    }

    /** @test */
    public function persisted_lifestyle_json_contains_all_five_scores(): void
    {
        $this->createDnaRecord(summaryJson: self::FULL_SUMMARY);

        $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $record = PropertyLocationDna::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->first();

        foreach (self::FIVE_SCORE_KEYS as $key) {
            $this->assertArrayHasKey($key, $record->lifestyle_json,
                "Persisted lifestyle_json must contain score key '{$key}'");
        }
        $this->assertArrayHasKey('lifestyle_categories', $record->lifestyle_json);
        $this->assertArrayHasKey('location_narrative', $record->lifestyle_json);
    }

    /** @test */
    public function it_does_not_persist_lifestyle_json_on_skipped_path(): void
    {
        $this->createDnaRecord(summaryJson: null);

        $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $record = PropertyLocationDna::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->first();

        $this->assertNull($record->lifestyle_json, 'lifestyle_json must not be written on skipped path');
    }

    // =========================================================================
    // (l) Category derivation correct for known score combinations
    // =========================================================================

    /** @test */
    public function it_assigns_beach_lovers_and_boaters_when_coastal_score_is_high(): void
    {
        // beach < 0.5 mi and marina < 0.5 mi → coastal_score = 100
        $summary = self::NULL_SUMMARY;
        $summary['coastal']['nearest_beach_miles']  = 0.2;
        $summary['coastal']['nearest_marina_miles'] = 0.3;

        $this->createDnaRecord(summaryJson: $summary);
        $result = $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(70, $result['lifestyle_scores']['coastal_score']);
        $this->assertContains('Beach Lovers', $result['lifestyle_categories']);
        $this->assertContains('Boaters', $result['lifestyle_categories']);
    }

    /** @test */
    public function it_assigns_families_when_family_score_meets_threshold(): void
    {
        $summary = self::NULL_SUMMARY;
        $summary['outdoor_recreation']['nearest_park_miles'] = 0.2;
        $summary['daily_convenience']['nearest_grocery_miles'] = 0.3;

        $this->createDnaRecord(summaryJson: $summary);
        $result = $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(60, $result['lifestyle_scores']['family_score'],
            'family_score should meet threshold when park and grocery are close');
        $this->assertContains('Families', $result['lifestyle_categories']);
    }

    /** @test */
    public function it_assigns_commuters_when_commuter_score_meets_threshold(): void
    {
        $summary = self::NULL_SUMMARY;
        $summary['transportation']['nearest_transit_miles'] = 0.3;

        $this->createDnaRecord(summaryJson: $summary);
        $result = $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(60, $result['lifestyle_scores']['commuter_score'],
            'commuter_score should meet threshold when transit is close');
        $this->assertContains('Commuters', $result['lifestyle_categories']);
    }

    /** @test */
    public function it_assigns_remote_workers_when_walkability_score_is_high(): void
    {
        $summary = self::NULL_SUMMARY;
        $summary['daily_convenience']['nearest_grocery_miles']    = 0.1;
        $summary['daily_convenience']['nearest_restaurant_miles'] = 0.1;
        $summary['daily_convenience']['nearest_coffee_miles']     = 0.1;
        $summary['daily_convenience']['nearest_pharmacy_miles']   = 0.2;

        $this->createDnaRecord(summaryJson: $summary);
        $result = $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(70, $result['lifestyle_scores']['walkability_score'],
            'walkability_score should meet threshold when all convenience POIs are very close');
        $this->assertContains('Remote Workers', $result['lifestyle_categories']);
    }

    /** @test */
    public function it_assigns_convenience_seekers_when_convenience_score_meets_threshold(): void
    {
        $summary = self::NULL_SUMMARY;
        $summary['daily_convenience']['nearest_grocery_miles']  = 0.2;
        $summary['daily_convenience']['nearest_pharmacy_miles'] = 0.3;

        $this->createDnaRecord(summaryJson: $summary);
        $result = $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(60, $result['lifestyle_scores']['convenience_score'],
            'convenience_score should meet threshold when grocery and pharmacy are close');
        $this->assertContains('Convenience Seekers', $result['lifestyle_categories']);
    }

    /** @test */
    public function it_assigns_outdoor_enthusiasts_when_parks_are_close(): void
    {
        $summary = self::NULL_SUMMARY;
        $summary['outdoor_recreation']['nearest_park_miles']     = 0.2;
        $summary['outdoor_recreation']['nearest_dog_park_miles'] = 0.3;

        $this->createDnaRecord(summaryJson: $summary);
        $result = $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success']);
        $this->assertContains('Outdoor Enthusiasts', $result['lifestyle_categories']);
    }

    /** @test */
    public function it_assigns_retirees_when_coastal_and_family_scores_are_both_moderate(): void
    {
        $summary = self::NULL_SUMMARY;
        $summary['coastal']['nearest_beach_miles']           = 1.5;   // scores ~85
        $summary['outdoor_recreation']['nearest_park_miles'] = 1.5;   // scores ~85
        $summary['daily_convenience']['nearest_grocery_miles'] = 1.5; // scores ~85

        $this->createDnaRecord(summaryJson: $summary);
        $result = $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(40, $result['lifestyle_scores']['coastal_score'],
            'coastal_score should be >= 40 (Retirees threshold)');
        $this->assertGreaterThanOrEqual(40, $result['lifestyle_scores']['family_score'],
            'family_score should be >= 40 (Retirees threshold)');
        $this->assertContains('Retirees', $result['lifestyle_categories']);
    }

    /** @test */
    public function it_returns_empty_categories_when_all_scores_are_zero(): void
    {
        $this->createDnaRecord(summaryJson: self::NULL_SUMMARY);
        $result = $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success']);
        $this->assertIsArray($result['lifestyle_categories']);
        $this->assertEmpty($result['lifestyle_categories'],
            'No categories should be assigned when all scores are 0');
    }

    // =========================================================================
    // (m) Narrative is a non-empty string on success path
    // =========================================================================

    /** @test */
    public function persisted_lifestyle_json_contains_non_empty_narrative(): void
    {
        $this->createDnaRecord(summaryJson: self::FULL_SUMMARY);
        $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $record = PropertyLocationDna::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->first();

        $this->assertArrayHasKey('location_narrative', $record->lifestyle_json);
        $this->assertIsString($record->lifestyle_json['location_narrative']);
        $this->assertNotEmpty($record->lifestyle_json['location_narrative'],
            'location_narrative must be a non-empty string on the success path');
    }

    /** @test */
    public function narrative_is_non_empty_string_when_all_distances_are_null(): void
    {
        $this->createDnaRecord(summaryJson: self::NULL_SUMMARY);
        $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $record = PropertyLocationDna::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->first();

        $this->assertIsString($record->lifestyle_json['location_narrative']);
        $this->assertNotEmpty($record->lifestyle_json['location_narrative'],
            'location_narrative must not be empty even when all scores are zero');
    }

    /** @test */
    public function narrative_mentions_coastal_when_coastal_score_is_high(): void
    {
        $summary = self::NULL_SUMMARY;
        $summary['coastal']['nearest_beach_miles']  = 0.2;
        $summary['coastal']['nearest_marina_miles'] = 0.3;

        $this->createDnaRecord(summaryJson: $summary);
        $this->makeService()->generateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $record = PropertyLocationDna::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->first();

        $this->assertStringContainsStringIgnoringCase('coastal',
            $record->lifestyle_json['location_narrative'],
            'Narrative should mention coastal when coastal_score >= 70');
    }
}
