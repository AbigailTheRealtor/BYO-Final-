<?php

namespace Tests\Feature\AskAi;

use App\Models\AskAiFact;
use App\Models\AskAiKnowledgeSnapshot;
use App\Models\AskAiQuestion;
use App\Services\AskAi\AskAiFinalResponseBuilderService;
use App\Services\AskAi\AskAiFollowUpQuestionService;
use App\Services\AskAi\AskAiInternalRunnerService;
use App\Services\AskAi\AskAiKnowledgeSearchService;
use App\Services\AskAi\AskAiOpenAiAdapterService;
use App\Services\AskAi\AskAiQuestionClassifierService;
use App\Services\AskAi\AskAiRunnerV2Service;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * AskAiCoverageRemediationRoutingTest
 *
 * Regression tests for Task #2598 – Ask AI Coverage Remediation.
 *
 * Verifies:
 *   P1-1  annual_cdd_fee no longer collides with has_cdd
 *   P1-2  building_sqft no longer collides with square_feet
 *   P1-3  sewer_available accepts bare "sewer available"; listing.sewer still routes sewer-type questions
 *   P1-4  landlord_approval_conditions is now discoverable
 *   P0-1  All 17 Business Opportunity listing.* fields route correctly
 *   P2    ~20+ alias / synonym gaps are covered
 *   DB    database_hit is returned when a fact is stored and key is routed correctly
 */
class AskAiCoverageRemediationRoutingTest extends TestCase
{
    use DatabaseTransactions;

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build a runner with mocked collaborators so no real HTTP calls are made.
     * Only detectListingFieldKey() is exercised through reflection.
     */
    private function makeRunner(): AskAiRunnerV2Service
    {
        return new AskAiRunnerV2Service(
            $this->createMock(AskAiQuestionClassifierService::class),
            $this->createMock(AskAiInternalRunnerService::class),
            $this->createMock(AskAiOpenAiAdapterService::class),
            $this->createMock(AskAiFinalResponseBuilderService::class),
            $this->createMock(AskAiFollowUpQuestionService::class),
        );
    }

    /**
     * Invoke the private detectListingFieldKey() method via reflection.
     */
    private function detect(string $question): ?string
    {
        $runner = $this->makeRunner();
        $method = new \ReflectionMethod(AskAiRunnerV2Service::class, 'detectListingFieldKey');
        $method->setAccessible(true);
        return $method->invoke($runner, $question);
    }

    // =========================================================================
    // Search-service helpers (mirrors AskAiKnowledgeSearchServiceTest)
    // =========================================================================

    private function makeSnapshot(
        string $listingType = 'seller',
        int    $listingId   = 998001,
        int    $version     = 1
    ): AskAiKnowledgeSnapshot {
        return AskAiKnowledgeSnapshot::create([
            'listing_type'  => $listingType,
            'listing_id'    => $listingId,
            'version'       => $version,
            'status'        => 'ready',
            'snapshot_uuid' => (string) Str::uuid(),
            'built_at'      => now(),
        ]);
    }

    private function addFact(AskAiKnowledgeSnapshot $snap, string $bareKey, string $value): AskAiFact
    {
        return AskAiFact::create([
            'snapshot_id'    => $snap->id,
            'canonical_key'  => $bareKey,
            'value'          => $value,
            'visibility'     => 'public_allowed',
            'listing_type'   => $snap->listing_type,
            'listing_id'     => $snap->listing_id,
            'label'          => ucwords(str_replace('_', ' ', $bareKey)),
            'value_type'     => 'string',
            'source_path'    => 'context.listing.' . $bareKey,
            'classification' => 'public_factual',
            'public_allowed' => true,
            'restricted'     => false,
            'sort_order'     => 0,
        ]);
    }

