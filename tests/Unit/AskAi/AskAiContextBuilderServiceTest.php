<?php

namespace Tests\Unit\AskAi;

use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\Dna\PropertyIntelligenceProfileService;
use App\Services\LocationDna\LocationDnaIntelligenceContextService;
use App\Services\LocationDna\LocationDnaMarketingContextService;
use PHPUnit\Framework\TestCase;

/**
 * AskAiContextBuilderServiceTest
 *
 * Pure unit tests — no database, no Laravel TestCase, no DB traits.
 *
 * Covers:
 *   A. Landlord context arm — all 32 fields added in the Residential Rental audit
 *      (25 structural/leasing + 7 tax/legal/parcel) are present and non-null when
 *      the listing fixture supplies them.
 *   B. Landlord context arm — scalar fields carry the raw string value.
 *   C. Landlord context arm — JSON-encoded array fields are decoded to a
 *      comma-separated string (decodeJsonField behaviour).
 *   D. Landlord context arm — missing meta keys yield null (not false/empty string).
 *   E. Static governance scan — no write calls in AskAiContextBuilderService.
 */
class AskAiContextBuilderServiceTest extends TestCase
{
    // =========================================================================
    // All 32 fields added in the Landlord Residential Rental audit.
    // (25 original + 7 Tax/Legal/Parcel fields added in follow-up)
    // =========================================================================

    private const LANDLORD_AUDIT_SCALAR_FIELDS = [
        // Tax / Legal / Parcel
        'parcel_id',
        'tax_year',
        'annual_property_taxes',
        'legal_description',
        'additional_parcels',
        'total_parcel_count',
        'additional_parcel_ids',
        // Structural / water / leasing
        'year_built',
        'lot_dimensions',
        'zoning',
        'waterfront',
        'flood_zone_code',
        'flood_zone_panel',
        'flood_zone_date',
        'flood_insurance_required',
        'security_deposit_amount',
        'lease_amount_frequency',
        'has_cdd',
        'annual_cdd_fee',
        'sqft_heated_source',
    ];

    private const LANDLORD_AUDIT_ARRAY_FIELDS = [
        'water_access',
        'interior_features',
        'roof_type',
        'exterior_construction',
        'foundation',
        'terms_of_lease',
        'tenant_pays',
        'rent_includes',
        'heating_fuel',
        'air_conditioning',
        'water',
        'sewer',
    ];

    private const WRITE_PATTERNS = [
        '->save(',
        '->update(',
        '->create(',
        '->delete(',
        'DB::insert(',
        'DB::update(',
        'DB::delete(',
    ];

    // =========================================================================
    // Factory helpers
    // =========================================================================

    private function makeIntelligenceServiceMock(): PropertyIntelligenceProfileService
    {
        return $this->getMockBuilder(PropertyIntelligenceProfileService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['buildPayloadReadOnly'])
            ->getMock();
    }

