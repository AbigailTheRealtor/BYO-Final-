<?php

namespace Tests\Unit\Services\Dna;

use App\Models\BuyerTenantDnaProfile;
use App\Models\PropertyDnaProfile;
use App\Services\Dna\TenantPropertyCompatibilityService;
use PHPUnit\Framework\TestCase;

/**
 * TenantPropertyCompatibilityServiceTest
 *
 * Verifies the TenantPropertyCompatibilityService deterministic compatibility layer
 * against in-memory stubs. No database connection is required — all test data is
 * constructed inline using PHPUnit\Framework\TestCase only.
 *
 * Each test asserts one or more of:
 *   (a) Guard conditions — wrong listing types, sparse/insufficient data → insufficient_data
 *   (b) Output contract shape — all required keys present in every result, every code path
 *   (c) Lease structure alignment — aligned/conflicting/unresolved cases
 *   (d) Pet alignment — pets present/absent, policy present/absent
 *   (e) Amenity alignment — pool, garage, both, neither
 *   (f) Commercial alignment — tenant interest vs. property tag
 *   (g) Waterfront alignment — tenant signal vs. property tag/hook
 *   (h) Location alignment — explicit lifestyle signals vs. Location DNA context arrays
 *   (i) Unresolved vs. conflict behavior
 *   (j) Deterministic output — same inputs always produce same output
 *   (k) Governance constraints — no AI/OpenAI imports, no DB access
 */
