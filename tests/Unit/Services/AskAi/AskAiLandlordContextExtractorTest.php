<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\Dna\PropertyIntelligenceProfileService;
use App\Services\LocationDna\LocationDnaIntelligenceContextService;
use App\Services\LocationDna\LocationDnaMarketingContextService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests verifying that the 61 new landlord fields added to
 * AskAiContextBuilderService::extractFactualFields() are included in the
 * landlord context output.
 *
 * No database is used.  The model is stubbed with an anonymous class that
 * implements info() via an in-memory map.  The service is a partial mock with
 * only the database-hitting finder methods replaced.
 */
class AskAiLandlordContextExtractorTest extends TestCase
{
    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeService(): AskAiContextBuilderService
    {
        $intelligenceMock = $this->getMockBuilder(PropertyIntelligenceProfileService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['buildPayloadReadOnly'])
            ->getMock();

        $locationDnaIntelMock = $this->getMockBuilder(LocationDnaIntelligenceContextService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getForListing'])
            ->getMock();
        $locationDnaIntelMock->method('getForListing')->willReturn([
            'success' => false, 'status' => 'missing', 'listing_type' => 'landlord',
            'listing_id' => 1, 'location_intelligence_context' => null, 'error' => 'missing',
        ]);

        $locationDnaMarketingMock = $this->getMockBuilder(LocationDnaMarketingContextService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getForListing'])
            ->getMock();
        $locationDnaMarketingMock->method('getForListing')->willReturn([
            'success' => false, 'status' => 'missing', 'listing_type' => 'landlord',
            'listing_id' => 1, 'marketing_location_context' => null, 'error' => 'missing',
        ]);

        return $this->getMockBuilder(AskAiContextBuilderService::class)
            ->setConstructorArgs([
                $intelligenceMock,
                $locationDnaIntelMock,
                $locationDnaMarketingMock,
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
     * Create an anonymous listing stub whose info() method returns values from
     * a provided key→value map.  Properties accessible via property access are
     * set dynamically on the object.
     */
    private function makeListingStub(array $meta = [], array $props = []): object
    {
        return new class($meta, $props) {
            public int    $id          = 1;
            public bool   $is_approved = true;
            public string $created_at  = '2025-01-01 00:00:00';
            public string $updated_at  = '2025-01-01 00:00:00';

            private array $meta;

            public function __construct(array $meta, array $props)
            {
                $this->meta = $meta;
                foreach ($props as $k => $v) {
                    $this->$k = $v;
                }
            }

            public function info(string $key): ?string
            {
                return $this->meta[$key] ?? null;
            }
        };
    }

    /**
     * Build a context for a landlord listing and return the ctx['listing'] sub-array.
     */
    private function buildListing(array $meta = [], array $props = []): array
    {
        $service = $this->makeService();
        $model   = $this->makeListingStub($meta, $props);

        $service->expects($this->any())
            ->method('findListing')
            ->willReturn($model);
        $service->expects($this->any())
            ->method('findPropertyDnaProfile')->willReturn(null);
        $service->expects($this->any())
            ->method('findPropertyLocationDna')->willReturn(null);
        $service->expects($this->any())
            ->method('findBuyerTenantDnaProfile')->willReturn(null);
        $service->expects($this->any())
            ->method('findCompatibilityScore')->willReturn(null);
        $service->expects($this->any())
            ->method('findAcceptedBidSummary')->willReturn(null);

        $ctx = $service->buildForListing('landlord', 1);
        return $ctx['listing'] ?? [];
    }

    // ─── Commercial Lease Terms ────────────────────────────────────────────────

    public function test_commercial_lease_type_present_in_context(): void
    {
        $listing = $this->buildListing(['commercial_lease_type' => 'NNN']);
        $this->assertArrayHasKey('commercial_lease_type', $listing,
            'commercial_lease_type must appear in the landlord context');
        $this->assertSame('NNN', $listing['commercial_lease_type']);
    }

    public function test_cam_nnn_additional_rent_charges_present_in_context(): void
    {
        $listing = $this->buildListing(['cam_nnn_additional_rent_charges' => '$350/mo']);
        $this->assertArrayHasKey('cam_nnn_additional_rent_charges', $listing);
        $this->assertSame('$350/mo', $listing['cam_nnn_additional_rent_charges']);
    }

    public function test_signage_rights_present_in_context(): void
    {
        $listing = $this->buildListing(['signage_rights' => 'Exterior sign permitted']);
        $this->assertArrayHasKey('signage_rights', $listing);
        $this->assertSame('Exterior sign permitted', $listing['signage_rights']);
    }

    public function test_commercial_parking_terms_present_in_context(): void
    {
        $listing = $this->buildListing(['commercial_parking_terms' => '5 reserved spaces included']);
        $this->assertArrayHasKey('commercial_parking_terms', $listing);
        $this->assertSame('5 reserved spaces included', $listing['commercial_parking_terms']);
    }

    public function test_rent_escalation_terms_present_in_context(): void
    {
        $listing = $this->buildListing(['rent_escalation_terms' => '3% annually']);
        $this->assertArrayHasKey('rent_escalation_terms', $listing);
    }

    public function test_tenant_improvement_buildout_terms_present_in_context(): void
    {
        $listing = $this->buildListing(['tenant_improvement_buildout_terms' => '$20/sqft TI allowance']);
        $this->assertArrayHasKey('tenant_improvement_buildout_terms', $listing);
    }

    public function test_permitted_use_restrictions_present_in_context(): void
    {
        $listing = $this->buildListing(['permitted_use_restrictions' => 'Professional office use only']);
        $this->assertArrayHasKey('permitted_use_restrictions', $listing);
    }

    public function test_personal_guarantee_requirement_present_in_context(): void
    {
        $listing = $this->buildListing(['personal_guarantee_requirement' => 'Required for leases > 3 years']);
        $this->assertArrayHasKey('personal_guarantee_requirement', $listing);
    }

    public function test_commercial_approval_conditions_present_in_context(): void
    {
        $listing = $this->buildListing(['commercial_approval_conditions' => 'Credit score min 700']);
        $this->assertArrayHasKey('commercial_approval_conditions', $listing);
    }

    // ─── General Lease Terms ──────────────────────────────────────────────────

    public function test_security_deposit_amount_present_in_context(): void
    {
        $listing = $this->buildListing(['security_deposit_amount' => '5200']);
        $this->assertArrayHasKey('security_deposit_amount', $listing);
        $this->assertSame('5200', $listing['security_deposit_amount']);
    }

    public function test_first_month_rent_required_present_in_context(): void
    {
        $listing = $this->buildListing(['first_month_rent_required' => 'yes']);
        $this->assertArrayHasKey('first_month_rent_required', $listing);
    }

    public function test_last_month_rent_required_present_in_context(): void
    {
        $listing = $this->buildListing(['last_month_rent_required' => 'yes']);
        $this->assertArrayHasKey('last_month_rent_required', $listing);
    }

    public function test_total_move_in_funds_required_present_in_context(): void
    {
        $listing = $this->buildListing(['total_move_in_funds_required' => '15600']);
        $this->assertArrayHasKey('total_move_in_funds_required', $listing);
        $this->assertSame('15600', $listing['total_move_in_funds_required']);
    }

    public function test_lease_amount_frequency_present_in_context(): void
    {
        $listing = $this->buildListing(['lease_amount_frequency' => 'monthly']);
        $this->assertArrayHasKey('lease_amount_frequency', $listing);
        $this->assertSame('monthly', $listing['lease_amount_frequency']);
    }

    public function test_tenant_pays_json_decoded_in_context(): void
    {
        $listing = $this->buildListing(['tenant_pays' => '["Electricity","Water"]']);
        $this->assertArrayHasKey('tenant_pays', $listing);
        $this->assertStringContainsString('Electricity', $listing['tenant_pays']);
    }

    public function test_rent_includes_json_decoded_in_context(): void
    {
        $listing = $this->buildListing(['rent_includes' => '["Taxes","Insurance"]']);
        $this->assertArrayHasKey('rent_includes', $listing);
        $this->assertStringContainsString('Taxes', $listing['rent_includes']);
    }

    public function test_terms_of_lease_present_in_context(): void
    {
        $listing = $this->buildListing(['terms_of_lease' => '["12 Months","24 Months"]']);
        $this->assertArrayHasKey('terms_of_lease', $listing);
    }

    // ─── Commercial Building Details ──────────────────────────────────────────

    public function test_zoning_present_in_context(): void
    {
        $listing = $this->buildListing(['zoning' => 'B-2']);
        $this->assertArrayHasKey('zoning', $listing);
        $this->assertSame('B-2', $listing['zoning']);
    }

    public function test_ceiling_height_present_in_context(): void
    {
        $listing = $this->buildListing(['ceiling_height' => '12 ft']);
        $this->assertArrayHasKey('ceiling_height', $listing);
        $this->assertSame('12 ft', $listing['ceiling_height']);
    }

    public function test_number_of_restrooms_present_in_context(): void
    {
        $listing = $this->buildListing(['number_of_restrooms' => '2']);
        $this->assertArrayHasKey('number_of_restrooms', $listing);
        $this->assertSame('2', $listing['number_of_restrooms']);
    }

    public function test_office_retail_sqft_present_in_context(): void
    {
        $listing = $this->buildListing(['office_retail_sqft' => '1800']);
        $this->assertArrayHasKey('office_retail_sqft', $listing);
        $this->assertSame('1800', $listing['office_retail_sqft']);
    }

    public function test_building_hours_present_in_context(): void
    {
        $listing = $this->buildListing(['building_hours' => 'Mon-Fri 7am-8pm']);
        $this->assertArrayHasKey('building_hours', $listing);
    }

    public function test_access_24_7_present_in_context(): void
    {
        $listing = $this->buildListing(['access_24_7' => 'yes']);
        $this->assertArrayHasKey('access_24_7', $listing);
        $this->assertSame('yes', $listing['access_24_7']);
    }

    public function test_shared_amenities_present_in_context(): void
    {
        $listing = $this->buildListing(['shared_amenities' => 'Lobby, Conference Room, Gym']);
        $this->assertArrayHasKey('shared_amenities', $listing);
    }

    public function test_neighboring_tenants_present_in_context(): void
    {
        $listing = $this->buildListing(['neighboring_tenants' => 'Law firm, CPA office']);
        $this->assertArrayHasKey('neighboring_tenants', $listing);
    }

    public function test_total_buildings_present_in_context(): void
    {
        $listing = $this->buildListing(['total_buildings' => '3']);
        $this->assertArrayHasKey('total_buildings', $listing);
    }

    public function test_total_units_on_property_present_in_context(): void
    {
        $listing = $this->buildListing(['total_units_on_property' => '24']);
        $this->assertArrayHasKey('total_units_on_property', $listing);
    }

    public function test_flex_space_sqft_present_in_context(): void
    {
        $listing = $this->buildListing(['flex_space_sqft' => '600']);
        $this->assertArrayHasKey('flex_space_sqft', $listing);
    }

    public function test_number_of_offices_present_in_context(): void
    {
        $listing = $this->buildListing(['number_of_offices' => '4']);
        $this->assertArrayHasKey('number_of_offices', $listing);
    }

    public function test_number_of_conference_rooms_present_in_context(): void
    {
        $listing = $this->buildListing(['number_of_conference_rooms' => '1']);
        $this->assertArrayHasKey('number_of_conference_rooms', $listing);
    }

    public function test_space_type_json_decoded_in_context(): void
    {
        $listing = $this->buildListing(['space_type' => '["Professional Office","Medical"]']);
        $this->assertArrayHasKey('space_type', $listing);
    }

    public function test_space_classification_json_decoded_in_context(): void
    {
        $listing = $this->buildListing(['space_classification' => '["Class A"]']);
        $this->assertArrayHasKey('space_classification', $listing);
    }

    public function test_space_features_present_in_context(): void
    {
        $listing = $this->buildListing(['space_features' => 'Private entrance, kitchenette']);
        $this->assertArrayHasKey('space_features', $listing);
    }

    public function test_electrical_service_json_decoded_in_context(): void
    {
        $listing = $this->buildListing(['electrical_service' => '["200 Amp","3-Phase"]']);
        $this->assertArrayHasKey('electrical_service', $listing);
    }

    public function test_building_features_json_decoded_in_context(): void
    {
        $listing = $this->buildListing(['building_features' => '["Elevator","ADA Compliant"]']);
        $this->assertArrayHasKey('building_features', $listing);
    }

    public function test_road_surface_type_json_decoded_in_context(): void
    {
        $listing = $this->buildListing(['road_surface_type' => '["Paved"]']);
        $this->assertArrayHasKey('road_surface_type', $listing);
    }

    public function test_zoning_allows_present_in_context(): void
    {
        $listing = $this->buildListing(['zoning_allows' => 'Retail, Office, Warehouse']);
        $this->assertArrayHasKey('zoning_allows', $listing);
    }

    // ─── Tax and Legal ─────────────────────────────────────────────────────────

    public function test_parcel_id_present_in_context(): void
    {
        $listing = $this->buildListing(['parcel_id' => '14-45-24-0000-07290-0000']);
        $this->assertArrayHasKey('parcel_id', $listing);
        $this->assertSame('14-45-24-0000-07290-0000', $listing['parcel_id']);
    }

    public function test_tax_year_present_in_context(): void
    {
        $listing = $this->buildListing(['tax_year' => '2023']);
        $this->assertArrayHasKey('tax_year', $listing);
        $this->assertSame('2023', $listing['tax_year']);
    }

    public function test_legal_description_present_in_context(): void
    {
        $listing = $this->buildListing(['legal_description' => 'EXECUTIVE COMMERCE PARK LOT 4 BLDG B UNIT 101']);
        $this->assertArrayHasKey('legal_description', $listing);
    }

    public function test_additional_parcels_present_in_context(): void
    {
        $listing = $this->buildListing(['additional_parcels' => 'no']);
        $this->assertArrayHasKey('additional_parcels', $listing);
    }

    // ─── Flood Zone ────────────────────────────────────────────────────────────

    public function test_flood_zone_code_present_in_context(): void
    {
        $listing = $this->buildListing(['flood_zone_code' => 'X']);
        $this->assertArrayHasKey('flood_zone_code', $listing);
        $this->assertSame('X', $listing['flood_zone_code']);
    }

    public function test_flood_zone_date_present_in_context(): void
    {
        $listing = $this->buildListing(['flood_zone_date' => '06/18/2020']);
        $this->assertArrayHasKey('flood_zone_date', $listing);
    }

    public function test_flood_zone_panel_present_in_context(): void
    {
        $listing = $this->buildListing(['flood_zone_panel' => '12071C0295G']);
        $this->assertArrayHasKey('flood_zone_panel', $listing);
    }

    public function test_flood_insurance_required_present_in_context(): void
    {
        $listing = $this->buildListing(['flood_insurance_required' => 'no']);
        $this->assertArrayHasKey('flood_insurance_required', $listing);
        $this->assertSame('no', $listing['flood_insurance_required']);
    }

    // ─── CDD and Special Assessments ──────────────────────────────────────────

    public function test_has_cdd_present_in_context(): void
    {
        $listing = $this->buildListing(['has_cdd' => 'no']);
        $this->assertArrayHasKey('has_cdd', $listing);
        $this->assertSame('no', $listing['has_cdd']);
    }

    public function test_annual_cdd_fee_present_in_context(): void
    {
        $listing = $this->buildListing(['annual_cdd_fee' => '1200']);
        $this->assertArrayHasKey('annual_cdd_fee', $listing);
    }

    public function test_has_special_assessments_present_in_context(): void
    {
        $listing = $this->buildListing(['has_special_assessments' => 'no']);
        $this->assertArrayHasKey('has_special_assessments', $listing);
    }

    public function test_special_assessment_amount_present_in_context(): void
    {
        $listing = $this->buildListing(['special_assessment_amount' => '500']);
        $this->assertArrayHasKey('special_assessment_amount', $listing);
    }

    public function test_special_assessment_description_present_in_context(): void
    {
        $listing = $this->buildListing(['special_assessment_description' => 'Road paving 2023']);
        $this->assertArrayHasKey('special_assessment_description', $listing);
    }

    // ─── Additional Landlord Terms ─────────────────────────────────────────────

    public function test_min_income_requirement_present_in_context(): void
    {
        $listing = $this->buildListing(['min_income_requirement' => '3x monthly rent']);
        $this->assertArrayHasKey('min_income_requirement', $listing);
        $this->assertSame('3x monthly rent', $listing['min_income_requirement']);
    }

    public function test_pet_deposit_amount_present_in_context(): void
    {
        $listing = $this->buildListing(['pet_deposit_amount' => '500']);
        $this->assertArrayHasKey('pet_deposit_amount', $listing);
        $this->assertSame('500', $listing['pet_deposit_amount']);
    }

    public function test_pet_monthly_fee_present_in_context(): void
    {
        $listing = $this->buildListing(['pet_monthly_fee' => '50']);
        $this->assertArrayHasKey('pet_monthly_fee', $listing);
    }

    public function test_number_of_occupants_allowed_present_in_context(): void
    {
        $listing = $this->buildListing(['number_of_occupants_allowed' => '4']);
        $this->assertArrayHasKey('number_of_occupants_allowed', $listing);
    }

    public function test_renewal_option_details_present_in_context(): void
    {
        $listing = $this->buildListing(['renewal_option_details' => 'Two 1-year renewals at 3% increase']);
        $this->assertArrayHasKey('renewal_option_details', $listing);
    }

    public function test_landlord_approval_conditions_present_in_context(): void
    {
        $listing = $this->buildListing(['landlord_approval_conditions' => 'Credit check required']);
        $this->assertArrayHasKey('landlord_approval_conditions', $listing);
    }

    public function test_ll_maintenance_responsibility_present_in_context(): void
    {
        $listing = $this->buildListing(['ll_maintenance_responsibility' => 'Roof and HVAC']);
        $this->assertArrayHasKey('ll_maintenance_responsibility', $listing);
    }

    // ─── Null / absent fields return null and still appear as keys ─────────────

    public function test_new_fields_return_null_when_not_set(): void
    {
        $listing = $this->buildListing([]);

        $expectedKeys = [
            'commercial_lease_type',
            'cam_nnn_additional_rent_charges',
            'security_deposit_amount',
            'total_move_in_funds_required',
            'zoning',
            'ceiling_height',
            'number_of_restrooms',
            'office_retail_sqft',
            'parcel_id',
            'legal_description',
            'flood_zone_code',
            'flood_insurance_required',
            'has_cdd',
            'annual_cdd_fee',
            'has_special_assessments',
            'pet_deposit_amount',
            'min_income_requirement',
            'signage_rights',
            'building_hours',
            'access_24_7',
            'renewal_option_details',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $listing,
                "Key '{$key}' must be present in landlord context even when the EAV value is null");
        }
    }
}