    private function addQuestion(
        AskAiKnowledgeSnapshot $snap,
        string $canonicalKey,
        string $questionText,
        string $sampleQuestion2
    ): AskAiQuestion {
        return AskAiQuestion::create([
            'snapshot_id'     => $snap->id,
            'canonical_key'   => $canonicalKey,
            'field_type'      => 'listing_model',
            'question_text'   => $questionText,
            'sample_question' => $questionText,
            'sample_question_2' => $sampleQuestion2,
            'source_path'     => 'registry.listing_model.' . $canonicalKey,
            'sort_order'      => 0,
        ]);
    }

    // =========================================================================
    // P1-1 — CDD collision fix
    // =========================================================================

    /** @test */
    public function p1_1_bare_cdd_fee_routes_to_annual_cdd_fee_not_has_cdd(): void
    {
        $this->assertSame('listing.annual_cdd_fee', $this->detect('What is the annual CDD fee?'));
        $this->assertSame('listing.annual_cdd_fee', $this->detect('annual CDD fee'));
        $this->assertSame('listing.annual_cdd_fee', $this->detect('How much is the CDD fee?'));
        $this->assertSame('listing.annual_cdd_fee', $this->detect('CDD fee amount?'));
        $this->assertSame('listing.annual_cdd_fee', $this->detect('CDD assessment amount'));
    }

    /** @test */
    public function p1_1_has_cdd_still_routes_existence_questions(): void
    {
        $this->assertSame('listing.has_cdd', $this->detect('Is there a CDD?'));
        $this->assertSame('listing.has_cdd', $this->detect('Does this property have a CDD?'));
        $this->assertSame('listing.has_cdd', $this->detect('community development district'));
        $this->assertSame('listing.has_cdd', $this->detect('CDD status'));
    }

    // =========================================================================
    // P1-2 — Square-footage collision fix
    // =========================================================================

    /** @test */
    public function p1_2_building_sqft_routes_via_specific_phrase(): void
    {
        $this->assertSame('listing.building_sqft', $this->detect('Building square footage?'));
        $this->assertSame('listing.building_sqft', $this->detect('What is the building square footage?'));
        $this->assertSame('listing.building_sqft', $this->detect('total commercial building square footage'));
    }

    /** @test */
    public function p1_2_residential_size_questions_still_route_to_square_feet(): void
    {
        $this->assertSame('listing.square_feet', $this->detect('How big is the home?'));
        $this->assertSame('listing.square_feet', $this->detect('Home square footage?'));
        $this->assertSame('listing.square_feet', $this->detect('Living area square footage?'));
        $this->assertSame('listing.square_feet', $this->detect('How large is the property?'));
    }

    // =========================================================================
    // P1-3 — Sewer collision fix
    // =========================================================================

    /** @test */
    public function p1_3_bare_sewer_available_routes_to_sewer_available(): void
    {
        $this->assertSame('listing.sewer_available', $this->detect('Sewer available?'));
        $this->assertSame('listing.sewer_available', $this->detect('Is sewer available?'));
        $this->assertSame('listing.sewer_available', $this->detect('Sewer connection available?'));
        $this->assertSame('listing.sewer_available', $this->detect('Does this land have sewer?'));
        $this->assertSame('listing.sewer_available', $this->detect('Is sewer available on this land?'));
        $this->assertSame('listing.sewer_available', $this->detect('Is there sewer service to this lot?'));
    }

    /** @test */
    public function p1_3_sewer_type_questions_still_route_to_listing_sewer(): void
    {
        $this->assertSame('listing.sewer', $this->detect('What type of sewer does this property use?'));
        $this->assertSame('listing.sewer', $this->detect('sewer type'));
        $this->assertSame('listing.sewer', $this->detect('Is this on public sewer?'));
        $this->assertSame('listing.sewer', $this->detect('septic or sewer?'));
    }

    // =========================================================================
    // P1-4 — landlord_approval_conditions now discoverable
    // =========================================================================