class TenantPropertyCompatibilityServiceTest extends TestCase
{
    private TenantPropertyCompatibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TenantPropertyCompatibilityService();
    }

    // -------------------------------------------------------------------------
    // Helpers — build in-memory profile stubs
    // -------------------------------------------------------------------------

    private function makeTenantProfile(array $attributes = []): BuyerTenantDnaProfile
    {
        $profile = new BuyerTenantDnaProfile();
        $defaults = [
            'listing_type'            => 'tenant',
            'listing_id'              => 10,
            'preference_completeness' => 60.0,
            'lifestyle_tags'          => ['has-pets'],
            'deal_breaker_flags'      => [],
        ];
        foreach (array_merge($defaults, $attributes) as $key => $value) {
            $profile->$key = $value;
        }
        return $profile;
    }

    private function makePropertyProfile(array $attributes = []): PropertyDnaProfile
    {
        $profile = new PropertyDnaProfile();
        $defaults = [
            'listing_type'             => 'landlord',
            'listing_id'               => 20,
            'overall_dna_completeness' => 55.0,
            'ai_buyer_archetype_tags'  => ['type:single-family', 'policy:pets-allowed'],
            'ai_marketing_hooks'       => [],
        ];
        foreach (array_merge($defaults, $attributes) as $key => $value) {
            $profile->$key = $value;
        }
        return $profile;
    }

    /** Required output keys that must be present in every response */
    private const REQUIRED_KEYS = [
        'success',
        'status',
        'tenant_listing_id',
        'property_listing_id',
        'compatibility_type',
        'aligned_signals',
        'conflicting_signals',
        'unresolved_signals',
        'tenant_avatar_context',
        'property_personality_context',
        'location_context',
        'missing_inputs',
        'error',
    ];

    private function assertOutputContract(array $result, string $context = ''): void
    {
        foreach (self::REQUIRED_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Missing key '{$key}'" . ($context ? " in: {$context}" : ''));
        }
        $this->assertSame('tenant_property', $result['compatibility_type']);
        $this->assertIsArray($result['aligned_signals']);
        $this->assertIsArray($result['conflicting_signals']);
        $this->assertIsArray($result['unresolved_signals']);
        $this->assertIsArray($result['missing_inputs']);
    }

    // -------------------------------------------------------------------------
    // (a) Guard conditions — wrong listing types
    // -------------------------------------------------------------------------

    /** @test */
    public function it_returns_insufficient_data_when_tenant_listing_type_is_not_tenant(): void
    {
        $tenant   = $this->makeTenantProfile(['listing_type' => 'buyer']);
        $property = $this->makePropertyProfile();

        $result = $this->service->generate($tenant, $property);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_data', $result['status']);
        $this->assertNotEmpty($result['missing_inputs']);
        $this->assertOutputContract($result, 'wrong tenant listing_type');
    }

    /** @test */
    public function it_returns_insufficient_data_when_property_listing_type_is_not_landlord(): void
    {
        $tenant   = $this->makeTenantProfile();
        $property = $this->makePropertyProfile(['listing_type' => 'seller']);

        $result = $this->service->generate($tenant, $property);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_data', $result['status']);
        $this->assertNotEmpty($result['missing_inputs']);
        $this->assertOutputContract($result, 'wrong property listing_type');
    }

    /** @test */
    public function it_returns_insufficient_data_when_both_listing_types_are_wrong(): void
    {
        $tenant   = $this->makeTenantProfile(['listing_type' => 'buyer']);
        $property = $this->makePropertyProfile(['listing_type' => 'seller']);

        $result = $this->service->generate($tenant, $property);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_data', $result['status']);
        $this->assertCount(2, array_filter($result['missing_inputs'], fn($m) => str_contains($m, 'listing_type')));
    }

    /** @test */
    public function it_returns_insufficient_data_when_tenant_has_no_lifestyle_tags_or_flags(): void
    {
        $tenant = $this->makeTenantProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [],
        ]);
        $property = $this->makePropertyProfile();

        $result = $this->service->generate($tenant, $property);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_data', $result['status']);
        $this->assertOutputContract($result, 'empty tenant signals');
    }

    /** @test */
    public function it_returns_insufficient_data_when_property_has_no_tags_hooks_or_personality_context(): void
    {
        $tenant   = $this->makeTenantProfile();
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags'  => [],
            'ai_marketing_hooks'       => [],
            'overall_dna_completeness' => 0.0,
        ]);

        $result = $this->service->generate($tenant, $property);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_data', $result['status']);
        $this->assertOutputContract($result, 'empty property signals');
    }

    /** @test */
    public function it_passes_guard_when_property_has_only_personality_context(): void
    {
        $tenant   = $this->makeTenantProfile();
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags'  => [],
            'ai_marketing_hooks'       => [],
            'overall_dna_completeness' => 0.0,
        ]);
        $personalityCtx = ['primary_personality' => 'Coastal Lifestyle Property'];

        $result = $this->service->generate($tenant, $property, [], $personalityCtx);

        $this->assertNotSame('insufficient_data', $result['status']);
        $this->assertOutputContract($result, 'property with only personality context');
    }

    /** @test */
    public function it_returns_insufficient_data_when_property_has_completeness_but_no_tags_hooks_or_personality_context(): void
    {
        $tenant   = $this->makeTenantProfile();
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags'  => [],
            'ai_marketing_hooks'       => [],
            'overall_dna_completeness' => 99.0,
        ]);

        // overall_dna_completeness alone is not a sufficient guard pass — no personality context either
        $result = $this->service->generate($tenant, $property, [], []);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_data', $result['status']);
        $this->assertNotEmpty($result['missing_inputs']);
        $this->assertOutputContract($result, 'completeness alone does not satisfy property guard');
    }

    /** @test */
    public function it_passes_guard_when_tenant_has_only_deal_breaker_flags(): void
    {
        $tenant = $this->makeTenantProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [['flag' => 'pool_required', 'source_field' => 'pool_needed']],
        ]);
        $property = $this->makePropertyProfile();

        $result = $this->service->generate($tenant, $property);

        $this->assertNotSame('insufficient_data', $result['status']);
        $this->assertOutputContract($result, 'tenant with only deal_breaker_flags');
    }

    // -------------------------------------------------------------------------
    // (b) Output contract shape — all required keys present in every code path
    // -------------------------------------------------------------------------

    /** @test */
    public function output_contract_is_complete_for_generated_result(): void
    {
        $tenant   = $this->makeTenantProfile();
        $property = $this->makePropertyProfile();

        $result = $this->service->generate($tenant, $property);

        $this->assertOutputContract($result, 'generated result');
        $this->assertTrue($result['success']);
        $this->assertSame('generated', $result['status']);
        $this->assertSame(10, $result['tenant_listing_id']);
        $this->assertSame(20, $result['property_listing_id']);
        $this->assertNull($result['error']);
    }

    /** @test */
    public function output_contract_is_complete_for_insufficient_data_result(): void
    {
        $tenant   = $this->makeTenantProfile(['listing_type' => 'buyer']);
        $property = $this->makePropertyProfile();

        $result = $this->service->generate($tenant, $property);

        $this->assertOutputContract($result, 'insufficient_data result');
        $this->assertFalse($result['success']);
    }

    /** @test */
    public function tenant_avatar_context_is_null_when_not_provided(): void
    {
        $tenant   = $this->makeTenantProfile();
        $property = $this->makePropertyProfile();

        $result = $this->service->generate($tenant, $property);

        $this->assertNull($result['tenant_avatar_context']);
    }

    /** @test */
    public function tenant_avatar_context_is_passed_through_when_provided(): void
    {
        $tenant   = $this->makeTenantProfile();
        $property = $this->makePropertyProfile();
        $ctx      = ['primary_avatar' => 'Pet-Conscious Tenant'];

        $result = $this->service->generate($tenant, $property, $ctx);

        $this->assertSame($ctx, $result['tenant_avatar_context']);
    }

    /** @test */
    public function property_personality_context_is_passed_through_when_provided(): void
    {
        $tenant   = $this->makeTenantProfile();
        $property = $this->makePropertyProfile();
        $ctx      = ['primary_personality' => 'Coastal Lifestyle Property'];

        $result = $this->service->generate($tenant, $property, [], $ctx);

        $this->assertSame($ctx, $result['property_personality_context']);
    }

    /** @test */
    public function location_context_is_null_when_not_provided(): void
    {
        $tenant   = $this->makeTenantProfile();
        $property = $this->makePropertyProfile();

        $result = $this->service->generate($tenant, $property);

        $this->assertNull($result['location_context']);
    }

    /** @test */
    public function location_context_is_passed_through_when_provided(): void
    {
        $tenant   = $this->makeTenantProfile();
        $property = $this->makePropertyProfile();
        $loc      = ['coastal_features' => ['beach' => 1.2]];

        $result = $this->service->generate($tenant, $property, [], [], $loc);

        $this->assertSame($loc, $result['location_context']);
    }

    // -------------------------------------------------------------------------
    // (c) Lease structure alignment
    // -------------------------------------------------------------------------

    /** @test */
    public function lease_option_is_aligned_when_both_sides_signal_it(): void
    {
        $tenant   = $this->makeTenantProfile(['lifestyle_tags' => ['open-to:lease-option', 'has-pets']]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['structure:lease-option', 'policy:pets-allowed'],
        ]);

        $result = $this->service->generate($tenant, $property);

        $this->assertSame('generated', $result['status']);

        $aligned = $result['aligned_signals'];
        $dims    = array_column($aligned, 'dimension');
        $this->assertContains('lease_structure_alignment', $dims);

        $entry = $this->findSignalByDimension($aligned, 'lease_structure_alignment');
        $this->assertSame('open-to:lease-option', $entry['tenant_signal']);
        $this->assertSame('structure:lease-option', $entry['property_signal']);
    }

    /** @test */
    public function lease_option_is_conflicting_when_tenant_wants_it_but_property_lacks_it(): void
    {
        $tenant   = $this->makeTenantProfile(['lifestyle_tags' => ['open-to:lease-option', 'has-pets']]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['policy:pets-allowed'],
        ]);

        $result = $this->service->generate($tenant, $property);

        $this->assertSame('generated', $result['status']);
        $dims = array_column($result['conflicting_signals'], 'dimension');
        $this->assertContains('lease_structure_alignment', $dims);
    }

    /** @test */
    public function lease_structure_is_unresolved_when_tenant_has_no_lease_interest(): void
    {
        $tenant   = $this->makeTenantProfile(['lifestyle_tags' => ['has-pets']]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['structure:lease-option', 'policy:pets-allowed'],
        ]);

        $result = $this->service->generate($tenant, $property);

        $dims = array_column($result['unresolved_signals'], 'dimension');
        $this->assertContains('lease_structure_alignment', $dims);
    }

    /** @test */
    public function lease_purchase_is_aligned_when_both_sides_signal_it(): void
    {
        $tenant   = $this->makeTenantProfile(['lifestyle_tags' => ['open-to:lease-purchase', 'has-pets']]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['structure:lease-purchase', 'policy:pets-allowed'],
        ]);

        $result = $this->service->generate($tenant, $property);

        $dims = array_column($result['aligned_signals'], 'dimension');
        $this->assertContains('lease_structure_alignment', $dims);

        $entry = $this->findSignalByDimension($result['aligned_signals'], 'lease_structure_alignment');
        $this->assertSame('open-to:lease-purchase', $entry['tenant_signal']);
        $this->assertSame('structure:lease-purchase', $entry['property_signal']);
    }

    /** @test */
    public function lease_purchase_is_conflicting_when_tenant_wants_it_but_property_lacks_it(): void
    {
        $tenant   = $this->makeTenantProfile(['lifestyle_tags' => ['open-to:lease-purchase', 'has-pets']]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['policy:pets-allowed'],
        ]);

        $result = $this->service->generate($tenant, $property);

        $dims = array_column($result['conflicting_signals'], 'dimension');
        $this->assertContains('lease_structure_alignment', $dims);
    }

    // -------------------------------------------------------------------------
    // (d) Pet alignment
    // -------------------------------------------------------------------------

    /** @test */
    public function pet_alignment_is_aligned_when_tenant_has_pets_and_property_allows(): void
    {
        $tenant   = $this->makeTenantProfile(['lifestyle_tags' => ['has-pets']]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['policy:pets-allowed'],
        ]);

        $result = $this->service->generate($tenant, $property);

        $dims = array_column($result['aligned_signals'], 'dimension');
        $this->assertContains('pet_alignment', $dims);

        $entry = $this->findSignalByDimension($result['aligned_signals'], 'pet_alignment');
        $this->assertSame('has-pets', $entry['tenant_signal']);
        $this->assertSame('policy:pets-allowed', $entry['property_signal']);
    }

    /** @test */
    public function pet_alignment_is_conflicting_when_tenant_has_pets_but_property_lacks_policy(): void
    {
        $tenant   = $this->makeTenantProfile(['lifestyle_tags' => ['has-pets']]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['type:single-family'],
        ]);

        $result = $this->service->generate($tenant, $property);

        $dims = array_column($result['conflicting_signals'], 'dimension');
        $this->assertContains('pet_alignment', $dims);
    }

    /** @test */
    public function pet_alignment_is_unresolved_when_tenant_has_no_pet_signal(): void
    {
        $tenant   = $this->makeTenantProfile(['lifestyle_tags' => ['open-to:lease-option']]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['policy:pets-allowed'],
        ]);

        $result = $this->service->generate($tenant, $property);

        $dims = array_column($result['unresolved_signals'], 'dimension');
        $this->assertContains('pet_alignment', $dims);
    }

    /** @test */
    public function pet_alignment_is_aligned_when_pet_required_flag_is_present(): void
    {
        $tenant   = $this->makeTenantProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [['flag' => 'pet_required', 'source_field' => 'pets']],
        ]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['policy:pets-allowed'],
        ]);

        $result = $this->service->generate($tenant, $property);

        $dims = array_column($result['aligned_signals'], 'dimension');
        $this->assertContains('pet_alignment', $dims);
    }

    // -------------------------------------------------------------------------
    // (e) Amenity alignment
    // -------------------------------------------------------------------------

    /** @test */
    public function amenity_pool_is_aligned_when_tenant_requires_pool_and_property_has_pool(): void
    {
        $tenant   = $this->makeTenantProfile(['lifestyle_tags' => ['requires:pool', 'has-pets']]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['amenity:pool', 'policy:pets-allowed'],
        ]);

        $result = $this->service->generate($tenant, $property);

        $aligned = $this->findAllSignalsByDimension($result['aligned_signals'], 'amenity_alignment');
        $signals  = array_column($aligned, 'tenant_signal');
        $this->assertContains('requires:pool', $signals);
    }

    /** @test */
    public function amenity_pool_is_conflicting_when_tenant_requires_pool_but_property_lacks_it(): void
    {
        $tenant   = $this->makeTenantProfile(['lifestyle_tags' => ['requires:pool', 'has-pets']]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['policy:pets-allowed'],
        ]);

        $result = $this->service->generate($tenant, $property);

        $dims = array_column($result['conflicting_signals'], 'dimension');
        $this->assertContains('amenity_alignment', $dims);
    }

    /** @test */
    public function amenity_garage_is_aligned_when_tenant_requires_garage_and_property_has_parking_garage(): void
    {
        $tenant   = $this->makeTenantProfile(['lifestyle_tags' => ['requires:garage', 'has-pets']]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['parking:garage', 'policy:pets-allowed'],
        ]);

        $result = $this->service->generate($tenant, $property);

        $aligned = $this->findAllSignalsByDimension($result['aligned_signals'], 'amenity_alignment');
        $signals  = array_column($aligned, 'tenant_signal');
        $this->assertContains('requires:garage', $signals);
    }

    /** @test */
    public function amenity_garage_is_conflicting_when_tenant_requires_garage_but_property_lacks_it(): void
    {
        $tenant   = $this->makeTenantProfile(['lifestyle_tags' => ['requires:garage', 'has-pets']]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['policy:pets-allowed'],
        ]);

        $result = $this->service->generate($tenant, $property);

        $dims = array_column($result['conflicting_signals'], 'dimension');
        $this->assertContains('amenity_alignment', $dims);
    }

    /** @test */
    public function amenity_is_unresolved_when_tenant_has_no_amenity_requirements(): void
    {
        $tenant   = $this->makeTenantProfile(['lifestyle_tags' => ['has-pets']]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['amenity:pool', 'policy:pets-allowed'],
        ]);

        $result = $this->service->generate($tenant, $property);

        $dims = array_column($result['unresolved_signals'], 'dimension');
        $this->assertContains('amenity_alignment', $dims);
    }

    /** @test */
    public function amenity_pool_aligned_via_deal_breaker_flag(): void
    {
        $tenant   = $this->makeTenantProfile([
            'lifestyle_tags'     => ['has-pets'],
            'deal_breaker_flags' => [['flag' => 'pool_required', 'source_field' => 'pool_needed']],
        ]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['amenity:pool', 'policy:pets-allowed'],
        ]);

        $result = $this->service->generate($tenant, $property);

        $dims = array_column($result['aligned_signals'], 'dimension');
        $this->assertContains('amenity_alignment', $dims);
    }

    // -------------------------------------------------------------------------
    // (f) Commercial alignment
    // -------------------------------------------------------------------------

    /** @test */
    public function commercial_is_aligned_when_tenant_has_commercial_tag_and_property_has_commercial_tag(): void
    {
        $tenant   = $this->makeTenantProfile([
            'lifestyle_tags'     => ['prefers-type:Commercial'],
            'deal_breaker_flags' => [],
        ]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['use:commercial'],
        ]);

        $result = $this->service->generate($tenant, $property);

        $dims = array_column($result['aligned_signals'], 'dimension');
        $this->assertContains('commercial_alignment', $dims);
    }

    /** @test */
    public function commercial_is_conflicting_when_tenant_has_commercial_interest_flag_but_property_lacks_tag(): void
    {
        $tenant   = $this->makeTenantProfile([
            'lifestyle_tags'     => ['has-pets'],
            'deal_breaker_flags' => [['flag' => 'commercial_interest', 'source_field' => 'property_type']],
        ]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['policy:pets-allowed'],
        ]);

        $result = $this->service->generate($tenant, $property);

        $dims = array_column($result['conflicting_signals'], 'dimension');
        $this->assertContains('commercial_alignment', $dims);
    }

    /** @test */
    public function commercial_is_unresolved_when_neither_side_has_commercial_signal(): void
    {
        $tenant   = $this->makeTenantProfile(['lifestyle_tags' => ['has-pets']]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['type:single-family', 'policy:pets-allowed'],
        ]);

        $result = $this->service->generate($tenant, $property);

        $dims = array_column($result['unresolved_signals'], 'dimension');
        $this->assertContains('commercial_alignment', $dims);
    }

    /** @test */
    public function commercial_is_unresolved_when_only_property_has_commercial_signal(): void
    {
        $tenant   = $this->makeTenantProfile(['lifestyle_tags' => ['has-pets']]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['use:commercial', 'policy:pets-allowed'],
        ]);

        $result = $this->service->generate($tenant, $property);

        $dims = array_column($result['unresolved_signals'], 'dimension');
        $this->assertContains('commercial_alignment', $dims);
    }

    // -------------------------------------------------------------------------
    // (g) Waterfront alignment
    // -------------------------------------------------------------------------

    /** @test */
    public function waterfront_is_aligned_when_tenant_has_waterfront_tag_and_property_has_waterfront_tag(): void
    {
        $tenant   = $this->makeTenantProfile([
            'lifestyle_tags' => ['prefers-type:Waterfront', 'has-pets'],
        ]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['amenity:waterfront', 'policy:pets-allowed'],
        ]);

        $result = $this->service->generate($tenant, $property);

        $dims = array_column($result['aligned_signals'], 'dimension');
        $this->assertContains('waterfront_lifestyle_alignment', $dims);
    }

    /** @test */
    public function waterfront_is_aligned_when_property_has_waterfront_via_marketing_hook(): void
    {
        $tenant   = $this->makeTenantProfile([
            'lifestyle_tags' => ['prefers-type:Waterfront', 'has-pets'],
        ]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['policy:pets-allowed'],
            'ai_marketing_hooks'      => [['trait' => 'waterfront', 'value' => 'Gulf-front']],
        ]);

        $result = $this->service->generate($tenant, $property);

        $dims = array_column($result['aligned_signals'], 'dimension');
        $this->assertContains('waterfront_lifestyle_alignment', $dims);
    }

    /** @test */
    public function waterfront_is_aligned_when_property_has_coastal_hook(): void
    {
        $tenant   = $this->makeTenantProfile([
            'lifestyle_tags' => ['prefers-type:Waterfront', 'has-pets'],
        ]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['policy:pets-allowed'],
            'ai_marketing_hooks'      => [['trait' => 'coastal', 'value' => 'Beachside']],
        ]);

        $result = $this->service->generate($tenant, $property);

        $dims = array_column($result['aligned_signals'], 'dimension');
        $this->assertContains('waterfront_lifestyle_alignment', $dims);
    }

    /** @test */
    public function waterfront_is_conflicting_when_tenant_has_waterfront_signal_but_property_lacks_it(): void
    {
        $tenant   = $this->makeTenantProfile([
            'lifestyle_tags' => ['prefers-type:Waterfront', 'has-pets'],
        ]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['type:single-family', 'policy:pets-allowed'],
            'ai_marketing_hooks'      => [],
        ]);

        $result = $this->service->generate($tenant, $property);

        $dims = array_column($result['conflicting_signals'], 'dimension');
        $this->assertContains('waterfront_lifestyle_alignment', $dims);
    }

    /** @test */
    public function waterfront_is_unresolved_when_tenant_has_no_waterfront_signal(): void
    {
        $tenant   = $this->makeTenantProfile(['lifestyle_tags' => ['has-pets']]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['amenity:waterfront', 'policy:pets-allowed'],
        ]);

        $result = $this->service->generate($tenant, $property);

        $dims = array_column($result['unresolved_signals'], 'dimension');
        $this->assertContains('waterfront_lifestyle_alignment', $dims);
    }

    // -------------------------------------------------------------------------
    // (h) Location alignment
    // -------------------------------------------------------------------------

    /** @test */
    public function location_is_unresolved_when_no_location_context_is_provided(): void
    {
        $tenant   = $this->makeTenantProfile(['lifestyle_tags' => ['prefers-type:Waterfront', 'has-pets']]);
        $property = $this->makePropertyProfile();

        $result = $this->service->generate($tenant, $property);

        $dims = array_column($result['unresolved_signals'], 'dimension');
        $this->assertContains('location_alignment', $dims);
    }

    /** @test */
    public function location_coastal_is_aligned_when_tenant_has_waterfront_tag_and_coastal_features_present(): void
    {
        $tenant   = $this->makeTenantProfile([
            'lifestyle_tags' => ['prefers-type:Waterfront', 'has-pets'],
        ]);
        $property = $this->makePropertyProfile();
        $locCtx   = ['coastal_features' => ['nearest_beach_miles' => 0.8]];

        $result = $this->service->generate($tenant, $property, [], [], $locCtx);

        $dims = array_column($result['aligned_signals'], 'dimension');
        $this->assertContains('location_alignment', $dims);

        $entry = $this->findSignalByDimension($result['aligned_signals'], 'location_alignment');
        $this->assertSame('coastal_lifestyle_signal', $entry['tenant_signal']);
        $this->assertSame('coastal_features_present', $entry['property_signal']);
    }

    /** @test */
    public function location_coastal_is_conflicting_when_tenant_has_waterfront_tag_but_no_coastal_features(): void
    {
        $tenant   = $this->makeTenantProfile([
            'lifestyle_tags' => ['prefers-type:Waterfront', 'has-pets'],
        ]);
        $property = $this->makePropertyProfile();
        $locCtx   = [
            'coastal_features'   => [],
            'daily_convenience'  => ['nearest_grocery_miles' => 0.3],
            'outdoor_recreation' => [],
            'transportation'     => [],
        ];

        $result = $this->service->generate($tenant, $property, [], [], $locCtx);

        $dims = array_column($result['conflicting_signals'], 'dimension');
        $this->assertContains('location_alignment', $dims);
    }

    /** @test */
    public function location_is_unresolved_when_tenant_has_no_matching_lifestyle_signals(): void
    {
        $tenant   = $this->makeTenantProfile(['lifestyle_tags' => ['has-pets']]);
        $property = $this->makePropertyProfile();
        $locCtx   = ['coastal_features' => ['nearest_beach_miles' => 1.5]];

        $result = $this->service->generate($tenant, $property, [], [], $locCtx);

        $dims = array_column($result['unresolved_signals'], 'dimension');
        $this->assertContains('location_alignment', $dims);
    }

    /** @test */
    public function location_compares_only_explicit_signals_no_demographic_assumptions(): void
    {
        // Only lifestyle signals in tenant tags trigger location comparisons.
        // No protected-class inference should occur. This test verifies that
        // a tenant with no explicit location-relevant lifestyle tags produces
        // only unresolved location signals regardless of what the location context contains.
        $tenant   = $this->makeTenantProfile([
            'lifestyle_tags'     => ['has-pets', 'open-to:lease-option'],
            'deal_breaker_flags' => [],
        ]);
        $property = $this->makePropertyProfile();
        $locCtx   = [
            'coastal_features'   => ['nearest_beach_miles' => 0.5],
            'daily_convenience'  => ['nearest_grocery_miles' => 0.2],
            'outdoor_recreation' => ['nearest_park_miles' => 0.3],
            'transportation'     => ['nearest_transit_miles' => 0.4],
        ];

        $result = $this->service->generate($tenant, $property, [], [], $locCtx);

        // No conflicting location signals should exist since tenant has no
        // matching explicit lifestyle signals for location dimensions
        $locationConflicts = $this->findAllSignalsByDimension($result['conflicting_signals'], 'location_alignment');
        $this->assertEmpty($locationConflicts, 'No location conflicts expected when tenant has no location lifestyle signals');
    }

    // -------------------------------------------------------------------------
    // (i) Unresolved vs. conflict behavior
    // -------------------------------------------------------------------------

    /** @test */
    public function unresolved_signals_have_missing_side_key_not_tenant_or_property_signal(): void
    {
        $tenant   = $this->makeTenantProfile(['lifestyle_tags' => ['has-pets']]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['policy:pets-allowed'],
        ]);

        $result = $this->service->generate($tenant, $property);

        foreach ($result['unresolved_signals'] as $entry) {
            $this->assertArrayHasKey('dimension',    $entry);
            $this->assertArrayHasKey('missing_side', $entry);
            $this->assertArrayHasKey('reason',       $entry);
            $this->assertArrayNotHasKey('_bucket', $entry);
        }
    }

    /** @test */
    public function aligned_and_conflicting_signals_have_tenant_and_property_signal_keys(): void
    {
        $tenant   = $this->makeTenantProfile([
            'lifestyle_tags' => ['has-pets', 'requires:pool', 'open-to:lease-option'],
        ]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['policy:pets-allowed', 'amenity:pool'],
        ]);

        $result = $this->service->generate($tenant, $property);

        foreach ($result['aligned_signals'] as $entry) {
            $this->assertArrayHasKey('dimension',       $entry);
            $this->assertArrayHasKey('tenant_signal',   $entry);
            $this->assertArrayHasKey('property_signal', $entry);
            $this->assertArrayHasKey('reason',          $entry);
            $this->assertArrayNotHasKey('_bucket', $entry);
        }

        foreach ($result['conflicting_signals'] as $entry) {
            $this->assertArrayHasKey('dimension',       $entry);
            $this->assertArrayHasKey('tenant_signal',   $entry);
            $this->assertArrayHasKey('property_signal', $entry);
            $this->assertArrayHasKey('reason',          $entry);
            $this->assertArrayNotHasKey('_bucket', $entry);
        }
    }

    /** @test */
    public function internal_bucket_key_is_never_present_in_any_output_signal(): void
    {
        $tenant   = $this->makeTenantProfile([
            'lifestyle_tags' => [
                'has-pets',
                'requires:pool',
                'open-to:lease-option',
                'open-to:lease-purchase',
                'prefers-type:Waterfront',
            ],
        ]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['policy:pets-allowed', 'amenity:pool'],
            'ai_marketing_hooks'      => [['trait' => 'waterfront', 'value' => 'Gulf-front']],
        ]);

        $result = $this->service->generate($tenant, $property);

        $allSignals = array_merge(
            $result['aligned_signals'],
            $result['conflicting_signals'],
            $result['unresolved_signals']
        );

        foreach ($allSignals as $entry) {
            $this->assertArrayNotHasKey('_bucket', $entry, '_bucket must be stripped from all output signals');
        }
    }

    // -------------------------------------------------------------------------
    // (j) Deterministic output — same inputs always produce same output
    // -------------------------------------------------------------------------

    /** @test */
    public function identical_inputs_always_produce_identical_outputs(): void
    {
        $tenant   = $this->makeTenantProfile([
            'lifestyle_tags'     => ['has-pets', 'requires:pool', 'open-to:lease-option'],
            'deal_breaker_flags' => [['flag' => 'garage_required', 'source_field' => 'garage_needed']],
        ]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['type:single-family', 'policy:pets-allowed', 'amenity:pool'],
        ]);

        $result1 = $this->service->generate($tenant, $property);
        $result2 = $this->service->generate($tenant, $property);

        $this->assertSame($result1['aligned_signals'],    $result2['aligned_signals']);
        $this->assertSame($result1['conflicting_signals'], $result2['conflicting_signals']);
        $this->assertSame($result1['unresolved_signals'],  $result2['unresolved_signals']);
        $this->assertSame($result1['status'],              $result2['status']);
    }

    // -------------------------------------------------------------------------
    // (k) Governance constraints
    // -------------------------------------------------------------------------

    /** @test */
    public function service_file_does_not_import_openai_or_ai_classes(): void
    {
        $serviceFile = file_get_contents(
            dirname(__DIR__, 4) . '/app/Services/Dna/TenantPropertyCompatibilityService.php'
        );

        // Check for actual use/import statements referencing AI providers — not comment mentions.
        $this->assertDoesNotMatchRegularExpression('/^use\s+OpenAI/m',            $serviceFile);
        $this->assertDoesNotMatchRegularExpression('/^use\s+.*OpenAI/m',          $serviceFile);
        $this->assertDoesNotMatchRegularExpression('/new\s+OpenAI/i',             $serviceFile);
        $this->assertDoesNotMatchRegularExpression('/OpenAI::/',                  $serviceFile);
        $this->assertStringNotContainsString('AiMarketingReport',                 $serviceFile);
        $this->assertDoesNotMatchRegularExpression('/^use\s+.*AiMarketing/m',     $serviceFile);
    }

    /** @test */
    public function service_file_does_not_contain_db_reads_or_writes(): void
    {
        $serviceFile = file_get_contents(
            dirname(__DIR__, 4) . '/app/Services/Dna/TenantPropertyCompatibilityService.php'
        );

        $this->assertStringNotContainsString('DB::',             $serviceFile);
        $this->assertStringNotContainsString('->save(',          $serviceFile);
        $this->assertStringNotContainsString('->create(',        $serviceFile);
        $this->assertStringNotContainsString('->update(',        $serviceFile);
        $this->assertStringNotContainsString('::find(',          $serviceFile);
        $this->assertStringNotContainsString('::where(',         $serviceFile);
        $this->assertStringNotContainsString('->get()',          $serviceFile);
        $this->assertStringNotContainsString('->first()',        $serviceFile);
    }

    /** @test */
    public function compatibility_type_is_always_tenant_property(): void
    {
        $tenant   = $this->makeTenantProfile();
        $property = $this->makePropertyProfile();

        $result = $this->service->generate($tenant, $property);

        $this->assertSame('tenant_property', $result['compatibility_type']);
    }

    /** @test */
    public function compatibility_type_is_tenant_property_even_for_insufficient_data(): void
    {
        $tenant   = $this->makeTenantProfile(['listing_type' => 'buyer']);
        $property = $this->makePropertyProfile();

        $result = $this->service->generate($tenant, $property);

        $this->assertSame('tenant_property', $result['compatibility_type']);
    }

    // -------------------------------------------------------------------------
    // Helpers — signal search utilities
    // -------------------------------------------------------------------------

    private function findSignalByDimension(array $signals, string $dimension): ?array
    {
        foreach ($signals as $entry) {
            if (($entry['dimension'] ?? '') === $dimension) {
                return $entry;
            }
        }
        return null;
    }

    private function findAllSignalsByDimension(array $signals, string $dimension): array
    {
        return array_values(array_filter($signals, fn($e) => ($e['dimension'] ?? '') === $dimension));
    }
}
