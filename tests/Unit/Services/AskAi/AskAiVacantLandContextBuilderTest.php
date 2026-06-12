<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\Dna\PropertyIntelligenceProfileService;
use App\Services\LocationDna\LocationDnaIntelligenceContextService;
use App\Services\LocationDna\LocationDnaMarketingContextService;
use PHPUnit\Framework\TestCase;

/**
 * AskAiVacantLandContextBuilderTest
 *
 * Pure unit tests for Vacant Land (VL) field wiring in the seller branch of
 * AskAiContextBuilderService::extractFactualFields().
 *
 * Coverage:
 *   VL-1  Unconditional lot/land fields appear for any seller property type
 *          (zoning, total_acreage, waterfront, water_access, lot_dimensions).
 *   VL-2  VL-only fields are included when property_type === 'Vacant Land'.
 *   VL-3  VL-only fields are absent when property_type !== 'Vacant Land'.
 *   VL-4  JSON-encoded array fields are decoded to comma-separated strings.
 *   VL-5  VL-only fields default to null when Vacant Land but values are absent.
 *
 * No database, no Laravel container — all stubs are built in memory.
 */
class AskAiVacantLandContextBuilderTest extends TestCase
{
    // =========================================================================
    // Shared helpers
    // =========================================================================

    private function makeIntelligenceServiceMock(): PropertyIntelligenceProfileService
    {
        return $this->getMockBuilder(PropertyIntelligenceProfileService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['buildPayloadReadOnly'])
            ->getMock();
    }