    /** @test */
    public function p1_4_landlord_approval_conditions_routes_via_all_registered_phrases(): void
    {
        $this->assertSame('listing.landlord_approval_conditions', $this->detect('landlord approval conditions'));
        $this->assertSame('listing.landlord_approval_conditions', $this->detect('What are the approval requirements?'));
        $this->assertSame('listing.landlord_approval_conditions', $this->detect('What are the landlord approval requirements?'));
        $this->assertSame('listing.landlord_approval_conditions', $this->detect('tenant approval criteria'));
        $this->assertSame('listing.landlord_approval_conditions', $this->detect('Credit requirements to rent?'));
        $this->assertSame('listing.landlord_approval_conditions', $this->detect('What does the landlord require from tenants?'));
        $this->assertSame('listing.landlord_approval_conditions', $this->detect('qualifying conditions for this rental'));
    }

    // =========================================================================
    // P0-1 — 17 Business Opportunity listing.* fields
    // =========================================================================

    /** @test */
    public function p0_1_business_fields_route_correctly(): void
    {
        // annual_revenue
        $this->assertSame('listing.annual_revenue', $this->detect('annual revenue'));
        $this->assertSame('listing.annual_revenue', $this->detect('What is the annual revenue of this business?'));
        $this->assertSame('listing.annual_revenue', $this->detect('business annual revenue'));
        $this->assertSame('listing.annual_revenue', $this->detect('How much revenue does this business generate?'));

        // employee_count
        $this->assertSame('listing.employee_count', $this->detect('How many employees does this business have?'));
        $this->assertSame('listing.employee_count', $this->detect('employee count'));
        $this->assertSame('listing.employee_count', $this->detect('number of employees'));
        $this->assertSame('listing.employee_count', $this->detect('full-time employees in this business'));

        // year_established
        $this->assertSame('listing.year_established', $this->detect('year established'));
        $this->assertSame('listing.year_established', $this->detect('When was this business established?'));
        $this->assertSame('listing.year_established', $this->detect('How long has this business been operating?'));
        $this->assertSame('listing.year_established', $this->detect('business founding year'));
        $this->assertSame('listing.year_established', $this->detect('how old is this business'));

        // business_name
        $this->assertSame('listing.business_name', $this->detect('business name'));
        $this->assertSame('listing.business_name', $this->detect('What is the name of this business?'));
        $this->assertSame('listing.business_name', $this->detect('trading name of the business'));

        // business_location_leased
        $this->assertSame('listing.business_location_leased', $this->detect('Is the business location leased?'));
        $this->assertSame('listing.business_location_leased', $this->detect('business location lease status'));
        $this->assertSame('listing.business_location_leased', $this->detect('does the business own or lease the location'));

        // nda_required
        $this->assertSame('listing.nda_required', $this->detect('nda required'));
        $this->assertSame('listing.nda_required', $this->detect('Is an NDA required?'));
        $this->assertSame('listing.nda_required', $this->detect('non-disclosure agreement required'));
        $this->assertSame('listing.nda_required', $this->detect('confidentiality agreement required'));

        // financial_statements_available
        $this->assertSame('listing.financial_statements_available', $this->detect('financial statements available for this business'));
        $this->assertSame('listing.financial_statements_available', $this->detect('are financial statements available'));
        $this->assertSame('listing.financial_statements_available', $this->detect('financial records available'));
        $this->assertSame('listing.financial_statements_available', $this->detect('can i see the financial statements'));

        // reason_for_sale
        $this->assertSame('listing.reason_for_sale', $this->detect('reason for sale'));
        $this->assertSame('listing.reason_for_sale', $this->detect('why is this business for sale'));
        $this->assertSame('listing.reason_for_sale', $this->detect('reason for selling this business'));

        // sale_includes
        $this->assertSame('listing.sale_includes', $this->detect('what is included in the sale'));
        $this->assertSame('listing.sale_includes', $this->detect('sale includes'));
        $this->assertSame('listing.sale_includes', $this->detect('what does the sale include'));
        $this->assertSame('listing.sale_includes', $this->detect('what assets are included in this business sale'));

        // business_assets
        $this->assertSame('listing.business_assets', $this->detect('business assets'));
        $this->assertSame('listing.business_assets', $this->detect('what assets does the business have'));
        $this->assertSame('listing.business_assets', $this->detect('assets included with business'));

        // business_lease_monthly_rent
        $this->assertSame('listing.business_lease_monthly_rent', $this->detect('how much does the business pay in rent'));
        $this->assertSame('listing.business_lease_monthly_rent', $this->detect('business location rent'));
        $this->assertSame('listing.business_lease_monthly_rent', $this->detect('commercial space rent for this business'));
        $this->assertSame('listing.business_lease_monthly_rent', $this->detect('lease payment for the business location'));

        // ffe_value
        $this->assertSame('listing.ffe_value', $this->detect('ffe value'));
        $this->assertSame('listing.ffe_value', $this->detect('furniture fixtures and equipment value'));
        $this->assertSame('listing.ffe_value', $this->detect('what is the ffe worth'));

        // gross_profit
        $this->assertSame('listing.gross_profit', $this->detect('gross profit'));
        $this->assertSame('listing.gross_profit', $this->detect('business gross profit'));
        $this->assertSame('listing.gross_profit', $this->detect('gross profit margin of this business'));

        // sde_ebitda
        $this->assertSame('listing.sde_ebitda', $this->detect('sde'));
        $this->assertSame('listing.sde_ebitda', $this->detect('ebitda'));
        $this->assertSame('listing.sde_ebitda', $this->detect('seller discretionary earnings'));
        $this->assertSame('listing.sde_ebitda', $this->detect('what is the SDE'));
        $this->assertSame('listing.sde_ebitda', $this->detect('owner earnings'));
        $this->assertSame('listing.sde_ebitda', $this->detect('discretionary earnings'));

        // inventory_value
        $this->assertSame('listing.inventory_value', $this->detect('inventory value'));
        $this->assertSame('listing.inventory_value', $this->detect('value of the inventory'));
        $this->assertSame('listing.inventory_value', $this->detect('how much is the inventory worth'));
        $this->assertSame('listing.inventory_value', $this->detect('current inventory value'));

        // licenses
        $this->assertSame('listing.licenses', $this->detect('business licenses'));
        $this->assertSame('listing.licenses', $this->detect('licenses required for this business'));
        $this->assertSame('listing.licenses', $this->detect('permits and licenses'));

        // business_lease_assignable
        $this->assertSame('listing.business_lease_assignable', $this->detect('is the business lease assignable'));
        $this->assertSame('listing.business_lease_assignable', $this->detect('can the business lease be transferred'));
        $this->assertSame('listing.business_lease_assignable', $this->detect('assignable business lease'));
    }

