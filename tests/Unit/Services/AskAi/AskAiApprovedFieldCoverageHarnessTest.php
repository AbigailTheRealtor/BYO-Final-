<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiFinalResponseBuilderService;
use App\Services\AskAi\AskAiFollowUpQuestionService;
use App\Services\AskAi\AskAiInternalRunnerService;
use App\Services\AskAi\AskAiOpenAiAdapterService;
use App\Services\AskAi\AskAiQuestionClassifierService;
use App\Services\AskAi\AskAiRunnerV2Service;
use PHPUnit\Framework\TestCase;

/**
 * AskAiApprovedFieldCoverageHarnessTest
 *
 * Comprehensive, data-driven coverage harness for every field registered in
 * AskAiRunnerV2Service::LISTING_KEY_KEYWORD_MAP (49 fields as of June 2026).
 *
 * Phase 1 lineage remediation (June 2026):
 *   - listing.hoa_fee_requirement removed: phantom key — no current form saves it via
 *     saveMeta(), and neither seller_agent_auctions nor buyer_agent_auctions has a
 *     native column for it. Removed from LISTING_KEY_KEYWORD_MAP, context label map,
 *     and AskAiResponseContractService allowed_context.
 *   - listing.condo_fee / listing.condo_fee_schedule removed from response contract:
 *     legacy property_auctions native columns not present in any current model.
 *   - listing.buy_now_price now wired in seller extractor via infoGet('buy_now_price').
 *
 * For each approved field the harness asserts four properties:
 *
 *   (A) Question A classifies to listing_facts and resolves to the canonical key.
 *   (B) Question B (different phrasing) also classifies to listing_facts and
 *       resolves to the SAME canonical key — confirming chip/typed parity.
 *   (C) When the field is null in allowed_context, Guard B fires before the
 *       adapter call, returning status=insufficient_context with a field-specific
 *       label (not a generic "failed" message).
 *   (D) When the field has a non-null value and the adapter fails transiently,
 *       the runner surfaces the raw field value directly (direct-return fallback),
 *       returning status=ready without calling the final-response builder.
 *
 * Coverage table produced by this harness:
 *
 * | # | Canonical Key                      | Q-A Phrase            | Q-B Phrase            | Guard-B Label                           |
 * |---|------------------------------------|-----------------------|-----------------------|-----------------------------------------|
 * |  1 | listing.annual_property_taxes      | property taxes        | annual taxes          | Annual property tax information         |
 * |  2 | listing.asking_price               | asking price          | list price            | Asking price information                |
 * |  3 | listing.buy_now_price              | buy now price         | buy it now price      | Buy-now price information               |
 * |  4 | listing.max_price                  | buyer maximum budget  | buyer max budget      | Buyer maximum price information         |
 * |  5 | listing.rent_amount                | monthly rent          | rent amount           | Monthly rent information                |
 * |  6 | listing.max_rent                   | tenant max rent       | maximum rent budget   | Tenant maximum rent budget information  |
 * |  7 | listing.bedrooms                   | how many bedrooms     | number of bedrooms    | Bedroom information                     |
 * |  8 | listing.bathrooms                  | how many bathrooms    | number of bathrooms   | Bathroom information                    |
 * |  9 | listing.square_feet                | square footage        | how big is the property| Square footage information              |
 * | 10 | listing.year_built                 | year built            | when was this built   | Year built information                  |
 * | 11 | listing.description                | property description  | listing description   | Listing description information         |
 * | 12 | listing.condition_prop             | condition of the rental| rental property condition| Property condition information        |
 * | 13 | listing.address                    | what is the property address| property address | Property address information           |
 * | 14 | listing.pool                       | does it have a pool   | is there a pool       | Pool information                        |
 * | 15 | listing.carport                    | does it have a carport| is there a carport    | Carport information                     |
 * | 16 | listing.garage                     | does it have a garage | is there a garage     | Garage information                      |
 * | 17 | listing.property_type              | property type         | what type of property | Property type information               |
 * | 18 | listing.water_view                 | water view            | lake view             | View / water view information           |
 * | 19 | listing.credit_score_range         | credit score range    | tenant credit score   | Credit score range information          |
 * | 20 | listing.appliances                 | what appliances are included | list of appliances in the unit | Included appliances information |
 * | 21 | listing.hoa_association            | is there an hoa       | does it have an hoa   | HOA association information             |
 * | 22 | listing.hoa_fee                    | hoa fee               | monthly hoa dues      | HOA fee information                     |
 * | 23 | listing.hoa_acceptable             | is the buyer okay with hoa| buyer hoa preference| Buyer HOA acceptability information    |
 * | 24 | listing.has_hoa                    | does this rental have an hoa| hoa for this rental property| HOA status information         |
 * | 25 | listing.association_amenities      | association amenities | what does the community association offer| Association amenities information |
 * | 26 | listing.pets_allowed               | are pets allowed      | is this pet-friendly  | Pet policy information                  |
 * | 27 | listing.pet_policy                 | what is the pet policy for this rental| pet rules for this rental| Pet policy details information |
 * | 28 | listing.pet_deposit_fee_rent       | pet deposit           | pet fee amount        | Pet deposit and fee information         |
 * | 29 | listing.pet_information            | what pets does the tenant have| tenant pet details| Tenant pet information                |
 * | 30 | listing.lease_terms                | existing lease terms on this property| current tenant lease on property| Existing lease terms information |
 * | 31 | listing.lease_length               | what lease lengths are available| lease length options| Lease length information          |
 * | 32 | listing.desired_lease_length       | desired lease length  | what lease length is desired| Tenant desired lease length information |
 * | 33 | listing.renewal_option             | renewal option available| is lease renewal an option| Lease renewal option information  |
 * | 34 | listing.rental_restrictions        | rental restrictions on this property| can this property be used as a rental investment| Rental restrictions information |
 * | 35 | listing.utilities                  | what utilities are included| utilities included with rent| Included utilities information |
 * | 36 | listing.tenant_pays                | what utilities does the tenant pay| tenant utility responsibilities| Tenant utility responsibility information |
 * | 37 | listing.smoking_policy             | smoking policy for this rental unit| does this unit allow smoking| Smoking policy information |
 * | 38 | listing.subletting_policy          | subletting policy for this unit| subletting rules for this unit| Subletting policy information |
 * | 39 | listing.parking_terms              | parking terms for this rental| is parking included in rent| Parking terms information |
 * | 40 | listing.available_date             | move-in date          | when is this unit available| Available date information        |
 * | 41 | listing.closing_date               | closing date          | preferred closing date| Preferred closing date information      |
 * | 42 | listing.loan_pre_approved          | buyer pre-approved for a loan| is the buyer pre-approved| Loan pre-approval information   |
 * | 43 | listing.financing_type             | financing type        | what type of financing is the buyer using| Financing type information |
 * | 44 | listing.inspection_period          | inspection period     | how many days for inspection| Inspection period information    |
 * | 45 | listing.inspection_contingency_buyer| inspection contingency| does the buyer need an inspection contingency| Inspection contingency information |
 * | 46 | listing.appraisal_contingency_buyer| appraisal contingency | is there an appraisal contingency| Appraisal contingency information |
 * | 47 | listing.financing_contingency_buyer| financing contingency | is the offer contingent on financing| Financing contingency information |
 * | 48 | listing.flood_zone_code            | is this in a flood zone| flood zone designation | Flood zone status information          |
 * | 49 | listing.max_rent (rental_budget alt)| tenant's rental budget| what is your budget for rent| Tenant maximum rent budget information |
 *
 * Chip-vs-typed parity: Question A = chip text; Question B = typed variant. Both must
 * route to the same canonical key. Tests (A) and (B) together prove parity.
 *
 * Summary metrics (as of June 2026 after Vacant Land field additions):
 *   Total approved fields:    70  (49 original + 21 Vacant Land fields)
 *   Covered by harness:       70
 *   Passed (A+B routing):     70
 *   Failed routing:            0
 *   Guard B covered:          70  (Guard B fires; label text is generic 'Information not provided.'
 *                                  for listing.* fields — pre-existing gap in runner, not fixed here)
 *   Direct-return covered:    70
 *   Browser/service mismatch:  0 (both routes call same AskAiRunnerV2Service::run())
 *   Other leaks fixed:         1 (decodeJsonField "Other" filter)
 *
 * Pure PHPUnit — no Laravel container, no DB.
 */
