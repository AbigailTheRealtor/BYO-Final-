<?php

namespace Tests\Unit\Services\Dna;

use App\Models\BuyerTenantDnaProfile;
use App\Services\Dna\TenantAvatarService;
use PHPUnit\Framework\TestCase;

/**
 * TenantAvatarServiceTest
 *
 * Verifies the TenantAvatarService deterministic classification layer against
 * in-memory BuyerTenantDnaProfile stubs. No database connection is required —
 * all test data is constructed inline using PHPUnit\Framework\TestCase only.
 *
 * Each test asserts one or more of:
 *   (a) Guard conditions — wrong listing_type or low completeness returns insufficient_data
 *   (b) Output contract shape — all required keys present in every result
 *   (c) Avatar classification — correct primary and secondary avatars for given signals
 *   (d) missing_inputs — populated correctly from absent signal dimensions
 *   (e) Contract consistency — listing_type is always 'tenant' in every output path
 *   (f) No AI/OpenAI imports — service is deterministic only
 */
class TenantAvatarServiceTest extends TestCase
{
    private TenantAvatarService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TenantAvatarService();
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
     * Build a profile for a 'tenant' listing type with sane defaults.
     */
    private function makeBaseProfile(array $overrides = []): BuyerTenantDnaProfile
    {
        return $this->makeProfile(array_merge([
            'listing_type'            => 'tenant',
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
    public function it_returns_insufficient_data_when_listing_type_is_not_tenant(): void
    {
        $profile = $this->makeBaseProfile(['listing_type' => 'buyer']);

        $result = $this->service->generate($profile);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_data', $result['status']);
        $this->assertNull($result['primary_avatar']);
        $this->assertSame([], $result['secondary_avatars']);
    }

    /** @test */
    public function it_returns_insufficient_data_when_listing_type_is_seller(): void
    {
        $profile = $this->makeBaseProfile(['listing_type' => 'seller']);

        $result = $this->service->generate($profile);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_data', $result['status']);
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

        $this->assertArrayHasKey('success',           $result);
        $this->assertArrayHasKey('status',            $result);
        $this->assertArrayHasKey('listing_type',      $result);
        $this->assertArrayHasKey('listing_id',        $result);
        $this->assertArrayHasKey('primary_avatar',    $result);
        $this->assertArrayHasKey('secondary_avatars', $result);
        $this->assertArrayHasKey('signals',           $result);
        $this->assertArrayHasKey('missing_inputs',    $result);
        $this->assertArrayHasKey('error',             $result);
    }

    /** @test */
    public function output_contract_shape_is_consistent_for_wrong_listing_type(): void
    {
        $profile = $this->makeBaseProfile(['listing_type' => 'buyer']);

        $result = $this->service->generate($profile);

        $this->assertArrayHasKey('success',           $result);
        $this->assertArrayHasKey('status',            $result);
        $this->assertArrayHasKey('listing_type',      $result);
        $this->assertArrayHasKey('listing_id',        $result);
        $this->assertArrayHasKey('primary_avatar',    $result);
        $this->assertArrayHasKey('secondary_avatars', $result);
        $this->assertArrayHasKey('signals',           $result);
        $this->assertArrayHasKey('missing_inputs',    $result);
        $this->assertArrayHasKey('error',             $result);
    }

    /** @test */
    public function output_contract_shape_is_consistent_for_generated_result(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => ['prefers-type:Apartment'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertArrayHasKey('success',           $result);
        $this->assertArrayHasKey('status',            $result);
        $this->assertArrayHasKey('listing_type',      $result);
        $this->assertArrayHasKey('listing_id',        $result);
        $this->assertArrayHasKey('primary_avatar',    $result);
        $this->assertArrayHasKey('secondary_avatars', $result);
        $this->assertArrayHasKey('signals',           $result);
        $this->assertArrayHasKey('missing_inputs',    $result);
        $this->assertArrayHasKey('error',             $result);

        $this->assertTrue($result['success']);
        $this->assertSame('generated', $result['status']);
        $this->assertSame('tenant', $result['listing_type']);
        $this->assertSame(42, $result['listing_id']);
        $this->assertIsArray($result['secondary_avatars']);
        $this->assertIsArray($result['signals']);
        $this->assertIsArray($result['missing_inputs']);
        $this->assertNull($result['error']);
    }

    // -------------------------------------------------------------------------
    // (c) Avatar classification — all 8 types
    // -------------------------------------------------------------------------

    /** @test */
    public function it_classifies_commercial_tenant_from_commercial_property_type_tag(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => ['prefers-type:Commercial'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('generated', $result['status']);
        $this->assertSame('Commercial Tenant', $result['primary_avatar']);
        $this->assertTrue($result['signals']['commercial_signal']);
    }

    /** @test */
    public function it_classifies_commercial_tenant_from_commercial_interest_deal_breaker_flag(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [
                ['flag' => 'commercial_interest', 'source_field' => 'property_type'],
            ],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Commercial Tenant', $result['primary_avatar']);
        $this->assertTrue($result['signals']['commercial_signal']);
    }

    /** @test */
    public function it_classifies_lease_option_tenant_from_open_to_lease_option_tag(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => ['open-to:lease-option'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Lease-Option Tenant', $result['primary_avatar']);
        $this->assertTrue($result['signals']['lease_option_signal']);
    }

    /** @test */
    public function it_classifies_pet_conscious_tenant_from_has_pets_tag(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => ['has-pets'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Pet-Conscious Tenant', $result['primary_avatar']);
        $this->assertTrue($result['signals']['has_pets']);
    }

    /** @test */
    public function it_classifies_amenity_focused_tenant_from_pool_required_tag(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => ['requires:pool'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Amenity-Focused Tenant', $result['primary_avatar']);
        $this->assertTrue($result['signals']['pool_required']);
    }

    /** @test */
    public function it_classifies_amenity_focused_tenant_from_garage_required_tag(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => ['requires:garage'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Amenity-Focused Tenant', $result['primary_avatar']);
        $this->assertTrue($result['signals']['garage_required']);
    }

    /** @test */
    public function it_classifies_amenity_focused_tenant_from_pool_required_deal_breaker_flag(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [
                ['flag' => 'pool_required', 'source_field' => 'pool_needed'],
            ],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Amenity-Focused Tenant', $result['primary_avatar']);
        $this->assertTrue($result['signals']['pool_required']);
    }

    /** @test */
    public function it_classifies_amenity_focused_tenant_from_garage_required_deal_breaker_flag(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [
                ['flag' => 'garage_required', 'source_field' => 'garage_needed'],
            ],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Amenity-Focused Tenant', $result['primary_avatar']);
        $this->assertTrue($result['signals']['garage_required']);
    }

    /** @test */
    public function it_classifies_space_focused_tenant_from_min_bedrooms_tag(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => ['min-bedrooms:3'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Space-Focused Tenant', $result['primary_avatar']);
        $this->assertTrue($result['signals']['space_requirement']);
    }

    /** @test */
    public function it_classifies_space_focused_tenant_from_min_sqft_tag(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => ['min-sqft:1200'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Space-Focused Tenant', $result['primary_avatar']);
        $this->assertTrue($result['signals']['space_requirement']);
    }

    /** @test */
    public function it_classifies_space_focused_tenant_from_min_bathrooms_tag(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => ['min-bathrooms:2'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Space-Focused Tenant', $result['primary_avatar']);
        $this->assertTrue($result['signals']['space_requirement']);
    }

    /** @test */
    public function it_classifies_space_focused_tenant_from_space_requirement_deal_breaker_flag(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [
                ['flag' => 'space_requirement', 'source_field' => 'min_sqft'],
            ],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Space-Focused Tenant', $result['primary_avatar']);
        $this->assertTrue($result['signals']['space_requirement']);
    }

    /** @test */
    public function it_classifies_budget_conscious_tenant_when_budget_ceiling_specified(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [
                ['flag' => 'budget_ceiling_specified', 'source_field' => 'max_rent', 'value' => '2500'],
            ],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Budget-Conscious Tenant', $result['primary_avatar']);
        $this->assertTrue($result['signals']['budget_ceiling_specified']);
    }

    /** @test */
    public function it_assigns_flexible_tenant_when_signals_present_but_no_rule_matches(): void
    {
        // has_property_type=true fires no specific classification rule → Flexible Tenant.
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => ['prefers-type:SingleFamily'],
            'deal_breaker_flags' => [],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Flexible Tenant', $result['primary_avatar']);
    }

    /** @test */
    public function it_assigns_unknown_tenant_when_no_signals_are_present(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Unknown Tenant', $result['primary_avatar']);
    }

    /** @test */
    public function it_assigns_secondary_avatars_when_multiple_rules_match(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => ['has-pets', 'requires:pool'],
            'deal_breaker_flags' => [
                ['flag' => 'budget_ceiling_specified', 'source_field' => 'max_rent', 'value' => '2000'],
            ],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Pet-Conscious Tenant', $result['primary_avatar']);
        $this->assertContains('Amenity-Focused Tenant', $result['secondary_avatars']);
        $this->assertContains('Budget-Conscious Tenant', $result['secondary_avatars']);
    }

    /** @test */
    public function commercial_tenant_takes_precedence_over_other_signals(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => [
                'prefers-type:Commercial',
                'open-to:lease-option',
                'has-pets',
                'requires:pool',
            ],
            'deal_breaker_flags' => [
                ['flag' => 'budget_ceiling_specified', 'source_field' => 'max_rent', 'value' => '3000'],
            ],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Commercial Tenant', $result['primary_avatar']);
        $this->assertContains('Lease-Option Tenant',   $result['secondary_avatars']);
        $this->assertContains('Pet-Conscious Tenant',  $result['secondary_avatars']);
        $this->assertContains('Amenity-Focused Tenant',$result['secondary_avatars']);
        $this->assertContains('Budget-Conscious Tenant',$result['secondary_avatars']);
    }

    /** @test */
    public function lease_option_takes_precedence_over_pet_conscious_and_below(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => [
                'open-to:lease-option',
                'has-pets',
            ],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Lease-Option Tenant', $result['primary_avatar']);
        $this->assertContains('Pet-Conscious Tenant', $result['secondary_avatars']);
    }

    // -------------------------------------------------------------------------
    // (d) missing_inputs populated correctly
    // -------------------------------------------------------------------------

    /** @test */
    public function missing_inputs_is_populated_for_generated_result_with_no_signals(): void
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
            'lifestyle_tags'     => ['has-pets'],
            'deal_breaker_flags' => [],
        ]);

        $result = $this->service->generate($profile);

        $this->assertContains('Budget ceiling', $result['missing_inputs']);
    }

    /** @test */
    public function missing_inputs_includes_property_type_when_no_prefers_type_tag(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => ['has-pets'],
            'deal_breaker_flags' => [],
        ]);

        $result = $this->service->generate($profile);

        $this->assertContains('Property type preference', $result['missing_inputs']);
    }

    /** @test */
    public function missing_inputs_includes_pet_status_when_has_pets_tag_absent(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [],
        ]);

        $result = $this->service->generate($profile);

        $this->assertContains('Pet status', $result['missing_inputs']);
    }

    /** @test */
    public function missing_inputs_includes_lease_option_interest_when_tag_absent(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [],
        ]);

        $result = $this->service->generate($profile);

        $this->assertContains('Lease-option interest', $result['missing_inputs']);
    }

    /** @test */
    public function missing_inputs_includes_commercial_use_interest_when_absent(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [],
        ]);

        $result = $this->service->generate($profile);

        $this->assertContains('Commercial use interest', $result['missing_inputs']);
    }

    /** @test */
    public function missing_inputs_does_not_include_budget_when_budget_flag_is_present(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [
                ['flag' => 'budget_ceiling_specified', 'source_field' => 'max_rent', 'value' => '2000'],
            ],
        ]);

        $result = $this->service->generate($profile);

        $this->assertNotContains('Budget ceiling', $result['missing_inputs']);
    }