    // =========================================================================
    // P2 — Alias / synonym gap coverage
    // =========================================================================

    /** @test */
    public function p2_year_built_aliases_route_correctly(): void
    {
        $this->assertSame('listing.year_built', $this->detect('year built'));
        $this->assertSame('listing.year_built', $this->detect('How old is this property?'));
        $this->assertSame('listing.year_built', $this->detect('how old is this building'));
        $this->assertSame('listing.year_built', $this->detect('age of this building'));
        $this->assertSame('listing.year_built', $this->detect('when was this home built'));
    }

    /** @test */
    public function p2_flood_zone_code_bare_and_alias_forms_route_correctly(): void
    {
        // Bare form — must resolve to flood_zone_code (flood_zone_panel/date are ordered first
        // in the map so their specific phrases win; bare 'flood zone' falls through to code)
        $this->assertSame('listing.flood_zone_code', $this->detect('flood zone'));
        $this->assertSame('listing.flood_zone_code', $this->detect('flood zone?'));
        $this->assertSame('listing.flood_zone_code', $this->detect('flood zone code'));
        $this->assertSame('listing.flood_zone_code', $this->detect('flood zone status'));
        $this->assertSame('listing.flood_zone_code', $this->detect('Is this in a flood zone?'));
        $this->assertSame('listing.flood_zone_code', $this->detect('fema flood zone'));
        $this->assertSame('listing.flood_zone_code', $this->detect('flood zone designation'));
    }