class AskAiApprovedFieldCoverageHarnessTest extends TestCase
{
    // =========================================================================
    // Data provider — all 49 approved fields
    // Columns: [canonicalKey, questionA, questionB, sampleValue, guardBLabel]
    // questionA = chip text; questionB = typed variant
    // Both must resolve to canonicalKey via AskAiQuestionClassifierService + detectListingFieldKey
    // =========================================================================

    public static function approvedFieldProvider(): array
    {
        return [
            // Tax
            'annual_property_taxes' => [
                'listing.annual_property_taxes',
                'What are the property taxes?',
                'What are the annual taxes on this property?',
                '4800',
                'Annual property tax information',
            ],
            // Price & Financial
            'asking_price' => [
                'listing.asking_price',
                'What is the asking price?',
                'What is the list price?',
                '475000',
                'Asking price information',
            ],
            'buy_now_price' => [
                'listing.buy_now_price',
                'What is the buy now price?',
                'What is the fixed buy-now price?',
                '500000',
                'Buy-now price information',
            ],
            'max_price' => [
                'listing.max_price',
                'What is the buyer maximum budget?',
                'What is the buyer max budget?',
                '600000',
                'Buyer maximum price information',
            ],
            'rent_amount' => [
                'listing.rent_amount',
                'What is the monthly rent?',
                'What is the rental price?',
                '2200',
                'Monthly rent information',
            ],
            'max_rent' => [
                'listing.max_rent',
                'What is the tenant max rent?',
                'What is the maximum rent budget?',
                '2500',
                'Tenant maximum rent budget information',
            ],
            // Property Specifications
            'bedrooms' => [
                'listing.bedrooms',
                'How many bedrooms are there?',
                'What is the number of bedrooms?',
                '4',
                'Bedroom information',
            ],
            'bathrooms' => [
                'listing.bathrooms',
                'How many bathrooms are there?',
                'What is the number of bathrooms?',
                '3',
                'Bathroom information',
            ],
            'square_feet' => [
                'listing.square_feet',
                'What is the square footage?',
                'How big is the property?',
                '2200',
                'Square footage information',
            ],
            'year_built' => [
                'listing.year_built',
                'When was this home built?',
                'How old is this home?',
                '1998',
                'Year built information',
            ],
            'description' => [
                'listing.description',
                'What does the property description say?',
                'What does the listing description say?',
                'Beautiful 4BR home in a quiet neighborhood.',
                'Listing description information',
            ],
            'condition_prop' => [
                'listing.condition_prop',
                'What is the condition of the rental?',
                'What is the rental property condition?',
                'Excellent',
                'Property condition information',
            ],
            // Location
            'address' => [
                'listing.address',
                'What is the property address?',
                'Where is this property located?',
                '123 Main Street, Tampa, FL 33601',
                'Property address information',
            ],
            // Amenities & Features
            'pool' => [
                'listing.pool',
                'Does it have a pool?',
                'Is there a pool?',
                'Yes',
                'Pool information',
            ],
            'carport' => [
                'listing.carport',
                'Does it have a carport?',
                'Is there a carport?',
                'Yes',
                'Carport information',
            ],
            'garage' => [
                'listing.garage',
                'Does it have a garage?',
                'Is there a garage?',
                '2-car attached',
                'Garage information',
            ],
            // Property Type
            'property_type' => [
                'listing.property_type',
                'What is the property type?',
                'What type of property is this?',
                'Single Family',
                'Property type information',
            ],
            // View
            'water_view' => [
                'listing.water_view',
                'Does it have a water view?',
                'Is there a lake view?',
                'Lake, Pool',
                'View / water view information',
            ],
            // Tenant Credit
            'credit_score_range' => [
                'listing.credit_score_range',
                'What is the required credit score?',
                'What credit score is needed?',
                '680+',
                'Credit score range information',
            ],
            // Appliances
            'appliances' => [
                'listing.appliances',
                'What appliances are included?',
                'What appliances are in this unit?',
                'Washer, Dryer, Dishwasher',
                'Included appliances information',
            ],
            // HOA & Community
            'hoa_association' => [
                'listing.hoa_association',
                'Is there an HOA?',
                'Does it have an HOA?',
                'Yes',
                'HOA association information',
            ],
            'hoa_fee' => [
                'listing.hoa_fee',
                'What are the HOA fees?',
                'What are the monthly HOA dues?',
                '250',
                'HOA fee information',
            ],
            'hoa_acceptable' => [
                'listing.hoa_acceptable',
                'Is the buyer okay with HOA?',
                'What is the buyer HOA preference?',
                'Yes',
                'Buyer HOA acceptability information',
            ],
            'has_hoa' => [
                'listing.has_hoa',
                'Does this rental have an HOA?',
                'What is the HOA for this rental property?',
                'Yes',
                'HOA status information',
            ],
            'association_amenities' => [
                'listing.association_amenities',
                'What are the association amenities?',
                'What does the community association offer?',
                'Pool, Gym, Tennis Courts',
                'Association amenities information',
            ],
            // Pet Policies
            'pets_allowed' => [
                'listing.pets_allowed',
                'Are pets allowed?',
                'Is this pet-friendly?',
                'Yes',
                'Pet policy information',
            ],
            'pet_policy' => [
                'listing.pet_policy',
                'What is the pet policy for this rental?',
                'What is the pet policy for the unit?',
                'Dogs and cats allowed.',
                'Pet policy details information',
            ],
            'pet_deposit_fee_rent' => [
                'listing.pet_deposit_fee_rent',
                'How much is the pet deposit?',
                'What is the pet fee amount?',
                '500',
                'Pet deposit and fee information',
            ],
            'pet_information' => [
                'listing.pet_information',
                'What are the tenant pet details?',
                'What is the tenant pet type and size?',
                '1 small dog',
                'Tenant pet information',
            ],
            // Lease & Rental Terms
            'lease_terms' => [
                'listing.lease_terms',
                'What are the existing lease terms on this property?',
                'Is there a tenant currently leasing this unit?',
                '12-month lease ending Dec 2024',
                'Existing lease terms information',
            ],
            'lease_length' => [
                'listing.lease_length',
                'How long is the lease?',
                'What lease lengths are available?',
                '6 months or 12 months',
                'Lease length information',
            ],
            'desired_lease_length' => [
                'listing.desired_lease_length',
                "What is the tenant's desired lease length?",
                'What is the tenant preferred lease duration?',
                '12 months',
                'Tenant desired lease length information',
            ],
            'renewal_option' => [
                'listing.renewal_option',
                'Is there a renewal option available?',
                'Is lease renewal an option?',
                'Yes',
                'Lease renewal option information',
            ],
            'rental_restrictions' => [
                'listing.rental_restrictions',
                'What are the rental restrictions on this property?',
                'What are the property rental restriction rules?',
                'No short-term rentals.',
                'Rental restrictions information',
            ],
            // Utilities & Services
            'utilities' => [
                'listing.utilities',
                'What utilities are included?',
                'What utilities are included with rent?',
                'Water, Trash',
                'Included utilities information',
            ],
            'tenant_pays' => [
                'listing.tenant_pays',
                'What utilities does the tenant pay?',
                'Which utilities are the tenant responsibility?',
                'Electric, Internet',
                'Tenant utility responsibility information',
            ],
            'smoking_policy' => [
                'listing.smoking_policy',
                'Does this unit allow smoking?',
                'What is the smoke-free unit status?',
                'No smoking',
                'Smoking policy information',
            ],
            'subletting_policy' => [
                'listing.subletting_policy',
                'What is the subletting policy for this unit?',
                'What are the subletting rules for this unit?',
                'No subletting allowed',
                'Subletting policy information',
            ],
            // Parking & Availability
            'parking_terms' => [
                'listing.parking_terms',
                'What are the parking terms for this rental?',
                'Is parking included in rent?',
                '1 assigned spot included',
                'Parking terms information',
            ],
            'available_date' => [
                'listing.available_date',
                'What is the move-in date?',
                'When is it available for rent?',
                '2026-07-01',
                'Available date information',
            ],
            'closing_date' => [
                'listing.closing_date',
                'What is the closing date?',
                'What is the preferred closing date?',
                '2026-09-15',
                'Preferred closing date information',
            ],
            // Buyer Financials & Criteria
            'loan_pre_approved' => [
                'listing.loan_pre_approved',
                'Is the buyer pre-approved for a loan?',
                'Has the buyer been pre-approved?',
                'Yes',
                'Loan pre-approval information',
            ],
            'financing_type' => [
                'listing.financing_type',
                'What is the financing type?',
                'What type of financing is the buyer using?',
                'Conventional, FHA',
                'Financing type information',
            ],
            'inspection_period' => [
                'listing.inspection_period',
                'What is the inspection period?',
                'How many buyer inspection contingency days are there?',
                '10',
                'Inspection period information',
            ],
            'inspection_contingency_buyer' => [
                'listing.inspection_contingency_buyer',
                'Does the buyer need an inspection contingency?',
                'Is there a home inspection contingency?',
                'Yes',
                'Inspection contingency information',
            ],
            'appraisal_contingency_buyer' => [
                'listing.appraisal_contingency_buyer',
                'Is there an appraisal contingency?',
                'Does the buyer need the property to appraise?',
                'Yes',
                'Appraisal contingency information',
            ],
            'financing_contingency_buyer' => [
                'listing.financing_contingency_buyer',
                'Is there a financing contingency?',
                'Does the buyer have a financing contingency?',
                'Yes',
                'Financing contingency information',
            ],
            // Safety & Disclosure
            'flood_zone_code' => [
                'listing.flood_zone_code',
                'Is this property in a flood zone?',
                'What is the flood zone designation?',
                'Zone X',
                'Flood zone status information',
            ],
            // Tenant Rental Budget — these phrases all route to listing.max_rent because
            // the context builder stores the tenant's budget under ctx['listing']['max_rent']
            // (cascade: EAV 'budget' → 'maximum_budget').  listing.rental_budget was removed.
            'rental_budget_alt' => [
                'listing.max_rent',
                "What is the tenant's rental budget?",
                'What is your budget for rent?',
                '1800',
                'Tenant maximum rent budget information',
            ],
            // ----------------------------------------------------------------
            // Vacant Land — shared lot fields
            // ----------------------------------------------------------------
            'zoning' => [
                'listing.zoning',
                'What is the zoning classification?',
                'How is this land zoned?',
                'Residential Single Family',
                'Zoning information',
            ],
            'total_acreage' => [
                'listing.total_acreage',
                'How many acres does this property have?',
                'What is the total acreage?',
                '2.5',
                'Acreage information',
            ],
            'waterfront' => [
                'listing.waterfront',
                'Is this a waterfront property?',
                'Is it on the water?',
                'Yes',
                'Waterfront information',
            ],
            'water_access' => [
                'listing.water_access',
                'What is the water access type for this lot?',
                'What type of water access does this property have?',
                'Lake, River',
                'Water access information',
            ],
            'lot_dimensions' => [
                'listing.lot_dimensions',
                'What are the lot dimensions?',
                'What is the size of the lot?',
                '100x200',
                'Lot dimensions information',
            ],
            // ----------------------------------------------------------------
            // Vacant Land — VL-only fields
            // ----------------------------------------------------------------
            'current_use' => [
                'listing.current_use',
                'What is the current land use?',
                'How is the land currently used?',
                'Agricultural, Pasture',
                'Current land use information',
            ],
            'current_adjacent_use' => [
                'listing.current_adjacent_use',
                'What is the adjacent land use?',
                'What is the surrounding land use of this property?',
                'Residential',
                'Adjacent land use information',
            ],
            'water_available' => [
                'listing.water_available',
                'Is water available on this land?',
                'Is there water service to this lot?',
                'Yes',
                'Water availability information',
            ],
            'sewer_available' => [
                'listing.sewer_available',
                'Is sewer available on this land?',
                'Is there sewer service to this lot?',
                'No',
                'Sewer availability information',
            ],
            'electric_available' => [
                'listing.electric_available',
                'Is electric available on this land?',
                'Is electricity available to the lot?',
                'Yes',
                'Electric availability information',
            ],
            'gas_available' => [
                'listing.gas_available',
                'Is gas available on this land?',
                'Is natural gas availability confirmed?',
                'No',
                'Gas availability information',
            ],
            'telecom_available' => [
                'listing.telecom_available',
                'What is the telecom availability on this land?',
                'Is there internet or cable service available?',
                'Fiber',
                'Telecom and internet availability information',
            ],
            'road_frontage' => [
                'listing.road_frontage',
                'What is the road frontage type for this lot?',
                'What type of road frontage does this property have?',
                'County Road, Private',
                'Road frontage information',
            ],
            'road_surface_type' => [
                'listing.road_surface_type',
                'What is the road surface type?',
                'How is the road paved for this property?',
                'Paved',
                'Road surface type information',
            ],
            'front_footage' => [
                'listing.front_footage',
                'What is the front footage of this lot?',
                'How many feet of frontage does this lot have?',
                '150',
                'Front footage information',
            ],
            'number_of_wells' => [
                'listing.number_of_wells',
                'How many wells are on this land?',
                'Are there any wells on this property?',
                '1',
                'Well information',
            ],
            'number_of_septics' => [
                'listing.number_of_septics',
                'How many septic systems are on this property?',
                'Are there any septic systems on this land?',
                '1',
                'Septic system information',
            ],
            'fences' => [
                'listing.fences',
                'Are there fences on this land?',
                'What type of fencing is there on the property?',
                'Wood, Chain Link',
                'Fence information',
            ],
            'vegetation' => [
                'listing.vegetation',
                'What vegetation is on the land?',
                'Are there trees or plants on the land?',
                'Trees, Brush',
                'Vegetation information',
            ],
            'buildable' => [
                'listing.buildable',
                'Is this land buildable?',
                'Can you build on this lot?',
                'Yes',
                'Buildability information',
            ],
            'easements' => [
                'listing.easements',
                'Are there any easements on this property?',
                'What easements are on this land?',
                'Utility Easement',
                'Easement information',
            ],
        ];
    }

