<?php

namespace Tests\Unit\Services\Dna;

use App\Models\PropertyDnaProfile;
use App\Services\Dna\LandlordDnaReportService;
use PHPUnit\Framework\TestCase;

/**
 * LandlordDnaReportServiceTest
 *
 * Verifies the LandlordDnaReportService deterministic interpretation layer against
 * in-memory PropertyDnaProfile stubs. No database connection is required —
 * all test data is constructed inline using PHPUnit\Framework\TestCase only.
 *
 * Each test asserts one or more of:
 *   (a) Guard conditions — wrong listing_type or sparse profile returns insufficient_data
 *   (b) Output contract shape — all required keys present in every result
 *   (c) landlord_priorities — correct labels from score fields above threshold
 *   (d) property_strengths — correct labels from tags and scores
 *   (e) leasing_considerations — "Not Specified" labels for absent dimensions
 *   (f) tenant_fit_signals — factual fit indicators from tags/scores
 *   (g) marketing_opportunities — verbatim pass-through of ai_marketing_hooks
 *   (h) lease_compatibility_signals — tag-extracted compatibility signals
 *   (i) missing_inputs — populated correctly for absent dimensions
 *   (j) Contract consistency — listing_type is always 'landlord' in every output path
 *   (k) No AI/OpenAI imports — service is deterministic only
 */