    /** @test */
    public function p2_flood_zone_panel_and_date_win_over_bare_flood_zone(): void
    {
        // Specific panel/date questions must NOT be intercepted by flood_zone_code's bare 'flood zone'
        $this->assertSame('listing.flood_zone_panel', $this->detect('flood zone panel'));
        $this->assertSame('listing.flood_zone_panel', $this->detect('fema map panel number'));
        $this->assertSame('listing.flood_zone_panel', $this->detect('what is the flood zone panel'));
        $this->assertSame('listing.flood_zone_panel', $this->detect('fema flood map panel'));
        $this->assertSame('listing.flood_zone_date', $this->detect('flood zone date'));
        $this->assertSame('listing.flood_zone_date', $this->detect('when was the flood zone last updated'));
        $this->assertSame('listing.flood_zone_date', $this->detect('fema map date'));
        $this->assertSame('listing.flood_zone_date', $this->detect('flood map date'));
    }

    /** @test */
    public function p2_rental_restrictions_bare_aliases_route_correctly(): void
    {
        $this->assertSame('listing.rental_restrictions', $this->detect('rental restrictions'));
        $this->assertSame('listing.rental_restrictions', $this->detect('are there any rental restrictions'));
        $this->assertSame('listing.rental_restrictions', $this->detect('are there restrictions on renting'));
        $this->assertSame('listing.rental_restrictions', $this->detect('rental restrictions on this property'));
    }

    /** @test */
    public function p2_max_price_aliases_route_correctly(): void
    {
        $this->assertSame('listing.max_price', $this->detect('buyer maximum budget'));
        $this->assertSame('listing.max_price', $this->detect('maximum budget'));
        $this->assertSame('listing.max_price', $this->detect('how much can they spend'));
        $this->assertSame('listing.max_price', $this->detect('what is their max price'));
        $this->assertSame('listing.max_price', $this->detect('buyer maximum price'));
    }

    /** @test */
    public function p2_hoa_acceptable_aliases_route_correctly(): void
    {
        $this->assertSame('listing.hoa_acceptable', $this->detect('is the buyer open to hoa'));
        $this->assertSame('listing.hoa_acceptable', $this->detect('buyer open to hoa properties'));
        $this->assertSame('listing.hoa_acceptable', $this->detect('is the buyer okay with hoa'));
        $this->assertSame('listing.hoa_acceptable', $this->detect('buyer hoa preference'));
    }

    /** @test */
    public function p2_max_rent_aliases_route_correctly(): void
    {
        $this->assertSame('listing.max_rent', $this->detect('how much can the tenant afford'));
        $this->assertSame('listing.max_rent', $this->detect('how much can the tenant pay'));
        $this->assertSame('listing.max_rent', $this->detect('tenant max rent'));
        $this->assertSame('listing.max_rent', $this->detect('maximum rent budget'));
    }

    /** @test */
    public function p2_desired_lease_length_aliases_route_correctly(): void
    {
        $this->assertSame('listing.desired_lease_length', $this->detect('desired lease length'));
        $this->assertSame('listing.desired_lease_length', $this->detect('how long of a lease does this tenant want'));
        $this->assertSame('listing.desired_lease_length', $this->detect('how long a lease does the tenant want'));
        $this->assertSame('listing.desired_lease_length', $this->detect('tenant preferred lease duration'));
    }