    private function makeLocationDnaIntelligenceServiceMock(): LocationDnaIntelligenceContextService
    {
        $mock = $this->getMockBuilder(LocationDnaIntelligenceContextService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getForListing'])
            ->getMock();

        $mock->method('getForListing')->willReturn([
            'success'                       => false,
            'status'                        => 'missing',
            'listing_type'                  => 'landlord',
            'listing_id'                    => 1,
            'location_intelligence_context' => null,
            'error'                         => 'No property_location_dna record found for this listing',
        ]);

        return $mock;
    }

    private function makeLocationDnaMarketingServiceMock(): LocationDnaMarketingContextService
    {
        $mock = $this->getMockBuilder(LocationDnaMarketingContextService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getForListing'])
            ->getMock();

        $mock->method('getForListing')->willReturn([
            'success'                    => false,
            'status'                     => 'missing',
            'listing_type'               => 'landlord',
            'listing_id'                 => 1,
            'marketing_location_context' => null,
            'error'                      => 'No property_location_dna record found for this listing',
        ]);

        return $mock;
    }

    /**
     * Build a partial mock of AskAiContextBuilderService with all finder methods stubbed.
     * Canonical reference — update all makeContextBuilder() copies in the suite when the
     * constructor signature changes (see ask-ai-test-constructor-drift.md in agent memory).
     *
     * @return AskAiContextBuilderService&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeService(): AskAiContextBuilderService
    {
        return $this->getMockBuilder(AskAiContextBuilderService::class)
            ->setConstructorArgs([
                $this->makeIntelligenceServiceMock(),
                $this->makeLocationDnaIntelligenceServiceMock(),
                $this->makeLocationDnaMarketingServiceMock(),
            ])
            ->onlyMethods([
                'findListing',
                'findPropertyDnaProfile',
                'findPropertyLocationDna',
                'findBuyerTenantDnaProfile',
                'findCompatibilityScore',
                'findAcceptedBidSummary',
            ])
            ->getMock();
    }

    /**
     * Build a landlord listing stub whose info() method returns predictable test values.
     *
     * Scalar fields → raw string.
     * Array fields  → JSON-encoded array (matches how EAV meta is stored).
     *
     * @param  array<string, string|null>  $overrides  Override specific keys to null.
     * @return object
     */
    private function makeLandlordListingStub(array $overrides = []): object
    {
        $scalarValues = [];
        foreach (self::LANDLORD_AUDIT_SCALAR_FIELDS as $key) {
            $scalarValues[$key] = 'test-' . $key;
        }

        $arrayValues = [];
        foreach (self::LANDLORD_AUDIT_ARRAY_FIELDS as $key) {
            $arrayValues[$key] = json_encode(['Option A', 'Option B']);
        }

        $meta = array_merge($scalarValues, $arrayValues, $overrides);

        return new class($meta) {
            public int    $id          = 1;
            public bool   $is_approved = true;
            public string $created_at  = '2026-01-01 00:00:00';
            public string $updated_at  = '2026-01-01 00:00:00';

            private array $meta;

            public function __construct(array $meta)
            {
                $this->meta = $meta;
            }

            public function info(string $key): ?string
            {
                return $this->meta[$key] ?? null;
            }
        };
    }

    // =========================================================================
    // Case A — all 25 audit fields appear in context when meta is populated
    // =========================================================================

    public function test_landlord_all_32_audit_fields_present_when_meta_populated(): void
    {
        $service = $this->makeService();
        $stub    = $this->makeLandlordListingStub();

        $service->method('findListing')->willReturn($stub);
        $service->method('findPropertyDnaProfile')->willReturn(null);
        $service->method('findPropertyLocationDna')->willReturn(null);
        $service->method('findBuyerTenantDnaProfile')->willReturn(null);
        $service->method('findCompatibilityScore')->willReturn(null);
        $service->method('findAcceptedBidSummary')->willReturn(null);

        $ctx = $service->buildForListing('landlord', 1);

        $this->assertTrue($ctx['success'], 'Expected success=true but got: ' . ($ctx['error'] ?? 'unknown'));
        $listing = $ctx['listing'];

        $allFields = array_merge(self::LANDLORD_AUDIT_SCALAR_FIELDS, self::LANDLORD_AUDIT_ARRAY_FIELDS);
        foreach ($allFields as $field) {
            $this->assertArrayHasKey($field, $listing,
                "listing['{$field}'] is missing from landlord context entirely.");
            $this->assertNotNull($listing[$field],
                "listing['{$field}'] is null in landlord context — expected a non-null value.");
        }
    }

    // =========================================================================
    // Case B — scalar fields carry the raw string value
    // =========================================================================

    public function test_landlord_scalar_fields_carry_raw_string(): void
    {
        $service = $this->makeService();
        $stub    = $this->makeLandlordListingStub();

        $service->method('findListing')->willReturn($stub);
        $service->method('findPropertyDnaProfile')->willReturn(null);
        $service->method('findPropertyLocationDna')->willReturn(null);
        $service->method('findBuyerTenantDnaProfile')->willReturn(null);
        $service->method('findCompatibilityScore')->willReturn(null);
        $service->method('findAcceptedBidSummary')->willReturn(null);

        $ctx     = $service->buildForListing('landlord', 1);
        $listing = $ctx['listing'];

        foreach (self::LANDLORD_AUDIT_SCALAR_FIELDS as $key) {
            $this->assertEquals(
                'test-' . $key,
                $listing[$key],
                "Scalar field '{$key}' value mismatch."
            );
        }
    }

    // =========================================================================
    // Case C — JSON array fields are decoded (non-null, comma-separated or array)
    // =========================================================================

    public function test_landlord_array_fields_are_decoded_not_raw_json(): void
    {
        $service = $this->makeService();
        $stub    = $this->makeLandlordListingStub();

        $service->method('findListing')->willReturn($stub);
        $service->method('findPropertyDnaProfile')->willReturn(null);
        $service->method('findPropertyLocationDna')->willReturn(null);
        $service->method('findBuyerTenantDnaProfile')->willReturn(null);
        $service->method('findCompatibilityScore')->willReturn(null);
        $service->method('findAcceptedBidSummary')->willReturn(null);

        $ctx     = $service->buildForListing('landlord', 1);
        $listing = $ctx['listing'];

        foreach (self::LANDLORD_AUDIT_ARRAY_FIELDS as $key) {
            $value = $listing[$key];
            $this->assertNotNull($value, "Array field '{$key}' should not be null.");
            $this->assertNotJsonString($value,
                "Array field '{$key}' should be decoded, not raw JSON. Got: {$value}");
            $this->assertStringContainsString('Option A', $value,
                "Decoded field '{$key}' should contain 'Option A'.");
        }
    }

    // =========================================================================
    // Case D — missing meta keys yield null
    // =========================================================================

    public function test_landlord_missing_meta_keys_yield_null(): void
    {
        $overrides = [];
        foreach (self::LANDLORD_AUDIT_SCALAR_FIELDS as $key) {
            $overrides[$key] = null;
        }
        foreach (self::LANDLORD_AUDIT_ARRAY_FIELDS as $key) {
            $overrides[$key] = null;
        }

        $service = $this->makeService();
        $stub    = $this->makeLandlordListingStub($overrides);

        $service->method('findListing')->willReturn($stub);
        $service->method('findPropertyDnaProfile')->willReturn(null);
        $service->method('findPropertyLocationDna')->willReturn(null);
        $service->method('findBuyerTenantDnaProfile')->willReturn(null);
        $service->method('findCompatibilityScore')->willReturn(null);
        $service->method('findAcceptedBidSummary')->willReturn(null);

        $ctx     = $service->buildForListing('landlord', 1);
        $listing = $ctx['listing'];

        $allFields = array_merge(self::LANDLORD_AUDIT_SCALAR_FIELDS, self::LANDLORD_AUDIT_ARRAY_FIELDS);
        foreach ($allFields as $field) {
            $this->assertArrayHasKey($field, $listing,
                "listing['{$field}'] should exist as a key even when null.");
            $this->assertNull($listing[$field],
                "listing['{$field}'] should be null when meta is missing. Got: " . var_export($listing[$field], true));
        }
    }

    // =========================================================================
    // Case E — static governance scan: no write calls in AskAiContextBuilderService
    // =========================================================================

    public function test_no_write_calls_in_context_builder_service(): void
    {
        $path    = dirname(__DIR__, 3) . '/app/Services/AskAi/AskAiContextBuilderService.php';
        $content = file_get_contents($path);

        $strippedLines = array_filter(
            explode("\n", $content),
            static function (string $line): bool {
                $trimmed = ltrim($line);
                return !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
            }
        );
        $stripped = implode("\n", $strippedLines);

        foreach (self::WRITE_PATTERNS as $pattern) {
            $this->assertStringNotContainsString(
                $pattern,
                $stripped,
                "Governance violation: '{$pattern}' found in AskAiContextBuilderService (outside comments)."
            );
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Assert that the string is NOT a JSON-encoded value (array or object).
     */
    private function assertNotJsonString(string $value, string $message = ''): void
    {
        $decoded = json_decode($value, true);
        $isJson  = (json_last_error() === JSON_ERROR_NONE && is_array($decoded));
        $this->assertFalse($isJson, $message ?: "Value '{$value}' appears to be raw JSON.");
    }
}
