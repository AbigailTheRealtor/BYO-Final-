<?php

namespace Tests\Unit\Services\Dna;

use App\Models\BuyerTenantDnaProfile;
use App\Services\Dna\BuyerAvatarService;
use App\Services\Dna\BuyerAvatarProfileService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * BuyerAvatarServiceTest
 *
 * Verifies the BuyerAvatarService deterministic classification layer against
 * in-memory BuyerTenantDnaProfile stubs. No database connection is required —
 * all test data is constructed inline using PHPUnit\Framework\TestCase only.
 *
 * Each test asserts one or more of:
 *   (a) Guard conditions — wrong listing_type or low completeness returns insufficient_data
 *   (b) Output contract shape — all required keys present in every result
 *   (c) Avatar classification — correct primary and secondary avatars for given signals
 *   (d) missing_inputs — populated correctly from absent signal dimensions
 *   (e) Contract consistency — listing_type is always 'buyer' in every output path
 *   (f) No AI/OpenAI imports — service is deterministic only
 *   (g) New output keys — motivation, narrative, preference summary, personality tags,
 *       match preferences, confidence score, readiness score, avatar version
 *   (h) BuyerAvatarProfileService — buyer-only guard and delegation
 *
 * NOTE — Relocation Buyer is not tested here because there is no explicit timeline
 * signal in the current BuyerTenantDnaProfile (the DNA generator does not emit a
 * timeline tag or flag). Relocation Buyer tests will be added in a future phase
 * when the generator emits an explicit timeline signal.
 */