    /** @test */
    public function p2_smoking_policy_bare_aliases_route_correctly(): void
    {
        $this->assertSame('listing.smoking_policy', $this->detect('smoking policy'));
        $this->assertSame('listing.smoking_policy', $this->detect('is smoking allowed'));
        $this->assertSame('listing.smoking_policy', $this->detect('smoking policy for this rental unit'));
        $this->assertSame('listing.smoking_policy', $this->detect('does this unit allow smoking'));
    }

    /** @test */
    public function p2_road_frontage_bare_aliases_route_correctly(): void
    {
        $this->assertSame('listing.road_frontage', $this->detect('road frontage'));
        $this->assertSame('listing.road_frontage', $this->detect('what is the road frontage'));
        $this->assertSame('listing.road_frontage', $this->detect('road frontage type'));
        $this->assertSame('listing.road_frontage', $this->detect('does this lot have road frontage'));
    }

    /** @test */
    public function p2_vegetation_bare_aliases_route_correctly(): void
    {
        $this->assertSame('listing.vegetation', $this->detect('vegetation'));
        $this->assertSame('listing.vegetation', $this->detect('vegetation on the land'));
        $this->assertSame('listing.vegetation', $this->detect('what vegetation is on the land'));
    }

    /** @test */
    public function p2_buildable_aliases_route_correctly(): void
    {
        $this->assertSame('listing.buildable', $this->detect('is this buildable'));
        $this->assertSame('listing.buildable', $this->detect('is this property buildable'));
        $this->assertSame('listing.buildable', $this->detect('can i build on this property'));
        $this->assertSame('listing.buildable', $this->detect('is this land buildable'));
        $this->assertSame('listing.buildable', $this->detect('can you build on this lot'));
    }

    /** @test */
    public function p2_water_available_bare_aliases_route_correctly(): void
    {
        $this->assertSame('listing.water_available', $this->detect('water available'));
        $this->assertSame('listing.water_available', $this->detect('is water available'));
        $this->assertSame('listing.water_available', $this->detect('is water available on this land'));
        $this->assertSame('listing.water_available', $this->detect('water to the site'));
    }

    /** @test */
    public function p2_easements_bare_aliases_route_correctly(): void
    {
        $this->assertSame('listing.easements', $this->detect('easements'));
        $this->assertSame('listing.easements', $this->detect('easements on the property'));
        $this->assertSame('listing.easements', $this->detect('are there any easements'));
        $this->assertSame('listing.easements', $this->detect('does this land have any easements'));
    }

    /** @test */
    public function p2_telecom_available_bare_alias_routes_correctly(): void
    {
        $this->assertSame('listing.telecom_available', $this->detect('telecom available'));
        $this->assertSame('listing.telecom_available', $this->detect('telecom availability on this land'));
        $this->assertSame('listing.telecom_available', $this->detect('is internet available on this land'));
    }

    /** @test */
    public function p2_lease_length_minimum_aliases_route_correctly(): void
    {
        $this->assertSame('listing.lease_length', $this->detect('minimum lease term'));
        $this->assertSame('listing.lease_length', $this->detect('shortest lease available'));
        $this->assertSame('listing.lease_length', $this->detect('how long is the minimum lease'));
        $this->assertSame('listing.lease_length', $this->detect('what lease lengths are available'));
    }

    /** @test */
    public function p2_cap_rate_investment_yield_aliases_route_correctly(): void
    {
        $this->assertSame('listing.cap_rate', $this->detect('cap rate'));
        $this->assertSame('listing.cap_rate', $this->detect('what return does this investment yield'));
        $this->assertSame('listing.cap_rate', $this->detect('investment yield rate'));
        $this->assertSame('listing.cap_rate', $this->detect('property investment return'));
        $this->assertSame('listing.cap_rate', $this->detect('capitalization rate'));
    }