class LandlordDnaReportServiceTest extends TestCase
{
    private LandlordDnaReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LandlordDnaReportService();
    }

    // -------------------------------------------------------------------------
    // Helpers — build in-memory PropertyDnaProfile stubs
    // -------------------------------------------------------------------------

    /**
     * Create a PropertyDnaProfile stub without touching the database.
     * Sets attributes directly on an unsaved model instance.
     */
    private function makeProfile(array $attributes): PropertyDnaProfile
    {
        $profile = new PropertyDnaProfile();

        foreach ($attributes as $key => $value) {
            $profile->$key = $value;
        }

        return $profile;
    }

    /**
     * Build a profile for a 'landlord' listing type with sane defaults.
     * Completeness and two score fields set to pass the sparse guard.
     */
    private function makeBaseProfile(array $overrides = []): PropertyDnaProfile
    {
        return $this->makeProfile(array_merge([
            'listing_type'             => 'landlord',
            'listing_id'               => 99,
            'overall_dna_completeness' => 50.0,
            'flexibility_score'        => 70.0,
            'financial_score'          => 65.0,
            'marketing_score'          => null,
            'compatibility_score'      => null,
            'occupant_qualification_score' => null,
            'commercial_score'         => null,
            'condition_score'          => null,
            'physical_score'           => null,
            'location_score'           => null,
            'legal_score'              => null,
            'ai_buyer_archetype_tags'  => [],
            'ai_marketing_hooks'       => [],
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // (a) Guard conditions
    // -------------------------------------------------------------------------

    /** @test */
    public function it_returns_insufficient_data_when_listing_type_is_not_landlord(): void
    {
        $profile = $this->makeBaseProfile(['listing_type' => 'seller']);

        $result = $this->service->generate($profile);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_data', $result['status']);
        $this->assertSame('landlord', $result['listing_type']);
        $this->assertEmpty($result['landlord_priorities']);
    }

    /** @test */
    public function it_returns_insufficient_data_for_tenant_listing_type(): void
    {
        $profile = $this->makeBaseProfile(['listing_type' => 'tenant']);

        $result = $this->service->generate($profile);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_data', $result['status']);
        $this->assertContains('listing_type must be landlord', $result['missing_inputs']);
    }

    /** @test */
    public function it_returns_insufficient_data_for_buyer_listing_type(): void
    {
        $profile = $this->makeBaseProfile(['listing_type' => 'buyer']);

        $result = $this->service->generate($profile);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_data', $result['status']);
    }

    /** @test */
    public function it_returns_insufficient_data_for_sparse_profile_with_low_completeness_and_no_scores(): void
    {
        $profile = $this->makeProfile([
            'listing_type'             => 'landlord',
            'listing_id'               => 5,
            'overall_dna_completeness' => 5.0,
            'flexibility_score'        => null,
            'financial_score'          => null,
            'marketing_score'          => null,
            'compatibility_score'      => null,
            'occupant_qualification_score' => null,
            'commercial_score'         => null,
            'condition_score'          => null,
            'physical_score'           => null,
            'location_score'           => null,
            'legal_score'              => null,
            'ai_buyer_archetype_tags'  => [],
            'ai_marketing_hooks'       => [],
        ]);

        $result = $this->service->generate($profile);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_data', $result['status']);
        $this->assertNotEmpty($result['missing_inputs']);
    }

    /** @test */
    public function it_proceeds_when_completeness_is_low_but_scores_are_populated(): void
    {
        // Low completeness but two score fields present — should pass the sparse guard.
        $profile = $this->makeProfile([
            'listing_type'             => 'landlord',
            'listing_id'               => 7,
            'overall_dna_completeness' => 5.0,
            'flexibility_score'        => 55.0,
            'financial_score'          => 40.0,
            'marketing_score'          => null,
            'compatibility_score'      => null,
            'occupant_qualification_score' => null,
            'commercial_score'         => null,
            'condition_score'          => null,
            'physical_score'           => null,
            'location_score'           => null,
            'legal_score'              => null,
            'ai_buyer_archetype_tags'  => [],
            'ai_marketing_hooks'       => [],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('generated', $result['status']);
        $this->assertTrue($result['success']);
    }

    // -------------------------------------------------------------------------
    // (b) Output contract shape — all required keys present in every result
    // -------------------------------------------------------------------------

    /** @test */
    public function output_contract_shape_is_consistent_for_insufficient_data_wrong_type(): void
    {
        $profile = $this->makeBaseProfile(['listing_type' => 'seller']);

        $result = $this->service->generate($profile);

        $this->assertOutputContractShape($result);
    }

    /** @test */
    public function output_contract_shape_is_consistent_for_insufficient_data_sparse(): void
    {
        $profile = $this->makeProfile([
            'listing_type'             => 'landlord',
            'listing_id'               => 3,
            'overall_dna_completeness' => 2.0,
            'flexibility_score'        => null,
            'financial_score'          => null,
            'marketing_score'          => null,
            'compatibility_score'      => null,
            'occupant_qualification_score' => null,
            'commercial_score'         => null,
            'condition_score'          => null,
            'physical_score'           => null,
            'location_score'           => null,
            'legal_score'              => null,
            'ai_buyer_archetype_tags'  => [],
            'ai_marketing_hooks'       => [],
        ]);

        $result = $this->service->generate($profile);

        $this->assertOutputContractShape($result);
    }

    /** @test */
    public function output_contract_shape_is_consistent_for_generated_result(): void
    {
        $profile = $this->makeBaseProfile();

        $result = $this->service->generate($profile);

        $this->assertSame('generated', $result['status']);
        $this->assertOutputContractShape($result);

        // Additional type assertions for generated result.
        $this->assertTrue($result['success']);
        $this->assertSame('landlord', $result['listing_type']);
        $this->assertSame(99, $result['listing_id']);
        $this->assertIsArray($result['landlord_priorities']);
        $this->assertIsArray($result['property_strengths']);
        $this->assertIsArray($result['leasing_considerations']);
        $this->assertIsArray($result['tenant_fit_signals']);
        $this->assertIsArray($result['marketing_opportunities']);
        $this->assertIsArray($result['lease_compatibility_signals']);
        $this->assertIsArray($result['signals']);
        $this->assertIsArray($result['missing_inputs']);
        $this->assertNull($result['error']);
    }

    // -------------------------------------------------------------------------
    // (c) landlord_priorities — score-derived labels
    // -------------------------------------------------------------------------

    /** @test */
    public function it_maps_flexibility_score_above_threshold_to_leasing_flexibility_focus(): void
    {
        $profile = $this->makeBaseProfile(['flexibility_score' => 75.0]);

        $result = $this->service->generate($profile);

        $this->assertContains('Leasing Flexibility Focus', $result['landlord_priorities']);
    }

    /** @test */
    public function it_maps_financial_score_above_threshold_to_rental_income_focus(): void
    {
        $profile = $this->makeBaseProfile(['financial_score' => 80.0]);

        $result = $this->service->generate($profile);

        $this->assertContains('Rental Income Focus', $result['landlord_priorities']);
    }

    /** @test */
    public function it_does_not_include_priority_label_when_score_is_below_threshold(): void
    {
        $profile = $this->makeBaseProfile([
            'flexibility_score' => 55.0,
            'financial_score'   => 40.0,
        ]);

        $result = $this->service->generate($profile);

        $this->assertNotContains('Leasing Flexibility Focus', $result['landlord_priorities']);
        $this->assertNotContains('Rental Income Focus', $result['landlord_priorities']);
    }

    /** @test */
    public function it_maps_all_seven_score_fields_to_priority_labels_when_above_threshold(): void
    {
        $profile = $this->makeBaseProfile([
            'flexibility_score'            => 85.0,
            'financial_score'              => 85.0,
            'marketing_score'              => 85.0,
            'compatibility_score'          => 85.0,
            'occupant_qualification_score' => 85.0,
            'commercial_score'             => 85.0,
            'condition_score'              => 85.0,
        ]);

        $result = $this->service->generate($profile);

        $this->assertContains('Leasing Flexibility Focus',      $result['landlord_priorities']);
        $this->assertContains('Rental Income Focus',            $result['landlord_priorities']);
        $this->assertContains('Marketing Visibility Focus',     $result['landlord_priorities']);
        $this->assertContains('Tenant Compatibility Focus',     $result['landlord_priorities']);
        $this->assertContains('Occupant Qualification Focus',   $result['landlord_priorities']);
        $this->assertContains('Commercial Use Focus',           $result['landlord_priorities']);
        $this->assertContains('Property Condition Focus',       $result['landlord_priorities']);
    }

    /** @test */
    public function it_returns_empty_landlord_priorities_when_all_scores_are_null(): void
    {
        $profile = $this->makeProfile([
            'listing_type'             => 'landlord',
            'listing_id'               => 10,
            'overall_dna_completeness' => 50.0,
            'flexibility_score'        => null,
            'financial_score'          => null,
            'marketing_score'          => null,
            'compatibility_score'      => null,
            'occupant_qualification_score' => null,
            'commercial_score'         => null,
            'condition_score'          => null,
            'physical_score'           => 60.0,
            'location_score'           => 60.0,
            'legal_score'              => null,
            'ai_buyer_archetype_tags'  => [],
            'ai_marketing_hooks'       => [],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('generated', $result['status']);
        $this->assertSame([], $result['landlord_priorities']);
    }

    // -------------------------------------------------------------------------
    // (d) property_strengths — tag and score driven
    // -------------------------------------------------------------------------

    /** @test */
    public function it_includes_pet_friendly_strength_when_pets_allowed_tag_present(): void
    {
        $profile = $this->makeBaseProfile([
            'ai_buyer_archetype_tags' => ['policy:pets-allowed'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertContains('Pet-Friendly Policy', $result['property_strengths']);
    }

    /** @test */
    public function it_includes_pool_strength_when_pool_amenity_tag_present(): void
    {
        $profile = $this->makeBaseProfile([
            'ai_buyer_archetype_tags' => ['amenity:pool'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertContains('Pool On-Site', $result['property_strengths']);
    }

    /** @test */
    public function it_includes_parking_strength_when_garage_tag_present(): void
    {
        $profile = $this->makeBaseProfile([
            'ai_buyer_archetype_tags' => ['amenity:garage'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertContains('Parking Available', $result['property_strengths']);
    }

    /** @test */
    public function it_includes_furnished_strength_when_furnished_amenity_tag_present(): void
    {
        $profile = $this->makeBaseProfile([
            'ai_buyer_archetype_tags' => ['amenity:furnished'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertContains('Furnished Unit', $result['property_strengths']);
    }

    /** @test */
    public function it_includes_waterfront_strength_when_waterfront_feature_tag_present(): void
    {
        $profile = $this->makeBaseProfile([
            'ai_buyer_archetype_tags' => ['feature:waterfront'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertContains('Waterfront Property', $result['property_strengths']);
    }

    /** @test */
    public function it_includes_commercial_strength_when_use_commercial_tag_present(): void
    {
        $profile = $this->makeBaseProfile([
            'ai_buyer_archetype_tags' => ['use:commercial'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertContains('Commercial Use Eligible', $result['property_strengths']);
    }

    /** @test */
    public function it_includes_condition_strength_from_strong_condition_score(): void
    {
        $profile = $this->makeBaseProfile(['condition_score' => 90.0]);

        $result = $this->service->generate($profile);

        $this->assertContains('Strong Condition Score', $result['property_strengths']);
    }

    /** @test */
    public function it_returns_empty_property_strengths_when_no_supporting_tags_or_scores(): void
    {
        $profile = $this->makeBaseProfile([
            'ai_buyer_archetype_tags' => [],
            'condition_score'         => null,
            'commercial_score'        => null,
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame([], $result['property_strengths']);
    }

    // -------------------------------------------------------------------------
    // (e) leasing_considerations — "Not Specified" labels for absent dimensions
    // -------------------------------------------------------------------------

    /** @test */
    public function it_flags_pet_policy_not_specified_when_no_pet_policy_tags_present(): void
    {
        $profile = $this->makeBaseProfile(['ai_buyer_archetype_tags' => []]);

        $result = $this->service->generate($profile);

        $this->assertContains('Pet Policy: Not Specified', $result['leasing_considerations']);
    }

    /** @test */
    public function it_does_not_flag_pet_policy_when_pets_allowed_tag_present(): void
    {
        $profile = $this->makeBaseProfile([
            'ai_buyer_archetype_tags' => ['policy:pets-allowed'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertNotContains('Pet Policy: Not Specified', $result['leasing_considerations']);
    }

    /** @test */
    public function it_does_not_flag_pet_policy_when_no_pets_tag_present(): void
    {
        $profile = $this->makeBaseProfile([
            'ai_buyer_archetype_tags' => ['policy:no-pets'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertNotContains('Pet Policy: Not Specified', $result['leasing_considerations']);
    }

    /** @test */
    public function it_flags_smoking_policy_not_specified_when_no_smoking_policy_tags(): void
    {
        $profile = $this->makeBaseProfile(['ai_buyer_archetype_tags' => []]);

        $result = $this->service->generate($profile);

        $this->assertContains('Smoking Policy: Not Specified', $result['leasing_considerations']);
    }

    // -------------------------------------------------------------------------
    // (f) tenant_fit_signals — factual indicators
    // -------------------------------------------------------------------------

    /** @test */
    public function it_sets_pet_policy_fit_signal_to_pets_allowed(): void
    {
        $profile = $this->makeBaseProfile([
            'ai_buyer_archetype_tags' => ['policy:pets-allowed'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Pets Allowed', $result['tenant_fit_signals']['pet_policy']);
    }

    /** @test */
    public function it_sets_pet_policy_fit_signal_to_no_pets(): void
    {
        $profile = $this->makeBaseProfile([
            'ai_buyer_archetype_tags' => ['policy:no-pets'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('No Pets', $result['tenant_fit_signals']['pet_policy']);
    }

    /** @test */
    public function it_returns_null_pet_policy_fit_signal_when_no_pet_tag(): void
    {
        $profile = $this->makeBaseProfile(['ai_buyer_archetype_tags' => []]);

        $result = $this->service->generate($profile);

        $this->assertNull($result['tenant_fit_signals']['pet_policy']);
    }

    /** @test */
    public function it_sets_lease_option_fit_signal_when_structure_lease_option_tag_present(): void
    {
        $profile = $this->makeBaseProfile([
            'ai_buyer_archetype_tags' => ['structure:lease-option'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Lease-Option Available', $result['tenant_fit_signals']['lease_option']);
    }

    /** @test */
    public function it_sets_move_in_date_fit_signal_when_timing_tag_present(): void
    {
        $profile = $this->makeBaseProfile([
            'ai_buyer_archetype_tags' => ['timing:available-now'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Availability Date Specified', $result['tenant_fit_signals']['move_in_date']);
    }

    // -------------------------------------------------------------------------
    // (g) marketing_opportunities — verbatim pass-through of ai_marketing_hooks
    // -------------------------------------------------------------------------

    /** @test */
    public function it_passes_through_marketing_hooks_verbatim(): void
    {
        $hooks = [
            ['trait' => 'property_type', 'value' => 'Single Family'],
            ['trait' => 'lease_length',  'value' => 'Flexible'],
        ];

        $profile = $this->makeBaseProfile(['ai_marketing_hooks' => $hooks]);

        $result = $this->service->generate($profile);

        $this->assertSame($hooks, $result['marketing_opportunities']);
    }

    /** @test */
    public function it_returns_empty_marketing_opportunities_when_no_hooks_on_profile(): void
    {
        $profile = $this->makeBaseProfile(['ai_marketing_hooks' => []]);

        $result = $this->service->generate($profile);

        $this->assertSame([], $result['marketing_opportunities']);
    }

    // -------------------------------------------------------------------------
    // (h) lease_compatibility_signals — tag-extracted signals
    // -------------------------------------------------------------------------

    /** @test */
    public function it_extracts_pet_policy_lease_compatibility_signal_from_pets_allowed_tag(): void
    {
        $profile = $this->makeBaseProfile([
            'ai_buyer_archetype_tags' => ['policy:pets-allowed'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('pets-allowed', $result['lease_compatibility_signals']['pet_policy']);
    }

    /** @test */
    public function it_extracts_smoking_policy_signal_from_no_smoking_tag(): void
    {
        $profile = $this->makeBaseProfile([
            'ai_buyer_archetype_tags' => ['policy:no-smoking'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('no-smoking', $result['lease_compatibility_signals']['smoking_policy']);
    }

    /** @test */
    public function it_extracts_furnishing_signal_from_amenity_furnished_tag(): void
    {
        $profile = $this->makeBaseProfile([
            'ai_buyer_archetype_tags' => ['amenity:furnished'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('furnished', $result['lease_compatibility_signals']['furnishing_terms']);
    }

    /** @test */
    public function it_extracts_lease_option_compatibility_signal(): void
    {
        $profile = $this->makeBaseProfile([
            'ai_buyer_archetype_tags' => ['structure:lease-option'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('available', $result['lease_compatibility_signals']['lease_option']);
    }

    /** @test */
    public function it_extracts_commercial_use_compatibility_signal(): void
    {
        $profile = $this->makeBaseProfile([
            'ai_buyer_archetype_tags' => ['use:commercial'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('eligible', $result['lease_compatibility_signals']['commercial_use']);
    }

    /** @test */
    public function lease_compatibility_signals_are_null_when_no_relevant_tags_present(): void
    {
        $profile = $this->makeBaseProfile(['ai_buyer_archetype_tags' => []]);

        $result = $this->service->generate($profile);

        $this->assertNull($result['lease_compatibility_signals']['pet_policy']);
        $this->assertNull($result['lease_compatibility_signals']['smoking_policy']);
        $this->assertNull($result['lease_compatibility_signals']['furnishing_terms']);
        $this->assertNull($result['lease_compatibility_signals']['lease_option']);
        $this->assertNull($result['lease_compatibility_signals']['commercial_use']);
    }

    // -------------------------------------------------------------------------
    // (i) missing_inputs — populated correctly
    // -------------------------------------------------------------------------

    /** @test */
    public function missing_inputs_includes_pet_policy_when_no_pet_tags_present(): void
    {
        $profile = $this->makeBaseProfile(['ai_buyer_archetype_tags' => []]);

        $result = $this->service->generate($profile);

        $this->assertContains('Pet policy', $result['missing_inputs']);
    }

    /** @test */
    public function missing_inputs_does_not_include_pet_policy_when_pet_tag_present(): void
    {
        $profile = $this->makeBaseProfile([
            'ai_buyer_archetype_tags' => ['policy:pets-allowed'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertNotContains('Pet policy', $result['missing_inputs']);
    }

    /** @test */
    public function missing_inputs_includes_condition_score_when_null(): void
    {
        $profile = $this->makeBaseProfile(['condition_score' => null]);

        $result = $this->service->generate($profile);

        $this->assertContains('Condition score', $result['missing_inputs']);
    }

    /** @test */
    public function missing_inputs_does_not_include_condition_score_when_present(): void
    {
        $profile = $this->makeBaseProfile(['condition_score' => 70.0]);

        $result = $this->service->generate($profile);

        $this->assertNotContains('Condition score', $result['missing_inputs']);
    }

    // -------------------------------------------------------------------------
    // (j) Contract consistency — listing_type is always 'landlord' in every path
    // -------------------------------------------------------------------------

    /** @test */
    public function it_always_returns_listing_type_landlord_for_wrong_type_guard(): void
    {
        $profile = $this->makeBaseProfile(['listing_type' => 'seller']);

        $result = $this->service->generate($profile);

        $this->assertSame('landlord', $result['listing_type'],
            'listing_type must be landlord in all output paths — service is landlord-only');
    }

    /** @test */
    public function it_always_returns_listing_type_landlord_for_sparse_guard(): void
    {
        $profile = $this->makeProfile([
            'listing_type'             => 'landlord',
            'listing_id'               => 1,
            'overall_dna_completeness' => 1.0,
            'flexibility_score'        => null,
            'financial_score'          => null,
            'marketing_score'          => null,
            'compatibility_score'      => null,
            'occupant_qualification_score' => null,
            'commercial_score'         => null,
            'condition_score'          => null,
            'physical_score'           => null,
            'location_score'           => null,
            'legal_score'              => null,
            'ai_buyer_archetype_tags'  => [],
            'ai_marketing_hooks'       => [],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('landlord', $result['listing_type']);
    }

    /** @test */
    public function it_always_returns_listing_type_landlord_for_generated_result(): void
    {
        $profile = $this->makeBaseProfile();

        $result = $this->service->generate($profile);

        $this->assertSame('landlord', $result['listing_type']);
    }

    // -------------------------------------------------------------------------
    // (k) No AI/OpenAI imports — service is deterministic only
    // -------------------------------------------------------------------------

    /** @test */
    public function service_file_contains_no_openai_or_ai_imports(): void
    {
        $serviceFile = file_get_contents(
            __DIR__ . '/../../../../app/Services/Dna/LandlordDnaReportService.php'
        );

        // Check for actual import/use statements — not just mentions in comments.
        $this->assertFalse(
            (bool) preg_match('/^use\s+OpenAI/im', $serviceFile),
            'LandlordDnaReportService must not import the OpenAI namespace'
        );
        $this->assertFalse(
            (bool) preg_match('/^use\s+.*AI\\\\Client/im', $serviceFile),
            'LandlordDnaReportService must not import AI client classes'
        );
        // No instantiation of OpenAI client.
        $this->assertFalse(
            (bool) preg_match('/new\s+\\\\?OpenAI/i', $serviceFile),
            'LandlordDnaReportService must not instantiate OpenAI classes'
        );
        // No OpenAI:: static method calls.
        $this->assertFalse(
            (bool) preg_match('/OpenAI::/i', $serviceFile),
            'LandlordDnaReportService must not call OpenAI static methods'
        );
    }

    // -------------------------------------------------------------------------
    // Full generated report — all output sections populated
    // -------------------------------------------------------------------------

    /** @test */
    public function full_report_populates_all_output_sections_from_rich_profile(): void
    {
        $hooks = [
            ['trait' => 'property_type', 'value' => 'Single Family'],
            ['trait' => 'lease_length',  'value' => 'Month-to-Month'],
        ];

        $profile = $this->makeBaseProfile([
            'listing_id'               => 42,
            'overall_dna_completeness' => 75.0,
            'flexibility_score'        => 80.0,
            'financial_score'          => 70.0,
            'marketing_score'          => 65.0,
            'compatibility_score'      => 62.0,
            'occupant_qualification_score' => 61.0,
            'commercial_score'         => 85.0,
            'condition_score'          => 90.0,
            'ai_buyer_archetype_tags'  => [
                'policy:pets-allowed',
                'policy:no-smoking',
                'amenity:pool',
                'amenity:garage',
                'feature:waterfront',
                'use:commercial',
                'structure:lease-option',
                'timing:available-now',
                'parking:covered',
            ],
            'ai_marketing_hooks' => $hooks,
        ]);

        $result = $this->service->generate($profile);

        // Guard passed.
        $this->assertTrue($result['success']);
        $this->assertSame('generated', $result['status']);
        $this->assertSame(42, $result['listing_id']);

        // Priorities — all above 60.
        $this->assertContains('Leasing Flexibility Focus',    $result['landlord_priorities']);
        $this->assertContains('Rental Income Focus',          $result['landlord_priorities']);
        $this->assertContains('Commercial Use Focus',         $result['landlord_priorities']);
        $this->assertContains('Property Condition Focus',     $result['landlord_priorities']);

        // Strengths.
        $this->assertContains('Pet-Friendly Policy',       $result['property_strengths']);
        $this->assertContains('Pool On-Site',              $result['property_strengths']);
        $this->assertContains('Waterfront Property',       $result['property_strengths']);
        $this->assertContains('Commercial Use Eligible',   $result['property_strengths']);
        $this->assertContains('Strong Condition Score',    $result['property_strengths']);
        $this->assertContains('Lease-Option Available',    $result['property_strengths']);

        // Leasing considerations — smoking and furnishing still unspecified.
        $this->assertNotContains('Pet Policy: Not Specified',     $result['leasing_considerations']);
        $this->assertNotContains('Smoking Policy: Not Specified',  $result['leasing_considerations']);
        $this->assertContains('Furnishing Terms: Not Specified',  $result['leasing_considerations']);

        // Tenant fit signals.
        $this->assertSame('Pets Allowed', $result['tenant_fit_signals']['pet_policy']);
        $this->assertSame('No Smoking',   $result['tenant_fit_signals']['smoking_policy']);
        $this->assertSame('Lease-Option Available', $result['tenant_fit_signals']['lease_option']);

        // Marketing opportunities — verbatim.
        $this->assertSame($hooks, $result['marketing_opportunities']);

        // Lease compatibility signals.
        $this->assertSame('pets-allowed', $result['lease_compatibility_signals']['pet_policy']);
        $this->assertSame('no-smoking',   $result['lease_compatibility_signals']['smoking_policy']);
        $this->assertSame('eligible',     $result['lease_compatibility_signals']['commercial_use']);
        $this->assertSame('available',    $result['lease_compatibility_signals']['lease_option']);

        // No error.
        $this->assertNull($result['error']);
    }

    // -------------------------------------------------------------------------
    // Helper — assert the full output contract shape
    // -------------------------------------------------------------------------

    private function assertOutputContractShape(array $result): void
    {
        $this->assertArrayHasKey('success',                     $result);
        $this->assertArrayHasKey('status',                      $result);
        $this->assertArrayHasKey('listing_type',                $result);
        $this->assertArrayHasKey('listing_id',                  $result);
        $this->assertArrayHasKey('landlord_priorities',         $result);
        $this->assertArrayHasKey('property_strengths',          $result);
        $this->assertArrayHasKey('leasing_considerations',      $result);
        $this->assertArrayHasKey('tenant_fit_signals',          $result);
        $this->assertArrayHasKey('marketing_opportunities',     $result);
        $this->assertArrayHasKey('lease_compatibility_signals', $result);
        $this->assertArrayHasKey('signals',                     $result);
        $this->assertArrayHasKey('missing_inputs',              $result);
        $this->assertArrayHasKey('error',                       $result);

        $this->assertSame('landlord', $result['listing_type']);
        $this->assertIsBool($result['success']);
        $this->assertIsString($result['status']);
        $this->assertIsInt($result['listing_id']);
        $this->assertIsArray($result['landlord_priorities']);
        $this->assertIsArray($result['property_strengths']);
        $this->assertIsArray($result['leasing_considerations']);
        $this->assertIsArray($result['tenant_fit_signals']);
        $this->assertIsArray($result['marketing_opportunities']);
        $this->assertIsArray($result['lease_compatibility_signals']);
        $this->assertIsArray($result['signals']);
        $this->assertIsArray($result['missing_inputs']);
    }
}