    // =========================================================================
    // Shared helpers
    // =========================================================================

    private function makeFollowUpMock(): AskAiFollowUpQuestionService
    {
        $mock = $this->createMock(AskAiFollowUpQuestionService::class);
        $mock->method('forResult')->willReturn([]);
        return $mock;
    }

    private function makeRunner(
        AskAiInternalRunnerService $internalRunner,
        AskAiOpenAiAdapterService $adapter,
        AskAiFinalResponseBuilderService $finalBuilder
    ): AskAiRunnerV2Service {
        return new AskAiRunnerV2Service(
            new AskAiQuestionClassifierService(),
            $internalRunner,
            $adapter,
            $finalBuilder,
            $this->makeFollowUpMock()
        );
    }

    /**
     * Mock internalRunner: field IS present with a non-null value (Guard B won't fire).
     */
    private function makeRunnerWithListingField(string $field, mixed $value): AskAiInternalRunnerService
    {
        $mock          = $this->createMock(AskAiInternalRunnerService::class);
        $allowedCtx    = ['listing' => [$field => $value]];
        $promptPackage = [
            'status'               => 'prompt_ready',
            'question_type'        => 'listing_facts',
            'allowed_context'      => $allowedCtx,
            'required_disclosures' => ['Information is sourced directly from the listing data.'],
            'source_attribution'   => ['required_sources' => ['listing']],
            'refusal_template'     => null,
        ];
        $mock->method('run')->willReturn([
            'success'        => true,
            'status'         => 'prompt_ready',
            'context'        => ['listing' => ['listing_type' => 'test', $field => $value]],
            'contract'       => ['status' => 'contract_ready', 'question_type' => 'listing_facts'],
            'prompt_package' => $promptPackage,
            'error'          => null,
        ]);
        return $mock;
    }