    /** @test */
    public function p2_total_units_multi_unit_aliases_route_correctly(): void
    {
        $this->assertSame('listing.total_units', $this->detect('multiple units'));
        $this->assertSame('listing.total_units', $this->detect('multi-unit property'));
        $this->assertSame('listing.total_units', $this->detect('is this multi-unit'));
        $this->assertSame('listing.total_units', $this->detect('how many rental units'));
        $this->assertSame('listing.total_units', $this->detect('separate living units'));
        $this->assertSame('listing.total_units', $this->detect('number of units in this building'));
        $this->assertSame('listing.total_units', $this->detect('how many units'));
    }

    /** @test */
    public function p2_gross_annual_income_revenue_aliases_route_correctly(): void
    {
        $this->assertSame('listing.gross_annual_income', $this->detect('gross annual income'));
        $this->assertSame('listing.gross_annual_income', $this->detect('how much revenue does this property generate'));
        $this->assertSame('listing.gross_annual_income', $this->detect('annual gross income'));
        $this->assertSame('listing.gross_annual_income', $this->detect('total revenue this property generates'));
    }

    /** @test */
    public function p2_rent_roll_available_bare_aliases_route_correctly(): void
    {
        $this->assertSame('listing.rent_roll_available', $this->detect('rent roll'));
        $this->assertSame('listing.rent_roll_available', $this->detect('rent roll available'));
        $this->assertSame('listing.rent_roll_available', $this->detect('can i see the rent roll'));
        $this->assertSame('listing.rent_roll_available', $this->detect('is a rent roll available'));
    }

    /** @test */
    public function p2_operating_statement_financial_aliases_route_correctly(): void
    {
        $this->assertSame('listing.operating_statement_available', $this->detect('operating statement available'));
        $this->assertSame('listing.operating_statement_available', $this->detect('do you have an operating statement'));
        $this->assertSame('listing.operating_statement_available', $this->detect('income statement available'));
        $this->assertSame('listing.operating_statement_available', $this->detect('income and expense statement'));
    }

    /** @test */
    public function p2_unit_mix_summary_breakdown_aliases_route_correctly(): void
    {
        $this->assertSame('listing.unit_mix_summary', $this->detect('unit mix'));
        $this->assertSame('listing.unit_mix_summary', $this->detect('what is the unit type breakdown'));
        $this->assertSame('listing.unit_mix_summary', $this->detect('unit type breakdown'));
        $this->assertSame('listing.unit_mix_summary', $this->detect('bedroom and bath mix'));
    }

    /** @test */
    public function p2_hoa_association_aliases_route_correctly(): void
    {
        $this->assertSame('listing.hoa_association', $this->detect('does this property have an association'));
        $this->assertSame('listing.hoa_association', $this->detect('hoa or association'));
        $this->assertSame('listing.hoa_association', $this->detect('does this property have an hoa'));
        $this->assertSame('listing.hoa_association', $this->detect('homeowners association details'));
    }

    /** @test */
    public function p2_number_of_occupants_allowed_bare_aliases_route_correctly(): void
    {
        $this->assertSame('listing.number_of_occupants_allowed', $this->detect('number of occupants'));
        $this->assertSame('listing.number_of_occupants_allowed', $this->detect('occupant limit'));
        $this->assertSame('listing.number_of_occupants_allowed', $this->detect('maximum number of occupants'));
        $this->assertSame('listing.number_of_occupants_allowed', $this->detect('occupancy limit'));
    }

    // =========================================================================
    // Database-hit integration — confirms routing + search-service return correct value
    // =========================================================================

    /** @test */
    public function database_hit_annual_cdd_fee_via_canonical_key(): void
    {
        $snap = $this->makeSnapshot('seller', 998001);
        $this->addFact($snap, 'annual_cdd_fee', '$1,200');
        $this->addQuestion($snap, 'listing.annual_cdd_fee',
            'What is the annual CDD fee for this property?',
            'How much is the CDD fee per year?'
        );

        $service = new AskAiKnowledgeSearchService();
        $result  = $service->search('seller', 998001, 'annual CDD fee', [
            'normalized_field_key' => 'listing.annual_cdd_fee',
        ]);

        $this->assertSame('database_hit', $result['outcome']);
        $this->assertSame('$1,200', $result['answer']);
        $this->assertSame('canonical_field', $result['source']['match_type']);
    }

