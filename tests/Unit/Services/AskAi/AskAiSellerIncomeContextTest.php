<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\Dna\PropertyIntelligenceProfileService;
use App\Services\LocationDna\LocationDnaIntelligenceContextService;
use App\Services\LocationDna\LocationDnaMarketingContextService;
use PHPUnit\Framework\TestCase;

/**
 * AskAiSellerIncomeContextTest
 *
 * Unit tests verifying that the 16 income / multifamily fields added to the
 * seller arm of AskAiContextBuilderService::extractFactualFields() are
 * correctly plumbed into the listing context output.
 *
 * Pure unit tests — no database, no Laravel TestCase, no DB traits.
 * All Eloquent calls are stubbed via the partial-mock pattern already used by
 * AskAiContextBuilderServiceTest.
 *
 * EAV key → context key mapping under test:
 *   gross_annual_income           → listing.gross_annual_income
 *   annual_operating_expenses     → listing.annual_operating_expenses
 *   minimum_annual_net_income     → listing.annual_net_income
 *   minimum_cap_rate              → listing.cap_rate
 *   unit_number                   → listing.total_units
 *   unit_buildings                → listing.total_buildings
 *   rent_roll_available           → listing.rent_roll_available
 *   operating_statement_available → listing.operating_statement_available
 *   assumable_occupancy_requirement → listing.occupancy_requirement
 *   monthly_income                → listing.income_requirement
 *   unit_type_configurations (JSON) → listing.unit_mix_summary (human summary)
 *   property_items (JSON)         → listing.property_items (decoded string)
 *   zoning                        → listing.zoning
 *   legal_description             → listing.legal_description
 *   parcel_id                     → listing.parcel_id
 *   tax_year                      → listing.tax_year
 */
class AskAiSellerIncomeContextTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers — mirrors AskAiContextBuilderServiceTest factory methods
    // -------------------------------------------------------------------------

    private function makeLocationDnaIntelligenceServiceMock(): LocationDnaIntelligenceContextService
    {
        $mock = $this->getMockBuilder(LocationDnaIntelligenceContextService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getForListing'])
            ->getMock();
        $mock->method('getForListing')->willReturn([
            'success'                       => false,
            'status'                        => 'missing',
            'listing_type'                  => 'seller',
            'listing_id'                    => 1,
            'location_intelligence_context' => null,
            'error'                         => 'No DNA record',
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
            'listing_type'               => 'seller',
            'listing_id'                 => 1,
            'marketing_location_context' => null,
            'error'                      => 'No DNA record',
        ]);
        return $mock;
    }

    private function makeIntelligenceServiceMock(): PropertyIntelligenceProfileService
    {
        return $this->getMockBuilder(PropertyIntelligenceProfileService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['buildPayloadReadOnly'])
            ->getMock();
    }

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
     * Create a listing stub with both native columns and EAV meta.
     */
    private function makeStub(array $native = [], array $meta = []): object
    {
        return new class($native, $meta) {
            public int    $id          = 1;
            public bool   $is_approved = true;
            public string $created_at  = '2026-01-01 00:00:00';
            public string $updated_at  = '2026-01-01 00:00:00';
            private array $metaStore;
            private array $dynProps = [];

            public function __construct(array $native, array $meta)
            {
                $this->metaStore = $meta;
                foreach ($native as $k => $v) {
                    $this->dynProps[$k] = $v;
                }
            }

            public function __get(string $name): mixed
            {
                return $this->dynProps[$name] ?? null;
            }

            public function __set(string $name, mixed $value): void
            {
                $this->dynProps[$name] = $value;
            }

            public function __isset(string $name): bool
            {
                return isset($this->dynProps[$name]);
            }

            public function info(string $key): mixed
            {
                return $this->metaStore[$key] ?? null;
            }
        };
    }

    // =========================================================================
    // gross_annual_income
    // =========================================================================

    public function test_gross_annual_income_appears_in_seller_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeStub([], ['gross_annual_income' => '120000'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('gross_annual_income', $result['listing'],
            'listing context must include gross_annual_income for seller');
        $this->assertSame('120000', $result['listing']['gross_annual_income']);
    }

    // =========================================================================
    // annual_operating_expenses
    // =========================================================================

    public function test_annual_operating_expenses_appears_in_seller_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeStub([], ['annual_operating_expenses' => '45000'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('annual_operating_expenses', $result['listing'],
            'listing context must include annual_operating_expenses for seller');
        $this->assertSame('45000', $result['listing']['annual_operating_expenses']);
    }

    // =========================================================================
    // annual_net_income (EAV key: minimum_annual_net_income)
    // =========================================================================

    public function test_annual_net_income_appears_in_seller_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeStub([], ['minimum_annual_net_income' => '75000'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('annual_net_income', $result['listing'],
            'listing context must include annual_net_income (from minimum_annual_net_income EAV key)');
        $this->assertSame('75000', $result['listing']['annual_net_income']);
    }

    // =========================================================================
    // cap_rate (EAV key: minimum_cap_rate)
    // =========================================================================

    public function test_cap_rate_appears_in_seller_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeStub([], ['minimum_cap_rate' => '6.5'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('cap_rate', $result['listing'],
            'listing context must include cap_rate (from minimum_cap_rate EAV key)');
        $this->assertSame('6.5', $result['listing']['cap_rate']);
    }

    // =========================================================================
    // total_units (EAV key: unit_number)
    // =========================================================================

    public function test_total_units_appears_in_seller_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeStub([], ['unit_number' => '12'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('total_units', $result['listing'],
            'listing context must include total_units (from unit_number EAV key)');
        $this->assertSame('12', $result['listing']['total_units']);
    }

    // =========================================================================
    // total_buildings (EAV key: unit_buildings)
    // =========================================================================

    public function test_total_buildings_appears_in_seller_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeStub([], ['unit_buildings' => '3'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('total_buildings', $result['listing'],
            'listing context must include total_buildings (from unit_buildings EAV key)');
        $this->assertSame('3', $result['listing']['total_buildings']);
    }

    // =========================================================================
    // rent_roll_available
    // =========================================================================

    public function test_rent_roll_available_appears_in_seller_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeStub([], ['rent_roll_available' => 'Yes'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('rent_roll_available', $result['listing'],
            'listing context must include rent_roll_available for seller');
        $this->assertSame('Yes', $result['listing']['rent_roll_available']);
    }

    // =========================================================================
    // operating_statement_available
    // =========================================================================

    public function test_operating_statement_available_appears_in_seller_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeStub([], ['operating_statement_available' => 'No'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('operating_statement_available', $result['listing'],
            'listing context must include operating_statement_available for seller');
        $this->assertSame('No', $result['listing']['operating_statement_available']);
    }

    // =========================================================================
    // occupancy_requirement (EAV key: assumable_occupancy_requirement)
    // =========================================================================

    public function test_occupancy_requirement_appears_in_seller_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeStub([], ['assumable_occupancy_requirement' => '90%'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('occupancy_requirement', $result['listing'],
            'listing context must include occupancy_requirement (from assumable_occupancy_requirement EAV key)');
        $this->assertSame('90%', $result['listing']['occupancy_requirement']);
    }

    // =========================================================================
    // income_requirement (EAV key: monthly_income)
    // =========================================================================

    public function test_income_requirement_appears_in_seller_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeStub([], ['monthly_income' => '5000'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('income_requirement', $result['listing'],
            'listing context must include income_requirement (from monthly_income EAV key)');
        $this->assertSame('5000', $result['listing']['income_requirement']);
    }

    // =========================================================================
    // unit_mix_summary (EAV key: unit_type_configurations JSON)
    // =========================================================================

    public function test_unit_mix_summary_appears_in_seller_listing_context_for_valid_json(): void
    {
        $configs = json_encode([
            ['number_of_units' => '4', 'beds_unit' => '1', 'baths_unit' => '1', 'expected_rent' => '1200'],
            ['number_of_units' => '2', 'beds_unit' => '2', 'baths_unit' => '2', 'expected_rent' => '1800'],
        ]);

        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeStub([], ['unit_type_configurations' => $configs])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('unit_mix_summary', $result['listing'],
            'listing context must include unit_mix_summary for seller when unit_type_configurations is set');
        $this->assertNotNull($result['listing']['unit_mix_summary'],
            'unit_mix_summary must not be null when valid configurations are provided');
        $this->assertStringContainsString('1BR', $result['listing']['unit_mix_summary'],
            'unit_mix_summary must include bedroom count labels');
        $this->assertStringContainsString('2BR', $result['listing']['unit_mix_summary']);
    }

    public function test_unit_mix_summary_is_null_when_no_unit_configurations_set(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeStub([], [])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('unit_mix_summary', $result['listing'],
            'unit_mix_summary key must always be present in seller context');
        $this->assertNull($result['listing']['unit_mix_summary'],
            'unit_mix_summary must be null when unit_type_configurations is absent');
    }

    // =========================================================================
    // property_items (EAV key: property_items JSON)
    // =========================================================================

    public function test_property_items_appears_in_seller_listing_context_decoded(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeStub([], ['property_items' => '["Duplex","Triplex"]'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('property_items', $result['listing'],
            'listing context must include property_items for seller');
        $this->assertStringContainsStringIgnoringCase('Duplex', (string) $result['listing']['property_items'],
            'property_items must decode the JSON and return a human-readable string');
    }

    // =========================================================================
    // Supplemental fields: zoning, legal_description, parcel_id, tax_year
    // =========================================================================

    public function test_zoning_appears_in_seller_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeStub([], ['zoning' => 'RMF-6'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('zoning', $result['listing']);
        $this->assertSame('RMF-6', $result['listing']['zoning']);
    }

    public function test_legal_description_appears_in_seller_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeStub([], ['legal_description' => 'LOT 12 BLOCK 4 SUNSET PARK'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('legal_description', $result['listing']);
        $this->assertSame('LOT 12 BLOCK 4 SUNSET PARK', $result['listing']['legal_description']);
    }

    public function test_parcel_id_appears_in_seller_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeStub([], ['parcel_id' => '12-34-56-789'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('parcel_id', $result['listing']);
        $this->assertSame('12-34-56-789', $result['listing']['parcel_id']);
    }

    public function test_tax_year_appears_in_seller_listing_context(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeStub([], ['tax_year' => '2024'])
        );

        $result = $service->buildForListing('seller', 1);

        $this->assertArrayHasKey('tax_year', $result['listing']);
        $this->assertSame('2024', $result['listing']['tax_year']);
    }

    // =========================================================================
    // Null-safety: absent income EAV keys must produce null context values
    // (not PHP errors) so the context shape is stable for the prompt layer.
    // =========================================================================

    public function test_income_context_keys_are_null_when_eav_absent(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(
            $this->makeStub([], [])
        );

        $result = $service->buildForListing('seller', 1);
        $listing = $result['listing'];

        $incomeKeys = [
            'gross_annual_income',
            'annual_operating_expenses',
            'annual_net_income',
            'cap_rate',
            'total_units',
            'total_buildings',
            'rent_roll_available',
            'operating_statement_available',
            'occupancy_requirement',
            'income_requirement',
            'unit_mix_summary',
            'property_items',
            'zoning',
            'legal_description',
            'parcel_id',
            'tax_year',
        ];

        foreach ($incomeKeys as $key) {
            $this->assertArrayHasKey($key, $listing,
                "Income key '{$key}' must always be present in seller context (even when null)");
            $this->assertNull($listing[$key],
                "Income key '{$key}' must be null when the EAV meta is absent");
        }
    }
}