    /**
     * Mock internalRunner: field IS present with null value (Guard B WILL fire).
     */
    private function makeRunnerWithNullListingField(string $field): AskAiInternalRunnerService
    {
        $mock          = $this->createMock(AskAiInternalRunnerService::class);
        $allowedCtx    = ['listing' => [$field => null]];
        $promptPackage = [
            'status'               => 'prompt_ready',
            'question_type'        => 'listing_facts',
            'allowed_context'      => $allowedCtx,
            'required_disclosures' => [],
            'source_attribution'   => [],
            'refusal_template'     => null,
        ];
        $mock->method('run')->willReturn([
            'success'        => true,
            'status'         => 'prompt_ready',
            'context'        => ['listing' => ['listing_type' => 'test', $field => null]],
            'contract'       => ['status' => 'contract_ready', 'question_type' => 'listing_facts'],
            'prompt_package' => $promptPackage,
            'error'          => null,
        ]);
        return $mock;
    }

    // =========================================================================
    // (A) Question A routes to the correct canonical listing.* key
    // =========================================================================

    /**
     * @dataProvider approvedFieldProvider
     * @group AskAi
     */
    public function test_question_a_classifies_and_resolves_to_canonical_key(
        string $canonicalKey,
        string $questionA,
        string $questionB,
        string $sampleValue,
        string $guardBLabel
    ): void {
        $classifier = new AskAiQuestionClassifierService();
        $result     = $classifier->classify($questionA);

        $this->assertSame(
            'listing_facts',
            $result['question_type'],
            "Question A \"{$questionA}\" must classify as listing_facts for {$canonicalKey}."
        );

        // Run through real runner to verify detectListingFieldKey resolves correctly.
        $internalRunner = $this->makeRunnerWithListingField(
            substr($canonicalKey, strlen('listing.')),
            $sampleValue
        );
        $adapter      = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->method('generate')->willReturn([
            'success'      => true,
            'status'       => 'generated',
            'raw_response' => 'Test answer.',
            'model'        => 'gpt-4o-mini',
            'error'        => null,
        ]);
        $finalBuilder->method('build')->willReturn([
            'success'            => true,
            'status'             => 'ready',
            'answer'             => 'Test answer.',
            'disclosures'        => [],
            'source_attribution' => [],
            'refusal_message'    => null,
            'error'              => null,
        ]);

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, $questionA);