    /** @test */
    public function database_hit_landlord_approval_conditions_via_canonical_key(): void
    {
        $snap = $this->makeSnapshot('landlord', 998002);
        $this->addFact($snap, 'landlord_approval_conditions', 'Credit score 650+, income 3x rent, no prior evictions');
        $this->addQuestion($snap, 'listing.landlord_approval_conditions',
            'What are the approval conditions for this rental?',
            'What credit or income requirements must tenants meet?'
        );

        $service = new AskAiKnowledgeSearchService();
        $result  = $service->search('landlord', 998002, 'approval requirements', [
            'normalized_field_key' => 'listing.landlord_approval_conditions',
        ]);

        $this->assertSame('database_hit', $result['outcome']);
        $this->assertSame('Credit score 650+, income 3x rent, no prior evictions', $result['answer']);
    }

    /** @test */
    public function database_hit_annual_revenue_business_field_via_canonical_key(): void
    {
        $snap = $this->makeSnapshot('seller', 998003);
        $this->addFact($snap, 'annual_revenue', '$2,500,000');
        $this->addQuestion($snap, 'listing.annual_revenue',
            'What is the annual revenue of this business?',
            'How much revenue did this business generate last year?'
        );

        $service = new AskAiKnowledgeSearchService();
        $result  = $service->search('seller', 998003, 'annual revenue', [
            'normalized_field_key' => 'listing.annual_revenue',
        ]);

        $this->assertSame('database_hit', $result['outcome']);
        $this->assertSame('$2,500,000', $result['answer']);
    }

    /** @test */
    public function database_hit_sde_ebitda_via_canonical_key(): void
    {
        $snap = $this->makeSnapshot('seller', 998004);
        $this->addFact($snap, 'sde_ebitda', '$380,000');
        $this->addQuestion($snap, 'listing.sde_ebitda',
            "What are the seller's discretionary earnings (SDE) for this business?",
            'What is the EBITDA for this business?'
        );

        $service = new AskAiKnowledgeSearchService();
        $result  = $service->search('seller', 998004, 'What is the SDE?', [
            'normalized_field_key' => 'listing.sde_ebitda',
        ]);

        $this->assertSame('database_hit', $result['outcome']);
        $this->assertSame('$380,000', $result['answer']);
    }

    /** @test */
    public function database_hit_sewer_available_via_canonical_key(): void
    {
        $snap = $this->makeSnapshot('seller', 998005);
        $this->addFact($snap, 'sewer_available', 'Yes – municipal sewer at the lot line');
        $this->addQuestion($snap, 'listing.sewer_available',
            'Is sewer available on this land?',
            'Is there sewer service to this lot?'
        );

        $service = new AskAiKnowledgeSearchService();
        $result  = $service->search('seller', 998005, 'sewer available', [
            'normalized_field_key' => 'listing.sewer_available',
        ]);

        $this->assertSame('database_hit', $result['outcome']);
        $this->assertSame('Yes – municipal sewer at the lot line', $result['answer']);
    }

    /** @test */
    public function database_hit_total_units_via_canonical_key(): void
    {
        $snap = $this->makeSnapshot('seller', 998006);
        $this->addFact($snap, 'total_units', '12');
        $this->addQuestion($snap, 'listing.total_units',
            'How many units does this property have?',
            'Is this a multi-unit property, and if so, how many units?'
        );

        $service = new AskAiKnowledgeSearchService();
        $result  = $service->search('seller', 998006, 'multi-unit property', [
            'normalized_field_key' => 'listing.total_units',
        ]);

        $this->assertSame('database_hit', $result['outcome']);
        $this->assertSame('12', $result['answer']);
    }
}