class BuyerAvatarServiceTest extends TestCase
{
    private BuyerAvatarService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BuyerAvatarService();
    }

    // -------------------------------------------------------------------------
    // Helpers — build in-memory BuyerTenantDnaProfile stubs
    // -------------------------------------------------------------------------

    /**
     * Create a BuyerTenantDnaProfile stub without touching the database.
     * Sets attributes directly on an unsaved model instance.
     */
    private function makeProfile(array $attributes): BuyerTenantDnaProfile
    {
        $profile = new BuyerTenantDnaProfile();

        foreach ($attributes as $key => $value) {
            $profile->$key = $value;
        }

        return $profile;
    }

    /**
     * Build a profile for a 'buyer' listing type with sane defaults.
     */
    private function makeBaseProfile(array $overrides = []): BuyerTenantDnaProfile
    {
        return $this->makeProfile(array_merge([
            'listing_type'            => 'buyer',
            'listing_id'              => 42,
            'preference_completeness' => 60.0,
            'lifestyle_tags'          => [],
            'deal_breaker_flags'      => [],
            'archetype_label'         => null,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // (a) Guard conditions
    // -------------------------------------------------------------------------

    /** @test */
    public function it_returns_insufficient_data_when_listing_type_is_not_buyer(): void
    {
        $profile = $this->makeBaseProfile(['listing_type' => 'tenant']);

        $result = $this->service->generate($profile);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_data', $result['status']);
        $this->assertNull($result['primary_avatar']);
        $this->assertSame([], $result['secondary_avatars']);
    }

    /** @test */
    public function it_returns_insufficient_data_when_preference_completeness_is_below_threshold(): void
    {
        $profile = $this->makeBaseProfile(['preference_completeness' => 10.0]);

        $result = $this->service->generate($profile);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_data', $result['status']);
        $this->assertNull($result['primary_avatar']);
        $this->assertNotEmpty($result['missing_inputs']);
    }

    /** @test */
    public function it_returns_insufficient_data_when_preference_completeness_is_exactly_zero(): void
    {
        $profile = $this->makeBaseProfile(['preference_completeness' => 0.0]);

        $result = $this->service->generate($profile);

        $this->assertSame('insufficient_data', $result['status']);
        $this->assertFalse($result['success']);
    }

    /** @test */
    public function it_proceeds_when_preference_completeness_meets_minimum_threshold(): void
    {
        $profile = $this->makeBaseProfile(['preference_completeness' => 20.0]);

        $result = $this->service->generate($profile);

        $this->assertNotSame('insufficient_data', $result['status']);
    }

    // -------------------------------------------------------------------------
    // (b) Output contract shape — all required keys present in every result
    // -------------------------------------------------------------------------

    /** @test */
    public function output_contract_shape_is_consistent_for_insufficient_data_result(): void
    {
        $profile = $this->makeBaseProfile(['preference_completeness' => 5.0]);

        $result = $this->service->generate($profile);

        $this->assertArrayHasKey('success',                  $result);
        $this->assertArrayHasKey('status',                   $result);
        $this->assertArrayHasKey('listing_type',             $result);
        $this->assertArrayHasKey('listing_id',               $result);
        $this->assertArrayHasKey('primary_avatar',           $result);
        $this->assertArrayHasKey('secondary_avatars',        $result);
        $this->assertArrayHasKey('signals',                  $result);
        $this->assertArrayHasKey('missing_inputs',           $result);
        $this->assertArrayHasKey('error',                    $result);
        $this->assertArrayHasKey('primary_motivation',       $result);
        $this->assertArrayHasKey('secondary_motivation',     $result);
        $this->assertArrayHasKey('buyer_narrative',          $result);
        $this->assertArrayHasKey('buyer_preference_summary', $result);
        $this->assertArrayHasKey('buyer_personality_tags',   $result);
        $this->assertArrayHasKey('buyer_match_preferences',  $result);
        $this->assertArrayHasKey('avatar_confidence_score',  $result);
        $this->assertArrayHasKey('buyer_readiness_score',    $result);
        $this->assertArrayHasKey('buyer_avatar_version',     $result);
    }

    /** @test */
    public function output_contract_shape_is_consistent_for_generated_result(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => ['prefers-type:SingleFamily'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertArrayHasKey('success',                  $result);
        $this->assertArrayHasKey('status',                   $result);
        $this->assertArrayHasKey('listing_type',             $result);
        $this->assertArrayHasKey('listing_id',               $result);
        $this->assertArrayHasKey('primary_avatar',           $result);
        $this->assertArrayHasKey('secondary_avatars',        $result);
        $this->assertArrayHasKey('signals',                  $result);
        $this->assertArrayHasKey('missing_inputs',           $result);
        $this->assertArrayHasKey('error',                    $result);
        $this->assertArrayHasKey('primary_motivation',       $result);
        $this->assertArrayHasKey('secondary_motivation',     $result);
        $this->assertArrayHasKey('buyer_narrative',          $result);
        $this->assertArrayHasKey('buyer_preference_summary', $result);
        $this->assertArrayHasKey('buyer_personality_tags',   $result);
        $this->assertArrayHasKey('buyer_match_preferences',  $result);
        $this->assertArrayHasKey('avatar_confidence_score',  $result);
        $this->assertArrayHasKey('buyer_readiness_score',    $result);
        $this->assertArrayHasKey('buyer_avatar_version',     $result);

        $this->assertTrue($result['success']);
        $this->assertSame('generated', $result['status']);
        $this->assertSame('buyer', $result['listing_type']);
        $this->assertSame(42, $result['listing_id']);
        $this->assertIsArray($result['secondary_avatars']);
        $this->assertIsArray($result['signals']);
        $this->assertIsArray($result['missing_inputs']);
        $this->assertNull($result['error']);
    }

    // -------------------------------------------------------------------------
    // (c) Avatar classification
    // -------------------------------------------------------------------------

    /** @test */
    public function it_classifies_commercial_buyer_from_commercial_property_type_tag(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => ['prefers-type:Commercial'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('generated', $result['status']);
        $this->assertSame('Commercial Buyer', $result['primary_avatar']);
    }

    /** @test */
    public function it_classifies_commercial_buyer_from_commercial_interest_deal_breaker_flag(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [
                ['flag' => 'commercial_interest', 'source_field' => 'property_type'],
            ],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Commercial Buyer', $result['primary_avatar']);
        $this->assertTrue($result['signals']['commercial_signal']);
    }

    /** @test */
    public function it_classifies_waterfront_buyer_from_waterfront_property_type_tag(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => ['prefers-type:Waterfront'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Waterfront Buyer', $result['primary_avatar']);
    }

    /** @test */
    public function it_classifies_waterfront_buyer_from_waterfront_required_deal_breaker_flag(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [
                ['flag' => 'waterfront_required', 'source_field' => 'view_preference'],
            ],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Waterfront Buyer', $result['primary_avatar']);
        $this->assertTrue($result['signals']['waterfront_signal']);
    }

    /** @test */
    public function it_classifies_investor_buyer_when_two_or_more_financing_signals_present(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => [
                'open-to:lease-option',
                'open-to:seller-financing',
            ],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Investor Buyer', $result['primary_avatar']);
    }

    /** @test */
    public function it_does_not_classify_investor_buyer_with_only_one_financing_signal(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => ['open-to:seller-financing'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertNotSame('Investor Buyer', $result['primary_avatar']);
    }

    /** @test */
    public function it_classifies_vacation_buyer_from_vacation_property_type_tag(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => ['prefers-type:Vacation'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Vacation Buyer', $result['primary_avatar']);
    }

    /** @test */
    public function it_classifies_downsizing_buyer_from_55_plus_community_tag(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => ['seeks:55-plus-community'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Downsizing Buyer', $result['primary_avatar']);
    }

    /** @test */
    public function it_classifies_luxury_buyer_when_pre_approved_with_high_budget(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => ['financial:pre-approved'],
            'deal_breaker_flags' => [
                ['flag' => 'budget_ceiling_specified', 'source_field' => 'maximum_budget', 'value' => '900000'],
            ],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Luxury Buyer', $result['primary_avatar']);
    }

    /** @test */
    public function it_classifies_move_up_buyer_when_pre_approved_with_pool_required(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => ['financial:pre-approved', 'requires:pool'],
            'deal_breaker_flags' => [
                ['flag' => 'pool_required', 'source_field' => 'pool_needed'],
                ['flag' => 'budget_ceiling_specified', 'source_field' => 'maximum_budget', 'value' => '400000'],
            ],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Move-Up Buyer', $result['primary_avatar']);
    }

    /** @test */
    public function it_classifies_budget_conscious_buyer_when_budget_set_not_pre_approved_and_has_financing_signal(): void
    {
        // Budget-Conscious requires ≥1 financing signal (distinguishes it from First-Time Buyer).
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => ['open-to:seller-financing'],
            'deal_breaker_flags' => [
                ['flag' => 'budget_ceiling_specified', 'source_field' => 'maximum_budget', 'value' => '250000'],
            ],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Budget-Conscious Buyer', $result['primary_avatar']);
    }

    /** @test */
    public function it_classifies_first_time_buyer_when_budget_set_not_pre_approved_and_no_financing_signals(): void
    {
        // First-Time Buyer = budget only + no pre-approval + no financing research (truly new).
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [
                ['flag' => 'budget_ceiling_specified', 'source_field' => 'maximum_budget', 'value' => '250000'],
            ],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('First-Time Buyer', $result['primary_avatar']);
    }

    /** @test */
    public function it_assigns_flexible_buyer_when_pre_approved_but_no_budget_or_amenity_signals(): void
    {
        // pre_approved=true fires no specific rule without a budget or amenity requirement.
        // Falls through all rules into the Flexible Buyer fallback.
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => ['financial:pre-approved'],
            'deal_breaker_flags' => [],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Flexible Buyer', $result['primary_avatar']);
    }

    /** @test */
    public function it_assigns_flexible_buyer_as_fallback_when_signals_present_but_no_rule_matches(): void
    {
        // garage_required alone fires no classification rule → Flexible Buyer.
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => ['requires:garage'],
            'deal_breaker_flags' => [
                ['flag' => 'garage_required', 'source_field' => 'garage_needed'],
            ],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Flexible Buyer', $result['primary_avatar']);
    }

    /** @test */
    public function it_assigns_unknown_buyer_when_no_signals_are_present(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Unknown Buyer', $result['primary_avatar']);
    }

    /** @test */
    public function it_assigns_secondary_avatars_when_multiple_rules_match(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => [
                'prefers-type:Waterfront',
                'open-to:lease-option',
                'open-to:seller-financing',
                'open-to:assumable-loan',
            ],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Waterfront Buyer', $result['primary_avatar']);
        $this->assertContains('Investor Buyer', $result['secondary_avatars']);
    }

    /** @test */
    public function commercial_buyer_takes_precedence_over_other_signals(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => [
                'prefers-type:Commercial',
                'prefers-type:Waterfront',
                'open-to:lease-option',
                'open-to:seller-financing',
            ],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Commercial Buyer', $result['primary_avatar']);
        $this->assertContains('Waterfront Buyer', $result['secondary_avatars']);
        $this->assertContains('Investor Buyer', $result['secondary_avatars']);
    }

    // -------------------------------------------------------------------------
    // (d) missing_inputs populated correctly
    // -------------------------------------------------------------------------

    /** @test */
    public function missing_inputs_is_populated_for_generated_result_with_absent_signals(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [],
        ]);

        $result = $this->service->generate($profile);

        $this->assertNotEmpty($result['missing_inputs']);
        $this->assertIsArray($result['missing_inputs']);
    }

    /** @test */
    public function missing_inputs_includes_budget_ceiling_when_not_specified(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => ['financial:pre-approved'],
            'deal_breaker_flags' => [],
        ]);

        $result = $this->service->generate($profile);

        $this->assertContains('Budget ceiling', $result['missing_inputs']);
    }

    /** @test */
    public function missing_inputs_includes_property_type_when_no_prefers_type_tag(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => ['financial:pre-approved'],
            'deal_breaker_flags' => [],
        ]);

        $result = $this->service->generate($profile);

        $this->assertContains('Property type preference', $result['missing_inputs']);
    }

    /** @test */
    public function missing_inputs_does_not_include_budget_when_budget_flag_is_present(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [
                ['flag' => 'budget_ceiling_specified', 'source_field' => 'maximum_budget', 'value' => '350000'],
            ],
        ]);

        $result = $this->service->generate($profile);

        $this->assertNotContains('Budget ceiling', $result['missing_inputs']);
    }

    /** @test */
    public function missing_inputs_does_not_include_pre_approval_when_pre_approved_tag_present(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => ['financial:pre-approved'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertNotContains('Pre-approval status', $result['missing_inputs']);
    }

    // -------------------------------------------------------------------------
    // (e) Contract consistency — listing_type is always 'buyer' in every path
    // -------------------------------------------------------------------------

    /** @test */
    public function it_always_returns_listing_type_buyer_in_contract_even_for_non_buyer_guard(): void
    {
        $profile = $this->makeBaseProfile(['listing_type' => 'tenant']);

        $result = $this->service->generate($profile);

        $this->assertSame('buyer', $result['listing_type'],
            'listing_type must be buyer in all output paths — service is buyer-only');
    }

    /** @test */
    public function it_always_returns_listing_type_buyer_in_contract_for_insufficient_completeness(): void
    {
        $profile = $this->makeBaseProfile(['preference_completeness' => 5.0]);

        $result = $this->service->generate($profile);

        $this->assertSame('buyer', $result['listing_type']);
    }

    /** @test */
    public function it_surfaces_listing_id_correctly_in_all_results(): void
    {
        $profile = $this->makeProfile([
            'listing_type'            => 'buyer',
            'listing_id'              => 99,
            'preference_completeness' => 50.0,
            'lifestyle_tags'          => [],
            'deal_breaker_flags'      => [],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('buyer', $result['listing_type']);
        $this->assertSame(99, $result['listing_id']);
    }

    // -------------------------------------------------------------------------
    // (f) No AI/OpenAI imports — service is deterministic only
    // -------------------------------------------------------------------------

    /** @test */
    public function service_file_contains_no_openai_or_ai_imports(): void
    {
        $serviceFile = file_get_contents(
            dirname(__DIR__, 4) . '/app/Services/Dna/BuyerAvatarService.php'
        );

        $this->assertStringNotContainsStringIgnoringCase('openai', $serviceFile,
            'BuyerAvatarService must not import or reference OpenAI');
        $this->assertStringNotContainsStringIgnoringCase('use OpenAI', $serviceFile,
            'BuyerAvatarService must not import OpenAI SDK');
        $this->assertStringNotContainsStringIgnoringCase('AiMarketing', $serviceFile,
            'BuyerAvatarService must not import AI marketing services');
    }

    /** @test */
    public function service_constructs_without_any_database_interaction(): void
    {
        $service = new BuyerAvatarService();

        $this->assertInstanceOf(BuyerAvatarService::class, $service);
    }

    /** @test */
    public function signals_array_in_output_contains_expected_keys(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => ['financial:pre-approved'],
            'deal_breaker_flags' => [],
        ]);

        $result  = $this->service->generate($profile);
        $signals = $result['signals'];

        $this->assertArrayHasKey('commercial_signal',        $signals);
        $this->assertArrayHasKey('waterfront_signal',        $signals);
        $this->assertArrayHasKey('vacation_signal',          $signals);
        $this->assertArrayHasKey('pre_approved',             $signals);
        $this->assertArrayHasKey('budget_ceiling_specified', $signals);
        $this->assertArrayHasKey('pool_required',            $signals);
        $this->assertArrayHasKey('garage_required',          $signals);
        $this->assertArrayHasKey('open_to_seller_financing', $signals);
        $this->assertArrayHasKey('open_to_assumable_loan',   $signals);
        $this->assertArrayHasKey('open_to_lease_option',     $signals);
        $this->assertArrayHasKey('open_to_lease_purchase',   $signals);
        $this->assertArrayNotHasKey('timeline_specified',    $signals,
            'timeline_specified must not appear in V1 signals — no explicit source in profile');
    }

    /** @test */
    public function investor_buyer_classification_fires_with_all_four_financing_signals(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => [
                'open-to:lease-option',
                'open-to:lease-purchase',
                'open-to:seller-financing',
                'open-to:assumable-loan',
            ],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Investor Buyer', $result['primary_avatar']);
        $this->assertTrue($result['signals']['open_to_lease_option']);
        $this->assertTrue($result['signals']['open_to_lease_purchase']);
        $this->assertTrue($result['signals']['open_to_seller_financing']);
        $this->assertTrue($result['signals']['open_to_assumable_loan']);
    }

    /** @test */
    public function it_correctly_detects_pre_approved_signal_from_lifestyle_tag(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => ['financial:pre-approved'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertTrue($result['signals']['pre_approved']);
    }

    /** @test */
    public function it_correctly_extracts_budget_value_from_deal_breaker_flag(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [
                ['flag' => 'budget_ceiling_specified', 'source_field' => 'maximum_budget', 'value' => '500000'],
            ],
        ]);

        $result = $this->service->generate($profile);

        $this->assertTrue($result['signals']['budget_ceiling_specified']);
        $this->assertSame(500000.0, $result['signals']['budget_value']);
    }

    /** @test */
    public function luxury_threshold_does_not_trigger_below_750000(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => ['financial:pre-approved'],
            'deal_breaker_flags' => [
                ['flag' => 'budget_ceiling_specified', 'source_field' => 'maximum_budget', 'value' => '700000'],
            ],
        ]);

        $result = $this->service->generate($profile);

        $this->assertNotSame('Luxury Buyer', $result['primary_avatar']);
    }

    // -------------------------------------------------------------------------
    // (g) New output keys — motivations, narrative, preference summary,
    //     personality tags, match preferences, confidence score,
    //     readiness score, avatar version
    // -------------------------------------------------------------------------

    /** @test */
    public function new_output_keys_are_present_for_generated_path(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => ['prefers-type:SingleFamily'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('generated', $result['status']);
        $this->assertArrayHasKey('primary_motivation',       $result);
        $this->assertArrayHasKey('secondary_motivation',     $result);
        $this->assertArrayHasKey('buyer_narrative',          $result);
        $this->assertArrayHasKey('buyer_preference_summary', $result);
        $this->assertArrayHasKey('buyer_personality_tags',   $result);
        $this->assertArrayHasKey('buyer_match_preferences',  $result);
        $this->assertArrayHasKey('avatar_confidence_score',  $result);
        $this->assertArrayHasKey('buyer_readiness_score',    $result);
        $this->assertArrayHasKey('buyer_avatar_version',     $result);
    }

    /** @test */
    public function new_output_keys_are_present_for_insufficient_data_path(): void
    {
        $profile = $this->makeBaseProfile(['preference_completeness' => 5.0]);

        $result = $this->service->generate($profile);

        $this->assertSame('insufficient_data', $result['status']);
        $this->assertArrayHasKey('primary_motivation',       $result);
        $this->assertArrayHasKey('secondary_motivation',     $result);
        $this->assertArrayHasKey('buyer_narrative',          $result);
        $this->assertArrayHasKey('buyer_preference_summary', $result);
        $this->assertArrayHasKey('buyer_personality_tags',   $result);
        $this->assertArrayHasKey('buyer_match_preferences',  $result);
        $this->assertArrayHasKey('avatar_confidence_score',  $result);
        $this->assertArrayHasKey('buyer_readiness_score',    $result);
        $this->assertArrayHasKey('buyer_avatar_version',     $result);
    }

    /** @test */
    public function correct_motivations_for_luxury_buyer(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => ['financial:pre-approved'],
            'deal_breaker_flags' => [
                ['flag' => 'budget_ceiling_specified', 'source_field' => 'maximum_budget', 'value' => '900000'],
            ],
        ]);
        $result = $this->service->generate($profile);
        $this->assertSame('Luxury Buyer', $result['primary_avatar']);
        $this->assertSame('Lifestyle Upgrade', $result['primary_motivation']);
        $this->assertSame('Investment', $result['secondary_motivation']);
    }

    /** @test */
    public function correct_motivations_for_investor_buyer(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => ['open-to:lease-option', 'open-to:seller-financing'],
        ]);
        $result = $this->service->generate($profile);
        $this->assertSame('Investor Buyer', $result['primary_avatar']);
        $this->assertSame('Investment', $result['primary_motivation']);
        $this->assertSame('Cash Flow', $result['secondary_motivation']);
    }

    /** @test */
    public function correct_motivations_for_vacation_buyer(): void
    {
        $profile = $this->makeBaseProfile(['lifestyle_tags' => ['prefers-type:Vacation']]);
        $result = $this->service->generate($profile);
        $this->assertSame('Vacation Buyer', $result['primary_avatar']);
        $this->assertSame('Lifestyle Upgrade', $result['primary_motivation']);
        $this->assertSame('Retirement Planning', $result['secondary_motivation']);
    }

    /** @test */
    public function correct_motivations_for_move_up_buyer(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => ['financial:pre-approved', 'requires:pool'],
            'deal_breaker_flags' => [
                ['flag' => 'pool_required', 'source_field' => 'pool_needed'],
                ['flag' => 'budget_ceiling_specified', 'source_field' => 'maximum_budget', 'value' => '400000'],
            ],
        ]);
        $result = $this->service->generate($profile);
        $this->assertSame('Move-Up Buyer', $result['primary_avatar']);
        $this->assertSame('Family Growth', $result['primary_motivation']);
        $this->assertSame('Lifestyle Upgrade', $result['secondary_motivation']);
    }

    /** @test */
    public function correct_motivations_for_first_time_buyer(): void
    {
        // First-Time Buyer: budget set + not pre-approved + no financing signals.
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [
                ['flag' => 'budget_ceiling_specified', 'source_field' => 'maximum_budget', 'value' => '200000'],
            ],
        ]);
        $result = $this->service->generate($profile);
        $this->assertSame('First-Time Buyer', $result['primary_avatar']);
        $this->assertSame('Stability', $result['primary_motivation']);
        $this->assertSame('Family Growth', $result['secondary_motivation']);
    }

    /** @test */
    public function correct_motivations_for_budget_conscious_buyer(): void
    {
        // Budget-Conscious requires at least one financing signal alongside the budget.
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => ['open-to:seller-financing'],
            'deal_breaker_flags' => [
                ['flag' => 'budget_ceiling_specified', 'source_field' => 'maximum_budget', 'value' => '200000'],
            ],
        ]);
        $result = $this->service->generate($profile);
        $this->assertSame('Budget-Conscious Buyer', $result['primary_avatar']);
        $this->assertSame('Stability', $result['primary_motivation']);
        $this->assertSame('Relocation', $result['secondary_motivation']);
    }

    /** @test */
    public function correct_motivations_for_commercial_buyer(): void
    {
        $profile = $this->makeBaseProfile(['lifestyle_tags' => ['prefers-type:Commercial']]);
        $result = $this->service->generate($profile);
        $this->assertSame('Commercial Buyer', $result['primary_avatar']);
        $this->assertSame('Investment', $result['primary_motivation']);
        $this->assertSame('Business Expansion', $result['secondary_motivation']);
    }

    /** @test */
    public function correct_motivations_for_waterfront_buyer(): void
    {
        $profile = $this->makeBaseProfile(['lifestyle_tags' => ['prefers-type:Waterfront']]);
        $result = $this->service->generate($profile);
        $this->assertSame('Waterfront Buyer', $result['primary_avatar']);
        $this->assertSame('Lifestyle Upgrade', $result['primary_motivation']);
        $this->assertSame('Appreciation', $result['secondary_motivation']);
    }

    /** @test */
    public function correct_motivations_for_downsizing_buyer(): void
    {
        $profile = $this->makeBaseProfile(['lifestyle_tags' => ['seeks:55-plus-community']]);
        $result = $this->service->generate($profile);
        $this->assertSame('Downsizing Buyer', $result['primary_avatar']);
        $this->assertSame('Retirement Planning', $result['primary_motivation']);
        $this->assertSame('Lifestyle Upgrade', $result['secondary_motivation']);
    }

    /** @test */
    public function correct_motivations_for_flexible_buyer(): void
    {
        $profile = $this->makeBaseProfile(['lifestyle_tags' => ['financial:pre-approved']]);
        $result = $this->service->generate($profile);
        $this->assertSame('Flexible Buyer', $result['primary_avatar']);
        $this->assertSame('Relocation', $result['primary_motivation']);
        $this->assertSame('Lifestyle Upgrade', $result['secondary_motivation']);
    }

    /** @test */
    public function unknown_buyer_returns_null_for_both_motivation_fields(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [],
        ]);
        $result = $this->service->generate($profile);
        $this->assertSame('Unknown Buyer', $result['primary_avatar']);
        $this->assertNull($result['primary_motivation']);
        $this->assertNull($result['secondary_motivation']);
    }

    /** @test */
    public function buyer_narrative_is_non_empty_string_for_all_types_except_unknown(): void
    {
        $profiles = [
            'Luxury Buyer'           => $this->makeBaseProfile(['lifestyle_tags' => ['financial:pre-approved'], 'deal_breaker_flags' => [['flag' => 'budget_ceiling_specified', 'source_field' => 'maximum_budget', 'value' => '900000']]]),
            'Investor Buyer'         => $this->makeBaseProfile(['lifestyle_tags' => ['open-to:lease-option', 'open-to:seller-financing']]),
            'Vacation Buyer'         => $this->makeBaseProfile(['lifestyle_tags' => ['prefers-type:Vacation']]),
            'Downsizing Buyer'       => $this->makeBaseProfile(['lifestyle_tags' => ['seeks:55-plus-community']]),
            'Waterfront Buyer'       => $this->makeBaseProfile(['lifestyle_tags' => ['prefers-type:Waterfront']]),
            'Commercial Buyer'       => $this->makeBaseProfile(['lifestyle_tags' => ['prefers-type:Commercial']]),
            'Flexible Buyer'         => $this->makeBaseProfile(['lifestyle_tags' => ['financial:pre-approved']]),
            // Budget-Conscious requires ≥1 financing signal.
            'Budget-Conscious Buyer' => $this->makeBaseProfile(['lifestyle_tags' => ['open-to:seller-financing'], 'deal_breaker_flags' => [['flag' => 'budget_ceiling_specified', 'source_field' => 'maximum_budget', 'value' => '250000']]]),
            // First-Time Buyer: budget only, no pre-approval, no financing signals.
            'First-Time Buyer'       => $this->makeBaseProfile(['deal_breaker_flags' => [['flag' => 'budget_ceiling_specified', 'source_field' => 'maximum_budget', 'value' => '180000']]]),
        ];

        foreach ($profiles as $avatarType => $profile) {
            $result = $this->service->generate($profile);
            $this->assertSame($avatarType, $result['primary_avatar'], "Expected {$avatarType}");
            $this->assertIsString($result['buyer_narrative'],
                "buyer_narrative must be a non-empty string for {$avatarType}");
            $this->assertNotEmpty($result['buyer_narrative'],
                "buyer_narrative must not be empty for {$avatarType}");
        }
    }

    /** @test */
    public function buyer_narrative_is_null_for_unknown_buyer(): void
    {
        $profile = $this->makeBaseProfile(['lifestyle_tags' => [], 'deal_breaker_flags' => []]);
        $result = $this->service->generate($profile);
        $this->assertSame('Unknown Buyer', $result['primary_avatar']);
        $this->assertNull($result['buyer_narrative']);
    }

    /** @test */
    public function buyer_preference_summary_is_structured_with_four_groups(): void
    {
        $profiles = [
            $this->makeBaseProfile(['lifestyle_tags' => [], 'deal_breaker_flags' => []]),
            $this->makeBaseProfile(['lifestyle_tags' => ['financial:pre-approved']]),
            $this->makeBaseProfile(['lifestyle_tags' => ['prefers-type:Waterfront']]),
        ];

        foreach ($profiles as $profile) {
            $result  = $this->service->generate($profile);
            $summary = $result['buyer_preference_summary'];

            $this->assertIsArray($summary);
            $this->assertArrayHasKey('property_types',    $summary);
            $this->assertArrayHasKey('amenities',         $summary);
            $this->assertArrayHasKey('budget_signals',    $summary);
            $this->assertArrayHasKey('financing_signals', $summary);
            $this->assertIsArray($summary['property_types']);
            $this->assertIsArray($summary['amenities']);
            $this->assertIsArray($summary['budget_signals']);
            $this->assertIsArray($summary['financing_signals']);
        }
    }

    /** @test */
    public function buyer_preference_summary_insufficient_data_path_returns_four_empty_groups(): void
    {
        $profile = $this->makeBaseProfile(['preference_completeness' => 5.0]);
        $result  = $this->service->generate($profile);
        $summary = $result['buyer_preference_summary'];

        $this->assertSame([], $summary['property_types']);
        $this->assertSame([], $summary['amenities']);
        $this->assertSame([], $summary['budget_signals']);
        $this->assertSame([], $summary['financing_signals']);
    }

    /** @test */
    public function buyer_personality_tags_is_always_an_array(): void
    {
        $profiles = [
            $this->makeBaseProfile(['lifestyle_tags' => [], 'deal_breaker_flags' => []]),
            $this->makeBaseProfile(['lifestyle_tags' => ['financial:pre-approved']]),
            $this->makeBaseProfile(['lifestyle_tags' => ['prefers-type:Commercial']]),
        ];

        foreach ($profiles as $profile) {
            $result = $this->service->generate($profile);
            $this->assertIsArray($result['buyer_personality_tags']);
        }
    }

    /** @test */
    public function buyer_match_preferences_is_always_an_array(): void
    {
        $profiles = [
            $this->makeBaseProfile(['lifestyle_tags' => [], 'deal_breaker_flags' => []]),
            $this->makeBaseProfile(['lifestyle_tags' => ['requires:pool', 'financial:pre-approved']]),
        ];

        foreach ($profiles as $profile) {
            $result = $this->service->generate($profile);
            $this->assertIsArray($result['buyer_match_preferences']);
        }
    }

    /** @test */
    public function avatar_confidence_score_is_integer_between_0_and_100(): void
    {
        $profiles = [
            $this->makeBaseProfile(['lifestyle_tags' => [], 'deal_breaker_flags' => []]),
            $this->makeBaseProfile(['lifestyle_tags' => ['financial:pre-approved']]),
            $this->makeBaseProfile(['lifestyle_tags' => ['prefers-type:Waterfront'], 'preference_completeness' => 100.0]),
        ];

        foreach ($profiles as $profile) {
            $result = $this->service->generate($profile);
            $score = $result['avatar_confidence_score'];
            $this->assertIsInt($score);
            $this->assertGreaterThanOrEqual(0, $score);
            $this->assertLessThanOrEqual(100, $score);
        }
    }

    /** @test */
    public function buyer_readiness_score_is_integer_between_0_and_100(): void
    {
        $profiles = [
            $this->makeBaseProfile(['lifestyle_tags' => [], 'deal_breaker_flags' => []]),
            $this->makeBaseProfile(['lifestyle_tags' => ['financial:pre-approved']]),
        ];

        foreach ($profiles as $profile) {
            $result = $this->service->generate($profile);
            $score = $result['buyer_readiness_score'];
            $this->assertIsInt($score);
            $this->assertGreaterThanOrEqual(0, $score);
            $this->assertLessThanOrEqual(100, $score);
        }
    }

    /** @test */
    public function unknown_buyer_confidence_score_is_at_most_20(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'          => [],
            'deal_breaker_flags'      => [],
            'preference_completeness' => 100.0,
        ]);
        $result = $this->service->generate($profile);
        $this->assertSame('Unknown Buyer', $result['primary_avatar']);
        $this->assertLessThanOrEqual(20, $result['avatar_confidence_score']);
    }

    /** @test */
    public function flexible_buyer_confidence_score_is_at_most_60(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'          => ['financial:pre-approved'],
            'preference_completeness' => 100.0,
        ]);
        $result = $this->service->generate($profile);
        $this->assertSame('Flexible Buyer', $result['primary_avatar']);
        $this->assertLessThanOrEqual(60, $result['avatar_confidence_score']);
    }

    /** @test */
    public function buyer_readiness_score_is_zero_when_no_readiness_signals_present(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [],
        ]);
        $result = $this->service->generate($profile);
        $this->assertSame(0, $result['buyer_readiness_score']);
    }

    /** @test */
    public function buyer_readiness_score_is_at_least_30_when_pre_approval_is_present(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => ['financial:pre-approved'],
        ]);
        $result = $this->service->generate($profile);
        $this->assertGreaterThanOrEqual(30, $result['buyer_readiness_score']);
    }

    /** @test */
    public function buyer_avatar_version_is_always_buyer_avatar_v1(): void
    {
        $profiles = [
            $this->makeBaseProfile(['lifestyle_tags' => [], 'deal_breaker_flags' => []]),
            $this->makeBaseProfile(['lifestyle_tags' => ['financial:pre-approved']]),
            $this->makeBaseProfile(['lifestyle_tags' => ['prefers-type:Commercial']]),
            $this->makeBaseProfile(['preference_completeness' => 5.0]),
        ];

        foreach ($profiles as $profile) {
            $result = $this->service->generate($profile);
            $this->assertSame('BUYER_AVATAR_V1', $result['buyer_avatar_version'],
                'buyer_avatar_version must always be BUYER_AVATAR_V1');
        }
    }

    /** @test */
    public function buyer_avatar_version_is_buyer_avatar_v1_for_insufficient_data(): void
    {
        $profile = $this->makeBaseProfile(['preference_completeness' => 5.0]);
        $result = $this->service->generate($profile);
        $this->assertSame('insufficient_data', $result['status']);
        $this->assertSame('BUYER_AVATAR_V1', $result['buyer_avatar_version']);
    }

    /** @test */
    public function match_preferences_contains_pool_when_pool_required_signal_set(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => ['requires:pool'],
            'deal_breaker_flags' => [['flag' => 'pool_required', 'source_field' => 'pool_needed']],
        ]);
        $result = $this->service->generate($profile);
        $this->assertContains('Pool', $result['buyer_match_preferences']);
    }

    /** @test */
    public function match_preferences_contains_waterfront_when_waterfront_signal_set(): void
    {
        $profile = $this->makeBaseProfile(['lifestyle_tags' => ['prefers-type:Waterfront']]);
        $result = $this->service->generate($profile);
        $this->assertContains('Waterfront', $result['buyer_match_preferences']);
    }

    /** @test */
    public function preference_summary_budget_signals_contains_pre_approved_when_signal_set(): void
    {
        $profile = $this->makeBaseProfile(['lifestyle_tags' => ['financial:pre-approved']]);
        $result  = $this->service->generate($profile);
        $this->assertContains('Pre-Approved', $result['buyer_preference_summary']['budget_signals']);
    }

    /** @test */
    public function preference_summary_financing_signals_contains_seller_financing_when_signal_set(): void
    {
        $profile = $this->makeBaseProfile(['lifestyle_tags' => ['open-to:seller-financing']]);
        $result  = $this->service->generate($profile);
        $this->assertContains('Seller Financing', $result['buyer_preference_summary']['financing_signals']);
    }

    /** @test */
    public function preference_summary_property_types_contains_waterfront_when_signal_set(): void
    {
        $profile = $this->makeBaseProfile(['lifestyle_tags' => ['prefers-type:Waterfront']]);
        $result  = $this->service->generate($profile);
        $this->assertContains('Waterfront', $result['buyer_preference_summary']['property_types']);
    }

    /** @test */
    public function preference_summary_amenities_contains_pool_when_signal_set(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => ['requires:pool'],
            'deal_breaker_flags' => [['flag' => 'pool_required', 'source_field' => 'pool_needed']],
        ]);
        $result = $this->service->generate($profile);
        $this->assertContains('Pool', $result['buyer_preference_summary']['amenities']);
    }

    // -------------------------------------------------------------------------
    // (h) BuyerAvatarProfileService — buyer-only guard
    // -------------------------------------------------------------------------

    /** @test */
    public function buyer_avatar_profile_service_does_not_call_avatar_service_for_non_buyer(): void
    {
        /** @var BuyerAvatarService|MockObject $mockAvatarService */
        $mockAvatarService = $this->createMock(BuyerAvatarService::class);

        $mockAvatarService->expects($this->never())
            ->method('generate');

        $orchestrator = new BuyerAvatarProfileService($mockAvatarService);

        $tenantProfile = new BuyerTenantDnaProfile();
        $tenantProfile->listing_type = 'tenant';
        $tenantProfile->listing_id   = 1;

        $orchestrator->compute($tenantProfile);
    }

    /** @test */
    public function buyer_avatar_profile_service_silently_noops_for_non_buyer_profile(): void
    {
        $mockAvatarService = $this->createMock(BuyerAvatarService::class);
        $mockAvatarService->expects($this->never())->method('generate');

        $service = new BuyerAvatarProfileService($mockAvatarService);

        $profile = new BuyerTenantDnaProfile();
        $profile->listing_type = 'seller';

        $service->compute($profile);
    }
}