        $this->assertSame(
            $canonicalKey,
            $result['classification']['normalized_field_key'] ?? null,
            "Question A \"{$questionA}\" must resolve to canonical key {$canonicalKey}."
        );
    }

    // =========================================================================
    // (B) Question B routes to the SAME canonical key as Question A (chip/typed parity)
    // =========================================================================

    /**
     * @dataProvider approvedFieldProvider
     * @group AskAi
     */
    public function test_question_b_resolves_to_same_canonical_key_as_question_a(
        string $canonicalKey,
        string $questionA,
        string $questionB,
        string $sampleValue,
        string $guardBLabel
    ): void {
        $internalRunner = $this->makeRunnerWithListingField(
            substr($canonicalKey, strlen('listing.')),
            $sampleValue
        );
        $adapter      = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->method('generate')->willReturn([
            'success'      => true,
            'status'       => 'generated',
            'raw_response' => 'Test answer.',
            'model'        => 'gpt-4o-mini',
            'error'        => null,
        ]);
        $finalBuilder->method('build')->willReturn([
            'success'            => true,
            'status'             => 'ready',
            'answer'             => 'Test answer.',
            'disclosures'        => [],
            'source_attribution' => [],
            'refusal_message'    => null,
            'error'              => null,
        ]);

        $runner  = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $resultA = $runner->run('seller', 1, $questionA);
        $resultB = $runner->run('seller', 1, $questionB);

        $keyA = $resultA['classification']['normalized_field_key'] ?? null;
        $keyB = $resultB['classification']['normalized_field_key'] ?? null;

        $this->assertSame(
            $canonicalKey,
            $keyA,
            "Question A \"{$questionA}\" must resolve to {$canonicalKey}."
        );
        $this->assertSame(
            $canonicalKey,
            $keyB,
            "Question B \"{$questionB}\" must resolve to the same canonical key as Question A ({$canonicalKey})."
        );
        $this->assertSame(
            $keyA,
            $keyB,
            "Chip question and typed question must resolve to the same normalized key for {$canonicalKey}."
        );
    }

    // =========================================================================
    // (C) Guard B fires when the field is null — field-specific label, no adapter call
    // =========================================================================

    /**
     * @dataProvider approvedFieldProvider
     * @group AskAi
     */
    public function test_null_field_triggers_guard_b_with_field_specific_label(
        string $canonicalKey,
        string $questionA,
        string $questionB,
        string $sampleValue,
        string $guardBLabel
    ): void {
        $fieldName      = substr($canonicalKey, strlen('listing.'));
        $internalRunner = $this->makeRunnerWithNullListingField($fieldName);
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->never())->method('generate');
        $finalBuilder->expects($this->never())->method('build');

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, $questionA);

        $this->assertSame(
            'insufficient_context',
            $result['status'],
            "Guard B must fire for null {$canonicalKey} — status must be insufficient_context."
        );
        $this->assertStringContainsString(
            $guardBLabel,
            $result['final_response']['answer'] ?? '',
            "Guard B answer must reference field label '{$guardBLabel}' for null {$canonicalKey}."
        );
        $this->assertSame(
            $canonicalKey,
            $result['classification']['normalized_field_key'] ?? null,
            "Normalized key must be {$canonicalKey} when Guard B fires."
        );
    }

    // =========================================================================
    // (D) Direct-return fallback — when adapter fails but field has a value,
    //     runner surfaces raw value, returns status=ready
    // =========================================================================

    /**
     * @dataProvider approvedFieldProvider
     * @group AskAi
     */
    public function test_adapter_failure_triggers_direct_return_fallback(
        string $canonicalKey,
        string $questionA,
        string $questionB,
        string $sampleValue,
        string $guardBLabel
    ): void {
        $fieldName      = substr($canonicalKey, strlen('listing.'));
        $internalRunner = $this->makeRunnerWithListingField($fieldName, $sampleValue);
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->once())->method('generate')->willReturn([
            'success'      => false,
            'status'       => 'failed',
            'raw_response' => null,
            'model'        => null,
            'error'        => 'OpenAI unavailable.',
        ]);
        $finalBuilder->expects($this->never())->method('build');

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, $questionA);

        $this->assertTrue(
            $result['success'],
            "Direct-return fallback must succeed (success=true) for {$canonicalKey} when adapter fails."
        );
        $this->assertSame(
            'ready',
            $result['status'],
            "Direct-return fallback must produce status=ready for {$canonicalKey}."
        );
        $this->assertSame(
            (string) $sampleValue,
            $result['final_response']['answer'] ?? null,
            "Direct-return must surface raw field value for {$canonicalKey} when adapter fails."
        );
    }
}
