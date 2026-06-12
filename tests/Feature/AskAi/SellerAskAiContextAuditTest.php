<?php

namespace Tests\Feature\AskAi;

use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use App\Services\AskAi\AskAiContextBuilderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Seller Ask AI Context Audit — Regression Tests (Task #2524)
 *
 * Verifies that extractFactualFields() for the seller role:
 *   1. Reads water_view from the 'water_view' EAV key (not 'view_preference')
 *   2. Includes every factual field identified by the audit as missing or
 *      mis-keyed — structural, utilities, HOA/CDD, flood, tax/legal,
 *      transaction/occupancy, and waterfront fields.
 *
 * Strategy: create a SellerAgentAuction with saveMeta() populated for each
 * field under test, then call extractListingFields() via buildForListing()
 * reflection and assert ctx['listing'][key] is not null.
 *
 * Since extractListingFields() is protected, we call it indirectly through
 * buildForListing(), which wraps the full context including the listing sub-array.
 */
class SellerAskAiContextAuditTest extends TestCase
{
    use DatabaseTransactions;

    private AskAiContextBuilderService $contextBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contextBuilder = app(AskAiContextBuilderService::class);
    }

    private function makeAuction(array $metaValues = []): SellerAgentAuction
    {
        $user = User::factory()->create();

        $auction = SellerAgentAuction::create([
            'user_id'     => $user->id,
            'title'       => 'Ask AI Audit Test Listing',
            'is_approved' => true,
            'is_draft'    => false,
            'address'     => '999 Audit Lane',
        ]);

        $auction->saveMeta('workflow_type', 'offer_listing');

        foreach ($metaValues as $key => $value) {
            $auction->saveMeta($key, $value);
        }

        return $auction;
    }

    private function getSellerListingContext(int $auctionId): array
    {
        $ctx = $this->contextBuilder->buildForListing('seller', $auctionId);
        return $ctx['listing'] ?? [];
    }

    // =========================================================================
    // 1 — water_view decoded from 'view_preference' EAV key (live-DB audit fix)
    // Live-DB audit (June 2026) confirmed all roles store view selections under
    // 'view_preference'. The legacy 'water_view' EAV key does not exist in any
    // role's meta table. Output key is kept as 'water_view' for prompt contract.
    // =========================================================================

    public function test_water_view_reads_from_view_preference_meta_key(): void
    {
        $auction = $this->makeAuction([
            'view_preference' => json_encode(['Lake', 'Pond']),
        ]);

        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('water_view', $listing,
            'Seller context must include water_view key');
        $this->assertNotNull($listing['water_view'],
            'water_view must not be null when the view_preference meta key is set');
        $this->assertStringContainsStringIgnoringCase('Lake', (string) $listing['water_view'],
            'water_view must read from the view_preference meta key');
        $this->assertStringContainsStringIgnoringCase('Pond', (string) $listing['water_view'],
            'water_view must decode all values from the view_preference JSON array');
    }

    public function test_water_view_is_null_when_no_view_meta_is_set(): void
    {
        $auction = $this->makeAuction([]);

        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('water_view', $listing);
        $this->assertNull($listing['water_view'],
            'water_view must be null when no view_preference meta key is set');
    }

    // =========================================================================
    // 2 — Structural / physical fields
    // =========================================================================

    public function test_seller_context_includes_lot_size_from_total_acreage(): void
    {
        $auction = $this->makeAuction(['total_acreage' => '0.45']);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('lot_size', $listing);
        $this->assertEquals('0.45', $listing['lot_size']);
    }

    public function test_seller_context_includes_lot_size_falls_back_to_min_acreage(): void
    {
        $auction = $this->makeAuction(['min_acreage' => '0.22']);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('lot_size', $listing);
        $this->assertEquals('0.22', $listing['lot_size']);
    }

    public function test_seller_context_includes_lot_dimensions(): void
    {
        $auction = $this->makeAuction(['lot_dimensions' => '80x120']);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('lot_dimensions', $listing);
        $this->assertEquals('80x120', $listing['lot_dimensions']);
    }

    public function test_seller_context_includes_zoning(): void
    {
        $auction = $this->makeAuction(['zoning' => 'RSF-1']);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('zoning', $listing);
        $this->assertEquals('RSF-1', $listing['zoning']);
    }

    public function test_seller_context_includes_waterfront(): void
    {
        $auction = $this->makeAuction(['waterfront' => 'yes']);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('waterfront', $listing);
        $this->assertEquals('yes', $listing['waterfront']);
    }

    public function test_seller_context_includes_water_access(): void
    {
        $auction = $this->makeAuction(['water_access' => json_encode(['Lake', 'Pond'])]);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('water_access', $listing);
        $this->assertNotNull($listing['water_access']);
        $this->assertStringContainsStringIgnoringCase('Lake', (string) $listing['water_access']);
    }

    public function test_seller_context_includes_interior_features(): void
    {
        $auction = $this->makeAuction([
            'interior_features' => json_encode(['Crown Molding', 'Walk-In Closet(s)']),
        ]);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('interior_features', $listing);
        $this->assertNotNull($listing['interior_features']);
        $this->assertStringContainsStringIgnoringCase('Crown Molding', (string) $listing['interior_features']);
    }

    public function test_seller_context_includes_appliances(): void
    {
        $auction = $this->makeAuction([
            'appliances' => json_encode(['Dishwasher', 'Refrigerator']),
        ]);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('appliances', $listing);
        $this->assertNotNull($listing['appliances']);
        $this->assertStringContainsStringIgnoringCase('Dishwasher', (string) $listing['appliances']);
    }

    public function test_seller_context_includes_roof_type(): void
    {
        $auction = $this->makeAuction(['roof_type' => json_encode(['Shingle'])]);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('roof_type', $listing);
        $this->assertNotNull($listing['roof_type']);
        $this->assertStringContainsStringIgnoringCase('Shingle', (string) $listing['roof_type']);
    }

    public function test_seller_context_includes_exterior_construction(): void
    {
        $auction = $this->makeAuction([
            'exterior_construction' => json_encode(['Block', 'Stucco']),
        ]);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('exterior_construction', $listing);
        $this->assertNotNull($listing['exterior_construction']);
        $this->assertStringContainsStringIgnoringCase('Block', (string) $listing['exterior_construction']);
    }

    public function test_seller_context_includes_foundation(): void
    {
        $auction = $this->makeAuction(['foundation' => json_encode(['Slab'])]);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('foundation', $listing);
        $this->assertNotNull($listing['foundation']);
        $this->assertStringContainsStringIgnoringCase('Slab', (string) $listing['foundation']);
    }

    public function test_seller_context_includes_heating_and_fuel(): void
    {
        $auction = $this->makeAuction([
            'heating_and_fuel' => json_encode(['Central', 'Electric']),
        ]);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('heating_and_fuel', $listing);
        $this->assertNotNull($listing['heating_and_fuel']);
        $this->assertStringContainsStringIgnoringCase('Central', (string) $listing['heating_and_fuel']);
    }

    public function test_seller_context_includes_air_conditioning(): void
    {
        $auction = $this->makeAuction(['air_conditioning' => json_encode(['Central Air'])]);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('air_conditioning', $listing);
        $this->assertNotNull($listing['air_conditioning']);
        $this->assertStringContainsStringIgnoringCase('Central Air', (string) $listing['air_conditioning']);
    }

    public function test_seller_context_includes_water(): void
    {
        $auction = $this->makeAuction(['water' => json_encode(['Public'])]);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('water', $listing);
        $this->assertNotNull($listing['water']);
        $this->assertStringContainsStringIgnoringCase('Public', (string) $listing['water']);
    }

    public function test_seller_context_includes_sewer(): void
    {
        $auction = $this->makeAuction(['sewer' => json_encode(['Public Sewer'])]);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('sewer', $listing);
        $this->assertNotNull($listing['sewer']);
        $this->assertStringContainsStringIgnoringCase('Public Sewer', (string) $listing['sewer']);
    }

    public function test_seller_context_includes_utilities(): void
    {
        $auction = $this->makeAuction([
            'utilities' => json_encode(['Cable Available', 'Electricity Connected']),
        ]);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('utilities', $listing);
        $this->assertNotNull($listing['utilities']);
        $this->assertStringContainsStringIgnoringCase('Cable', (string) $listing['utilities']);
    }

    // =========================================================================
    // 3 — Transaction / Occupancy fields
    // =========================================================================

    public function test_seller_context_includes_sale_provision(): void
    {
        $auction = $this->makeAuction(['sale_provision' => json_encode(['Standard Sale'])]);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('sale_provision', $listing);
        $this->assertNotNull($listing['sale_provision']);
    }

    public function test_seller_context_includes_offered_financing(): void
    {
        $auction = $this->makeAuction([
            'offered_financing' => json_encode(['Cash', 'Conventional']),
        ]);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('offered_financing', $listing);
        $this->assertNotNull($listing['offered_financing']);
        $this->assertStringContainsStringIgnoringCase('Cash', (string) $listing['offered_financing']);
    }

    public function test_seller_context_includes_occupant_status(): void
    {
        $auction = $this->makeAuction(['occupant_status' => 'Owner']);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('occupant_status', $listing);
        $this->assertEquals('Owner', $listing['occupant_status']);
    }

    public function test_seller_context_includes_furnished_from_building_features(): void
    {
        $auction = $this->makeAuction([
            'building_features' => json_encode(['Furnished', 'Sprinkler System']),
        ]);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('furnished', $listing);
        $this->assertNotNull($listing['furnished'],
            'furnished must be extracted from building_features when Furnished is present');
        $this->assertStringContainsStringIgnoringCase('Furnished', (string) $listing['furnished']);
    }

    public function test_seller_context_furnished_is_null_when_not_in_building_features(): void
    {
        $auction = $this->makeAuction([
            'building_features' => json_encode(['Sprinkler System', 'Gutters']),
        ]);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('furnished', $listing);
        $this->assertNull($listing['furnished'],
            'furnished must be null when building_features contains no furnished-related values');
    }

    // =========================================================================
    // 4 — HOA / CDD / Special Assessments fields
    // =========================================================================

    public function test_seller_context_includes_association_name(): void
    {
        $auction = $this->makeAuction(['association_name' => 'Sunridge HOA']);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('association_name', $listing);
        $this->assertEquals('Sunridge HOA', $listing['association_name']);
    }

    public function test_seller_context_includes_association_fee_includes(): void
    {
        $auction = $this->makeAuction([
            'association_fee_includes' => json_encode(['Maintenance Grounds', 'Pool']),
        ]);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('association_fee_includes', $listing);
        $this->assertNotNull($listing['association_fee_includes']);
        $this->assertStringContainsStringIgnoringCase('Pool', (string) $listing['association_fee_includes']);
    }

    public function test_seller_context_includes_has_cdd(): void
    {
        $auction = $this->makeAuction(['has_cdd' => 'yes']);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('has_cdd', $listing);
        $this->assertEquals('yes', $listing['has_cdd']);
    }

    public function test_seller_context_includes_annual_cdd_fee(): void
    {
        $auction = $this->makeAuction(['annual_cdd_fee' => '1200.00']);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('annual_cdd_fee', $listing);
        $this->assertEquals('1200.00', $listing['annual_cdd_fee']);
    }

    public function test_seller_context_includes_has_special_assessments(): void
    {
        $auction = $this->makeAuction(['has_special_assessments' => 'no']);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('has_special_assessments', $listing);
        $this->assertEquals('no', $listing['has_special_assessments']);
    }

    public function test_seller_context_includes_special_assessment_amount(): void
    {
        $auction = $this->makeAuction(['special_assessment_amount' => '5000']);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('special_assessment_amount', $listing);
        $this->assertEquals('5000', $listing['special_assessment_amount']);
    }

    public function test_seller_context_includes_special_assessment_description(): void
    {
        $auction = $this->makeAuction(['special_assessment_description' => 'Road repaving']);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('special_assessment_description', $listing);
        $this->assertEquals('Road repaving', $listing['special_assessment_description']);
    }

    // =========================================================================
    // 5 — Flood Zone sub-fields
    // =========================================================================

    public function test_seller_context_includes_flood_zone_panel(): void
    {
        $auction = $this->makeAuction(['flood_zone_panel' => '12057C0215G']);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('flood_zone_panel', $listing);
        $this->assertEquals('12057C0215G', $listing['flood_zone_panel']);
    }

    public function test_seller_context_includes_flood_zone_date(): void
    {
        $auction = $this->makeAuction(['flood_zone_date' => '09/01/2021']);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('flood_zone_date', $listing);
        $this->assertEquals('09/01/2021', $listing['flood_zone_date']);
    }

    public function test_seller_context_includes_flood_insurance_required(): void
    {
        $auction = $this->makeAuction(['flood_insurance_required' => 'no']);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('flood_insurance_required', $listing);
        $this->assertEquals('no', $listing['flood_insurance_required']);
    }

    // =========================================================================
    // 6 — Tax / Legal fields
    // =========================================================================

    public function test_seller_context_includes_parcel_id(): void
    {
        $auction = $this->makeAuction(['parcel_id' => '19-30-17-45612-000-1410']);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('parcel_id', $listing);
        $this->assertEquals('19-30-17-45612-000-1410', $listing['parcel_id']);
    }

    public function test_seller_context_includes_tax_year(): void
    {
        $auction = $this->makeAuction(['tax_year' => '2023']);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('tax_year', $listing);
        $this->assertEquals('2023', $listing['tax_year']);
    }

    public function test_seller_context_includes_legal_description(): void
    {
        $auction = $this->makeAuction([
            'legal_description' => 'SUNRIDGE ESTATES LOT 14 BLOCK 2 PB 45 PG 12',
        ]);
        $listing = $this->getSellerListingContext($auction->id);

        $this->assertArrayHasKey('legal_description', $listing);
        $this->assertEquals(
            'SUNRIDGE ESTATES LOT 14 BLOCK 2 PB 45 PG 12',
            $listing['legal_description']
        );
    }

    // =========================================================================
    // 7 — All new context keys exist in the listing array (presence check)
    // =========================================================================

    public function test_all_new_ask_ai_context_keys_are_present_in_seller_listing(): void
    {
        $auction = $this->makeAuction([
            'water_view'                     => json_encode(['Lake']),
            'total_acreage'                  => '0.22',
            'lot_dimensions'                 => '80x120',
            'zoning'                         => 'RSF-1',
            'waterfront'                     => 'no',
            'water_access'                   => json_encode(['Lake']),
            'interior_features'              => json_encode(['Crown Molding']),
            'appliances'                     => json_encode(['Dishwasher']),
            'roof_type'                      => json_encode(['Shingle']),
            'exterior_construction'          => json_encode(['Block']),
            'foundation'                     => json_encode(['Slab']),
            'heating_and_fuel'               => json_encode(['Central']),
            'air_conditioning'               => json_encode(['Central Air']),
            'water'                          => json_encode(['Public']),
            'sewer'                          => json_encode(['Public Sewer']),
            'utilities'                      => json_encode(['Electricity Connected']),
            'sale_provision'                 => json_encode(['Standard Sale']),
            'offered_financing'              => json_encode(['Cash']),
            'occupant_status'                => 'Owner',
            'building_features'              => json_encode(['Furnished']),
            'association_name'               => 'Test HOA',
            'association_fee_includes'       => json_encode(['Pool']),
            'has_cdd'                        => 'no',
            'annual_cdd_fee'                 => '0',
            'has_special_assessments'        => 'no',
            'special_assessment_amount'      => '0',
            'special_assessment_description' => 'None',
            'flood_zone_panel'               => '12057C0215G',
            'flood_zone_date'                => '09/01/2021',
            'flood_insurance_required'       => 'no',
            'parcel_id'                      => '12-34-56-789',
            'tax_year'                       => '2023',
            'legal_description'              => 'LOT 1 BLOCK A',
        ]);

        $listing = $this->getSellerListingContext($auction->id);

        $expectedKeys = [
            'water_view', 'lot_size', 'lot_dimensions', 'zoning', 'waterfront',
            'water_access', 'interior_features', 'appliances', 'roof_type',
            'exterior_construction', 'foundation', 'heating_and_fuel', 'air_conditioning',
            'water', 'sewer', 'utilities', 'sale_provision', 'offered_financing',
            'occupant_status', 'furnished', 'association_name', 'association_fee_includes',
            'has_cdd', 'annual_cdd_fee', 'has_special_assessments', 'special_assessment_amount',
            'special_assessment_description', 'flood_zone_panel', 'flood_zone_date',
            'flood_insurance_required', 'parcel_id', 'tax_year', 'legal_description',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey(
                $key,
                $listing,
                "Seller Ask AI context must include key: '{$key}'"
            );
        }
    }
}