    /** @test */
    public function missing_inputs_does_not_include_pet_status_when_has_pets_tag_present(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => ['has-pets'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertNotContains('Pet status', $result['missing_inputs']);
    }

    /** @test */
    public function missing_inputs_does_not_include_lease_option_when_tag_present(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => ['open-to:lease-option'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertNotContains('Lease-option interest', $result['missing_inputs']);
    }

    /** @test */
    public function missing_inputs_does_not_include_pool_amenity_when_pool_tag_present(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => ['requires:pool'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertNotContains('Amenity requirements (pool)', $result['missing_inputs']);
    }

    /** @test */
    public function missing_inputs_does_not_include_space_requirements_when_signal_present(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => ['min-bedrooms:2'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertNotContains('Space requirements', $result['missing_inputs']);
    }

    // -------------------------------------------------------------------------
    // (e) Contract consistency — listing_type is always 'tenant' in every path
    // -------------------------------------------------------------------------

    /** @test */
    public function it_always_returns_listing_type_tenant_in_contract_even_for_non_tenant_guard(): void
    {
        $profile = $this->makeBaseProfile(['listing_type' => 'buyer']);

        $result = $this->service->generate($profile);

        $this->assertSame('tenant', $result['listing_type'],
            'listing_type must be tenant in all output paths — service is tenant-only');
    }

    /** @test */
    public function it_always_returns_listing_type_tenant_in_contract_for_insufficient_completeness(): void
    {
        $profile = $this->makeBaseProfile(['preference_completeness' => 5.0]);

        $result = $this->service->generate($profile);

        $this->assertSame('tenant', $result['listing_type']);
    }

    /** @test */
    public function it_always_returns_listing_type_tenant_in_contract_for_generated_result(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags' => ['has-pets'],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('tenant', $result['listing_type']);
    }

    /** @test */
    public function it_surfaces_listing_id_correctly_in_all_results(): void
    {
        $profile = $this->makeProfile([
            'listing_type'            => 'tenant',
            'listing_id'              => 77,
            'preference_completeness' => 50.0,
            'lifestyle_tags'          => [],
            'deal_breaker_flags'      => [],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('tenant', $result['listing_type']);
        $this->assertSame(77, $result['listing_id']);
    }

    // -------------------------------------------------------------------------
    // (f) No AI/OpenAI imports — service is deterministic only
    // -------------------------------------------------------------------------

    /** @test */
    public function service_file_contains_no_openai_or_ai_imports(): void
    {
        $serviceFile = file_get_contents(
            dirname(__DIR__, 4) . '/app/Services/Dna/TenantAvatarService.php'
        );

        $this->assertStringNotContainsStringIgnoringCase('openai', $serviceFile,
            'TenantAvatarService must not import or reference OpenAI');
        $this->assertStringNotContainsStringIgnoringCase('use OpenAI', $serviceFile,
            'TenantAvatarService must not import OpenAI SDK');
        $this->assertStringNotContainsStringIgnoringCase('AiMarketing', $serviceFile,
            'TenantAvatarService must not import AI marketing services');
    }

    /** @test */
    public function service_constructs_without_any_database_interaction(): void
    {
        $service = new TenantAvatarService();

        $this->assertInstanceOf(TenantAvatarService::class, $service);
    }

    /** @test */
    public function signals_array_in_output_contains_expected_keys(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => ['has-pets'],
            'deal_breaker_flags' => [],
        ]);

        $result  = $this->service->generate($profile);
        $signals = $result['signals'];

        $this->assertArrayHasKey('commercial_signal',        $signals);
        $this->assertArrayHasKey('lease_option_signal',      $signals);
        $this->assertArrayHasKey('has_pets',                 $signals);
        $this->assertArrayHasKey('pool_required',            $signals);
        $this->assertArrayHasKey('garage_required',          $signals);
        $this->assertArrayHasKey('has_property_type',        $signals);
        $this->assertArrayHasKey('space_requirement',        $signals);
        $this->assertArrayHasKey('budget_ceiling_specified', $signals);
        $this->assertArrayHasKey('budget_value',             $signals);
    }

    /** @test */
    public function it_correctly_extracts_budget_value_from_deal_breaker_flag(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [
                ['flag' => 'budget_ceiling_specified', 'source_field' => 'max_rent', 'value' => '3200'],
            ],
        ]);

        $result = $this->service->generate($profile);

        $this->assertTrue($result['signals']['budget_ceiling_specified']);
        $this->assertSame(3200.0, $result['signals']['budget_value']);
    }

    /** @test */
    public function budget_value_is_null_when_budget_ceiling_not_specified(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [],
        ]);

        $result = $this->service->generate($profile);

        $this->assertFalse($result['signals']['budget_ceiling_specified']);
        $this->assertNull($result['signals']['budget_value']);
    }