    private function makeLocationDnaIntelligenceMock(): LocationDnaIntelligenceContextService
    {
        $mock = $this->getMockBuilder(LocationDnaIntelligenceContextService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getForListing'])
            ->getMock();
        $mock->method('getForListing')->willReturn([
            'success' => false,
            'status'  => 'missing',
            'listing_type' => 'seller',
            'listing_id'   => 1,
            'location_intelligence_context' => null,
            'error' => 'No property_location_dna record found',
        ]);
        return $mock;
    }

    private function makeLocationDnaMarketingMock(): LocationDnaMarketingContextService
    {
        $mock = $this->getMockBuilder(LocationDnaMarketingContextService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getForListing'])
            ->getMock();
        $mock->method('getForListing')->willReturn([
            'success' => false,
            'status'  => 'missing',
            'listing_type' => 'seller',
            'listing_id'   => 1,
            'marketing_location_context' => null,
            'error' => 'No property_location_dna record found',
        ]);
        return $mock;
    }

    private function makeService(): AskAiContextBuilderService
    {
        return $this->getMockBuilder(AskAiContextBuilderService::class)
            ->setConstructorArgs([
                $this->makeIntelligenceServiceMock(),
                $this->makeLocationDnaIntelligenceMock(),
                $this->makeLocationDnaMarketingMock(),
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
     * Build a listing stub that simulates native columns + EAV meta.
     *
     * @param  array $native  Native column values keyed by column name.
     * @param  array $meta    EAV meta values keyed by meta key.
     */
    private function makeListingStub(array $native = [], array $meta = []): object
    {
        return new class($native, $meta) {
            public int    $id          = 1;
            public bool   $is_approved = true;
            public string $created_at  = '2026-01-01 00:00:00';
            public string $updated_at  = '2026-01-01 00:00:00';
            private array $metaStore;
            private array $dynamicProps = [];

            public function __construct(array $native, array $meta)
            {
                $this->metaStore = $meta;
                foreach ($native as $key => $value) {
                    $this->dynamicProps[$key] = $value;
                }
            }

            public function __set(string $name, mixed $value): void
            {
                $this->dynamicProps[$name] = $value;
            }

            public function __get(string $name): mixed
            {
                return $this->dynamicProps[$name] ?? null;
            }

            public function __isset(string $name): bool
            {
                return isset($this->dynamicProps[$name]) || array_key_exists($name, $this->dynamicProps);
            }

            public function info(string $key): string|false
            {
                return array_key_exists($key, $this->metaStore)
                    ? ($this->metaStore[$key] === null ? false : (string) $this->metaStore[$key])
                    : false;
            }
        };
    }

    // =========================================================================
    // VL-1 — Unconditional lot/land fields present for any property type
    // =========================================================================

    public function test_VL1_zoning_appears_in_seller_listing_context_for_any_property_type(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStub([], ['zoning' => 'RSF-1', 'property_type' => 'Single Family'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('zoning', $result['listing'],
            "'zoning' must appear in the seller listing context for any property type");
        $this->assertSame('RSF-1', $result['listing']['zoning']);
    }

    public function test_VL1_total_acreage_appears_in_seller_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStub([], ['total_acreage' => '1.25'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('total_acreage', $result['listing'],
            "'total_acreage' must appear in seller listing context");
        $this->assertSame('1.25', $result['listing']['total_acreage']);
    }

    public function test_VL1_waterfront_appears_in_seller_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStub([], ['waterfront' => 'yes'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('waterfront', $result['listing'],
            "'waterfront' must appear in seller listing context");
        $this->assertSame('yes', $result['listing']['waterfront']);
    }

    public function test_VL1_water_access_decoded_from_json_in_seller_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStub([], ['water_access' => json_encode(['Lake', 'Canal - Freshwater'])])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('water_access', $result['listing'],
            "'water_access' must appear in seller listing context");
        $this->assertSame('Lake, Canal - Freshwater', $result['listing']['water_access']);
    }

    public function test_VL1_lot_dimensions_appears_in_seller_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStub([], ['lot_dimensions' => '100x200'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('lot_dimensions', $result['listing'],
            "'lot_dimensions' must appear in seller listing context");
        $this->assertSame('100x200', $result['listing']['lot_dimensions']);
    }

    // =========================================================================
    // VL-2 — VL-only fields populated when property_type === 'Vacant Land'
    // =========================================================================

    public function test_VL2_current_use_populated_for_vacant_land_seller(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStub([], [
                'property_type' => 'Vacant Land',
                'current_use'   => json_encode(['Agricultural', 'Pasture']),
            ])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('current_use', $result['listing'],
            "'current_use' must appear for Vacant Land seller listing");
        $this->assertSame('Agricultural, Pasture', $result['listing']['current_use']);
    }

    public function test_VL2_current_adjacent_use_populated_for_vacant_land_seller(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStub([], [
                'property_type'        => 'Vacant Land',
                'current_adjacent_use' => json_encode(['Residential']),
            ])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('current_adjacent_use', $result['listing']);
        $this->assertSame('Residential', $result['listing']['current_adjacent_use']);
    }

    public function test_VL2_site_utilities_populated_for_vacant_land_seller(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStub([], [
                'property_type'    => 'Vacant Land',
                'water_available'  => 'Yes',
                'sewer_available'  => 'No',
                'electric_available' => 'Yes',
                'gas_available'    => 'No',
                'telecom_available' => 'Fiber',
            ])
        );

        $result = $service->buildForListing('seller', 1);
        $listing = $result['listing'];

        $this->assertSame('Yes', $listing['water_available'],   'water_available must be populated for VL');
        $this->assertSame('No',  $listing['sewer_available'],   'sewer_available must be populated for VL');
        $this->assertSame('Yes', $listing['electric_available'], 'electric_available must be populated for VL');
        $this->assertSame('No',  $listing['gas_available'],     'gas_available must be populated for VL');
        $this->assertSame('Fiber', $listing['telecom_available'], 'telecom_available must be populated for VL');
    }

    public function test_VL2_road_fields_populated_for_vacant_land_seller(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStub([], [
                'property_type'    => 'Vacant Land',
                'road_frontage'    => json_encode(['County Road', 'Private']),
                'road_surface_type' => json_encode(['Paved']),
                'front_footage'    => '150',
            ])
        );

        $result = $service->buildForListing('seller', 1);
        $listing = $result['listing'];

        $this->assertSame('County Road, Private', $listing['road_frontage'],
            'road_frontage must be decoded from JSON for VL');
        $this->assertSame('Paved', $listing['road_surface_type'],
            'road_surface_type must be decoded from JSON for VL');
        $this->assertSame('150', $listing['front_footage'],
            'front_footage must be populated for VL');
    }

    public function test_VL2_wells_and_septics_populated_for_vacant_land_seller(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStub([], [
                'property_type'    => 'Vacant Land',
                'number_of_wells'  => '2',
                'number_of_septics' => '1',
            ])
        );

        $result = $service->buildForListing('seller', 1);
        $listing = $result['listing'];

        $this->assertSame('2', $listing['number_of_wells'],   'number_of_wells must be populated for VL');
        $this->assertSame('1', $listing['number_of_septics'], 'number_of_septics must be populated for VL');
    }

    public function test_VL2_land_features_populated_for_vacant_land_seller(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStub([], [
                'property_type' => 'Vacant Land',
                'fences'        => json_encode(['Wood', 'Chain Link']),
                'vegetation'    => json_encode(['Trees', 'Brush']),
                'buildable'     => 'Yes',
                'easements'     => json_encode(['Utility Easement']),
            ])
        );

        $result = $service->buildForListing('seller', 1);
        $listing = $result['listing'];

        $this->assertSame('Wood, Chain Link', $listing['fences'],
            'fences must be decoded from JSON for VL');
        $this->assertSame('Trees, Brush', $listing['vegetation'],
            'vegetation must be decoded from JSON for VL');
        $this->assertSame('Yes', $listing['buildable'],
            'buildable must be populated for VL');
        $this->assertSame('Utility Easement', $listing['easements'],
            'easements must be decoded from JSON for VL');
    }

    // =========================================================================
    // VL-3 — VL-only fields absent when property_type !== 'Vacant Land'
    // =========================================================================

    public function test_VL3_vl_only_fields_absent_for_single_family_seller(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStub([], [
                'property_type' => 'Single Family',
                'current_use'   => json_encode(['Residential']),
                'fences'        => json_encode(['Wood']),
                'buildable'     => 'Yes',
            ])
        );

        $result = $service->buildForListing('seller', 1);
        $listing = $result['listing'];

        $vlOnlyFields = [
            'current_use', 'current_adjacent_use', 'water_available', 'sewer_available',
            'electric_available', 'gas_available', 'telecom_available', 'road_frontage',
            'road_surface_type', 'front_footage', 'number_of_wells', 'number_of_septics',
            'fences', 'vegetation', 'buildable', 'easements',
        ];

        foreach ($vlOnlyFields as $field) {
            $this->assertArrayNotHasKey($field, $listing,
                "VL-only field '{$field}' must NOT appear in listing context for non-VL property type");
        }
    }

    public function test_VL3_vl_only_fields_absent_when_property_type_is_null(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStub([], ['current_use' => json_encode(['Pasture'])])
        );

        $result = $service->buildForListing('seller', 1);
        $listing = $result['listing'];

        $this->assertArrayNotHasKey('current_use', $listing,
            "'current_use' must not appear when property_type is null (non-VL)");
        $this->assertArrayNotHasKey('buildable', $listing,
            "'buildable' must not appear when property_type is null (non-VL)");
    }

    // =========================================================================
    // VL-4 — JSON-encoded array fields are decoded to comma-separated strings
    // =========================================================================

    public function test_VL4_water_access_scalar_string_returned_as_is(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStub([], ['water_access' => 'Lake'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertSame('Lake', $result['listing']['water_access'],
            'Scalar water_access string must be returned as-is');
    }

    public function test_VL4_current_use_json_array_decoded_to_comma_string_for_vl(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStub([], [
                'property_type' => 'Vacant Land',
                'current_use'   => json_encode(['Agricultural', 'Pasture', 'Timber']),
            ])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertSame('Agricultural, Pasture, Timber', $result['listing']['current_use'],
            'current_use JSON array must be decoded to comma-separated string');
    }

    // =========================================================================
    // VL-5 — VL-only fields default to null when Vacant Land but values absent
    // =========================================================================

    public function test_VL5_vl_only_fields_null_when_absent_for_vacant_land(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeListingStub([], ['property_type' => 'Vacant Land'])
        );

        $result = $service->buildForListing('seller', 1);
        $listing = $result['listing'];

        $vlOnlyFields = [
            'current_use', 'current_adjacent_use', 'water_available', 'sewer_available',
            'electric_available', 'gas_available', 'telecom_available', 'road_frontage',
            'road_surface_type', 'front_footage', 'number_of_wells', 'number_of_septics',
            'fences', 'vegetation', 'buildable', 'easements',
        ];

        foreach ($vlOnlyFields as $field) {
            $this->assertArrayHasKey($field, $listing,
                "VL-only field '{$field}' must be present (even if null) for Vacant Land listings");
            $this->assertNull($listing[$field],
                "VL-only field '{$field}' must be null when no meta value is set");
        }
    }
}