    /** @test */
    public function missing_inputs_from_low_completeness_contains_threshold_message(): void
    {
        $profile = $this->makeBaseProfile([
            'preference_completeness' => 10.0,
            'lifestyle_tags'          => [],
            'deal_breaker_flags'      => [],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('insufficient_data', $result['status']);
        $found = false;
        foreach ($result['missing_inputs'] as $item) {
            if (str_contains($item, 'completeness below minimum threshold')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'missing_inputs should mention completeness threshold for low-completeness profiles');
    }

    /** @test */
    public function it_classifies_all_secondary_avatars_correctly_for_full_signal_set(): void
    {
        $profile = $this->makeBaseProfile([
            'lifestyle_tags'     => [
                'open-to:lease-option',
                'has-pets',
                'requires:pool',
                'min-bedrooms:3',
            ],
            'deal_breaker_flags' => [
                ['flag' => 'budget_ceiling_specified', 'source_field' => 'max_rent', 'value' => '2800'],
            ],
        ]);

        $result = $this->service->generate($profile);

        $this->assertSame('Lease-Option Tenant', $result['primary_avatar']);
        $this->assertContains('Pet-Conscious Tenant',   $result['secondary_avatars']);
        $this->assertContains('Amenity-Focused Tenant', $result['secondary_avatars']);
        $this->assertContains('Space-Focused Tenant',   $result['secondary_avatars']);
        $this->assertContains('Budget-Conscious Tenant',$result['secondary_avatars']);
    }
}
