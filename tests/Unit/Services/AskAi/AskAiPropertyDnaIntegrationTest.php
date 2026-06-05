<?php

namespace Tests\Unit\Services\AskAi;

use App\Models\PropertyDnaProfile;
use App\Models\PropertyLocationDna;
use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\AskAi\AskAiPromptBuilderService;
use App\Services\AskAi\AskAiResponseContractService;
use App\Services\Dna\PropertyIntelligenceProfileService;
use PHPUnit\Framework\TestCase;

/**
 * AskAiPropertyDnaIntegrationTest
 *
 * Pure unit tests — no database, no Laravel TestCase, no DB traits.
 * Traces the full Context → Contract → Prompt chain for property intelligence
 * scenarios, confirming the three supported question types work correctly
 * end-to-end and that the pipeline degrades safely when intelligence data is absent.
 *
 * Test coverage (steps 1–6 from task spec):
 *
 * Step 1 — class setup (partial mock pattern from AskAiContextBuilderServiceTest)
 *
 * Step 2 — Context layer:
 *   A. buildForListing() with mocked DNA profile returns property_intelligence with all approved fields
 *   B. Missing profile appends 'property_intelligence' to missing_sources and returns null for the key
 *
 * Step 3 — Contract layer:
 *   C. property_standout with property_intelligence present → contract_ready
 *   D. marketing_angles with property_intelligence present → contract_ready
 *   E. property_standout with property_intelligence absent/null → insufficient_context
 *   F. marketing_angles with property_intelligence absent/null → insufficient_context
 *
 * Step 4 — Prompt layer:
 *   G. contract_ready for property_standout with intelligence → prompt_ready
 *   H. source_attribution.required_sources includes 'property_intelligence'
 *   I. source_attribution.versions carries 'property_intelligence_version' key
 *   J. insufficient_context contract → non-prompt_ready status in prompt package
 *   K. allowed_context filtered to only property intelligence dot-paths; no bleed-through
 *
 * Step 5 — End-to-end chain:
 *   L. Context → Contract → Prompt for property_standout produces prompt_ready with source attribution
 *
 * Step 6 — Governance / no-write scan:
 *   M. None of the four service files contain write calls on non-comment lines
 *
 * Step 7 — missing_data surfaces missing_sources when property intelligence is absent:
 *   N. missing_data with listing present and property_intelligence absent → contract_ready
 *      with 'missing_sources' in allowed_context dot-paths
 */
class AskAiPropertyDnaIntegrationTest extends TestCase
{
    /**
     * Approved property intelligence field keys returned by buildPropertyIntelligence().
     */
    private const PROPERTY_INTELLIGENCE_KEYS = [
        'property_strengths',
        'property_highlights',
        'property_positioning',
        'property_target_audiences',
        'property_personality_tags',
        'property_story',
        'location_intelligence_context',
        'property_intelligence_version',
        'source_profile_id',
        'source_profile_version',
        'source_profile_computed_at',
    ];

    /**
     * Absolute base path for all four governed service files.
     */
    private function serviceDir(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/';
    }

    // =========================================================================
    // Shared factory helpers
    // =========================================================================

    /**
     * Build a mock PropertyIntelligenceProfileService that allows stubbing
     * buildPayloadReadOnly() without touching the DB.
     *
     * @return PropertyIntelligenceProfileService&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeIntelligenceServiceMock(): PropertyIntelligenceProfileService
    {
        return $this->getMockBuilder(PropertyIntelligenceProfileService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['buildPayloadReadOnly'])
            ->getMock();
    }

    /**
     * A successful buildPayloadReadOnly() return value with all approved fields.
     */
    private function makeIntelligencePayload(array $overrides = []): array
    {
        return array_merge([
            'success'                       => true,
            'status'                        => 'generated',
            'listing_type'                  => 'seller',
            'listing_id'                    => 1,
            'property_strengths'            => ['Pool', 'Garage'],
            'property_highlights'           => ['Pool', 'Garage', 'Pets Allowed'],
            'property_positioning'          => 'Move-Up Home',
            'property_target_audiences'     => ['Move-Up Families', 'First-Time Buyers'],
            'property_personality_tags'     => ['Outdoor Living', 'Family-Friendly'],
            'property_story'                => 'A beautifully updated Move-Up Home with a pool and garage.',
            'location_intelligence_context' => ['neighborhood_score' => 82],
            'property_intelligence_version' => 'PROPERTY_INTELLIGENCE_V1',
            'error'                         => null,
        ], $overrides);
    }

    /**
     * Create a PropertyDnaProfile stub in memory (no DB).
     */
    private function makePropertyDnaProfile(array $attrs = []): PropertyDnaProfile
    {
        $profile = new PropertyDnaProfile();
        $profile->id                         = $attrs['id'] ?? 10;
        $profile->listing_type               = $attrs['listing_type'] ?? 'seller';
        $profile->listing_id                 = $attrs['listing_id'] ?? 1;
        $profile->version                    = $attrs['version'] ?? 'v1';
        $profile->overall_dna_completeness   = $attrs['overall_dna_completeness'] ?? 70.0;
        $profile->ai_buyer_archetype_tags    = $attrs['ai_buyer_archetype_tags'] ?? ['amenity:pool', 'parking:garage'];
        $profile->ai_marketing_hooks         = $attrs['ai_marketing_hooks'] ?? [];
        $profile->location_intelligence_context = $attrs['location_intelligence_context'] ?? null;
        $profile->computed_at                = $attrs['computed_at'] ?? null;
        $profile->archived_at               = null;
        return $profile;
    }

    /**
     * Create a PropertyLocationDna stub in memory (no DB).
     */
    private function makeLocationDna(array $attrs = []): PropertyLocationDna
    {
        $dna = new PropertyLocationDna();
        $dna->listing_type   = $attrs['listing_type'] ?? 'seller';
        $dna->listing_id     = $attrs['listing_id'] ?? 1;
        $dna->geocode_status = $attrs['geocode_status'] ?? 'success';
        $dna->lifestyle_json = $attrs['lifestyle_json'] ?? [
            'scores'     => ['walkability' => 72],
            'categories' => ['walkable'],
            'narrative'  => 'A walkable neighborhood.',
            'version'    => 'LIFESTYLE_V1',
        ];
        $dna->generated_at = $attrs['generated_at'] ?? null;
        return $dna;
    }

    /**
     * Build a minimal listing stub so the context builder proceeds past not_found.
     */
    private function makeListingStub(): object
    {
        $stub             = new \stdClass();
        $stub->id         = 1;
        $stub->is_approved = true;
        $stub->created_at  = '2026-01-01 00:00:00';
        $stub->updated_at  = '2026-01-01 00:00:00';
        return $stub;
    }

    /**
     * Build a partial mock of AskAiContextBuilderService that stubs all finder
     * methods, mirroring the pattern in AskAiContextBuilderServiceTest.
     *
     * @return AskAiContextBuilderService&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeContextService(
        ?PropertyIntelligenceProfileService $intelligenceService = null
    ): AskAiContextBuilderService {
        $intelligenceService ??= $this->makeIntelligenceServiceMock();

        return $this->getMockBuilder(AskAiContextBuilderService::class)
            ->setConstructorArgs([$intelligenceService])
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
     * Build a fully-assembled seller context with property_intelligence populated.
     * Used directly in contract and prompt layer tests (no context builder needed).
     */
    private function makeAssembledSellerContext(array $overrides = []): array
    {
        return array_merge([
            'success'               => true,
            'listing_type'          => 'seller',
            'listing_id'            => 1,
            'context_version'       => 'ASK_AI_CONTEXT_V1',
            'status'                => 'assembled',
            'listing'               => [
                'listing_id'     => 1,
                'listing_title'  => 'Beautiful Tampa Home',
                'city'           => 'Tampa',
                'state'          => 'FL',
                'county'         => null,
                'property_type'  => 'Single Family',
                'listing_status' => 'approved',
                'created_at'     => '2026-01-01 00:00:00',
                'updated_at'     => '2026-01-01 00:00:00',
            ],
            'property_intelligence' => [
                'property_strengths'            => ['Pool', 'Garage'],
                'property_highlights'           => ['Pool', 'Garage', 'Pets Allowed'],
                'property_positioning'          => 'Move-Up Home',
                'property_target_audiences'     => ['Move-Up Families'],
                'property_personality_tags'     => ['Outdoor Living', 'Family-Friendly'],
                'property_story'                => 'A beautifully updated Move-Up Home.',
                'location_intelligence_context' => null,
                'property_intelligence_version' => 'PROPERTY_INTELLIGENCE_V1',
                'source_profile_id'             => 10,
                'source_profile_version'        => 'v1',
                'source_profile_computed_at'    => null,
            ],
            'location_intelligence' => [
                'lifestyle_json'       => ['scores' => ['walkability' => 72], 'version' => 'LIFESTYLE_V1'],
                'lifestyle_scores'     => ['walkability' => 72],
                'lifestyle_categories' => ['walkable'],
                'location_narrative'   => 'A walkable neighborhood.',
                'lifestyle_version'    => 'LIFESTYLE_V1',
                'geocode_status'       => 'success',
                'generated_at'         => null,
            ],
            'buyer_avatar'          => null,
            'tenant_avatar'         => null,
            'compatibility'         => null,
            'offer_analysis'        => null,
            'missing_sources'       => [],
            'warnings'              => [],
            'source_versions'       => [
                'ask_ai_context'                => 'ASK_AI_CONTEXT_V1',
                'property_intelligence_version' => 'PROPERTY_INTELLIGENCE_V1',
                'location_dna_lifestyle_version'=> 'LIFESTYLE_V1',
                'buyer_avatar_version'          => null,
                'tenant_avatar_version'         => null,
                'compatibility_version'         => null,
            ],
            'assembled_at'          => '2026-06-04T12:00:00.000000Z',
            'error'                 => null,
        ], $overrides);
    }

    /**
     * Build a context stub where property_intelligence is absent (null).
     */
    private function makeContextWithoutPropertyIntelligence(array $overrides = []): array
    {
        $ctx = $this->makeAssembledSellerContext($overrides);
        $ctx['property_intelligence'] = null;
        $ctx['missing_sources']       = ['property_intelligence'];
        $ctx['status']                = 'partial';
        return $ctx;
    }

    // =========================================================================
    // Step 2A — Context layer: buildForListing() returns all approved PI fields
    // =========================================================================

    public function test_step2A_context_layer_returns_all_approved_property_intelligence_fields(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')
            ->willReturn($this->makeIntelligencePayload());

        $service = $this->makeContextService($intelligenceService);
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfile());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('seller', 1);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['property_intelligence']);

        foreach (self::PROPERTY_INTELLIGENCE_KEYS as $key) {
            $this->assertArrayHasKey(
                $key,
                $result['property_intelligence'],
                "property_intelligence is missing approved field '{$key}'"
            );
        }
    }

    public function test_step2A_context_layer_property_intelligence_contains_correct_values(): void
    {
        $payload = $this->makeIntelligencePayload([
            'property_strengths'        => ['Pool', 'Garage'],
            'property_highlights'       => ['Pool', 'Garage', 'Pets Allowed'],
            'property_positioning'      => 'Move-Up Home',
            'property_target_audiences' => ['Move-Up Families'],
            'property_personality_tags' => ['Outdoor Living'],
            'property_story'            => 'A great home.',
            'property_intelligence_version' => 'PROPERTY_INTELLIGENCE_V1',
        ]);

        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')->willReturn($payload);

        $service = $this->makeContextService($intelligenceService);
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfile());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('seller', 1);

        $pi = $result['property_intelligence'];
        $this->assertSame(['Pool', 'Garage'], $pi['property_strengths']);
        $this->assertSame(['Pool', 'Garage', 'Pets Allowed'], $pi['property_highlights']);
        $this->assertSame('Move-Up Home', $pi['property_positioning']);
        $this->assertSame(['Move-Up Families'], $pi['property_target_audiences']);
        $this->assertSame(['Outdoor Living'], $pi['property_personality_tags']);
        $this->assertSame('A great home.', $pi['property_story']);
        $this->assertSame('PROPERTY_INTELLIGENCE_V1', $pi['property_intelligence_version']);
        $this->assertSame(10, $pi['source_profile_id']);
    }

    // =========================================================================
    // Step 2B — Context layer: missing profile → missing_sources + null PI
    // =========================================================================

    public function test_step2B_context_layer_missing_profile_appends_property_intelligence_to_missing_sources(): void
    {
        $service = $this->makeContextService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyDnaProfile')->willReturn(null);
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('seller', 1);

        $this->assertContains('property_intelligence', $result['missing_sources']);
        $this->assertNull($result['property_intelligence']);
    }

    public function test_step2B_context_layer_failed_payload_appends_property_intelligence_to_missing_sources(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')->willReturn([
            'success' => false,
            'status'  => 'insufficient_data',
            'error'   => 'No data available.',
        ]);

        $service = $this->makeContextService($intelligenceService);
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfile());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('seller', 1);

        $this->assertContains('property_intelligence', $result['missing_sources']);
        $this->assertNull($result['property_intelligence']);
    }

    public function test_step2B_context_layer_missing_profile_status_is_partial(): void
    {
        $service = $this->makeContextService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyDnaProfile')->willReturn(null);
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('seller', 1);

        $this->assertSame('partial', $result['status']);
    }

    // =========================================================================
    // Step 3C — Contract layer: property_standout with PI present → contract_ready
    // =========================================================================

    public function test_step3C_contract_layer_property_standout_with_intelligence_returns_contract_ready(): void
    {
        $service  = new AskAiResponseContractService();
        $context  = $this->makeAssembledSellerContext();

        $result = $service->buildContract('property_standout', $context);

        $this->assertSame('contract_ready', $result['status']);
        $this->assertTrue($result['success']);
        $this->assertEmpty($result['missing_required_sources']);
    }

    public function test_step3C_contract_layer_property_standout_allowed_context_includes_pi_paths(): void
    {
        $service = new AskAiResponseContractService();
        $context = $this->makeAssembledSellerContext();

        $result = $service->buildContract('property_standout', $context);

        $this->assertSame('contract_ready', $result['status']);

        $piPaths = array_filter(
            $result['allowed_context'],
            static fn(string $path) => str_starts_with($path, 'property_intelligence.')
        );
        $this->assertNotEmpty($piPaths, 'property_standout allowed_context must include property_intelligence dot-paths');
    }

    // =========================================================================
    // Step 3D — Contract layer: marketing_angles with PI present → contract_ready
    // =========================================================================

    public function test_step3D_contract_layer_marketing_angles_with_intelligence_returns_contract_ready(): void
    {
        $service = new AskAiResponseContractService();
        $context = $this->makeAssembledSellerContext();

        $result = $service->buildContract('marketing_angles', $context);

        $this->assertSame('contract_ready', $result['status']);
        $this->assertTrue($result['success']);
        $this->assertEmpty($result['missing_required_sources']);
    }

    public function test_step3D_contract_layer_marketing_angles_allowed_context_includes_pi_paths(): void
    {
        $service = new AskAiResponseContractService();
        $context = $this->makeAssembledSellerContext();

        $result = $service->buildContract('marketing_angles', $context);

        $this->assertSame('contract_ready', $result['status']);

        $piPaths = array_filter(
            $result['allowed_context'],
            static fn(string $path) => str_starts_with($path, 'property_intelligence.')
        );
        $this->assertNotEmpty($piPaths, 'marketing_angles allowed_context must include property_intelligence dot-paths');
    }

    // =========================================================================
    // Step 3E — Contract layer: property_standout with PI absent → insufficient_context
    // =========================================================================

    public function test_step3E_contract_layer_property_standout_without_intelligence_returns_insufficient_context(): void
    {
        $service = new AskAiResponseContractService();
        $context = $this->makeContextWithoutPropertyIntelligence();

        $result = $service->buildContract('property_standout', $context);

        $this->assertSame('insufficient_context', $result['status']);
        $this->assertFalse($result['success']);
    }

    public function test_step3E_contract_layer_property_standout_insufficient_context_lists_property_intelligence_in_missing_sources(): void
    {
        $service = new AskAiResponseContractService();
        $context = $this->makeContextWithoutPropertyIntelligence();

        $result = $service->buildContract('property_standout', $context);

        $this->assertContains(
            'property_intelligence',
            $result['missing_required_sources'],
            "'property_intelligence' must appear in missing_required_sources when PI is absent"
        );
    }

    public function test_step3E_contract_layer_property_standout_with_null_intelligence_returns_insufficient_context(): void
    {
        $service = new AskAiResponseContractService();
        $context = $this->makeAssembledSellerContext(['property_intelligence' => null]);

        $result = $service->buildContract('property_standout', $context);

        $this->assertSame('insufficient_context', $result['status']);
        $this->assertContains('property_intelligence', $result['missing_required_sources']);
    }

    // =========================================================================
    // Step 3F — Contract layer: marketing_angles with PI absent → insufficient_context
    // =========================================================================

    public function test_step3F_contract_layer_marketing_angles_without_intelligence_returns_insufficient_context(): void
    {
        $service = new AskAiResponseContractService();
        $context = $this->makeContextWithoutPropertyIntelligence();

        $result = $service->buildContract('marketing_angles', $context);

        $this->assertSame('insufficient_context', $result['status']);
        $this->assertFalse($result['success']);
        $this->assertContains('property_intelligence', $result['missing_required_sources']);
    }

    public function test_step3F_contract_layer_marketing_angles_with_null_intelligence_returns_insufficient_context(): void
    {
        $service = new AskAiResponseContractService();
        $context = $this->makeAssembledSellerContext(['property_intelligence' => null]);

        $result = $service->buildContract('marketing_angles', $context);

        $this->assertSame('insufficient_context', $result['status']);
        $this->assertContains('property_intelligence', $result['missing_required_sources']);
    }

    // =========================================================================
    // Step 4G — Prompt layer: contract_ready for property_standout → prompt_ready
    // =========================================================================

    public function test_step4G_prompt_layer_contract_ready_property_standout_produces_prompt_ready(): void
    {
        $service  = new AskAiPromptBuilderService();
        $context  = $this->makeAssembledSellerContext();
        $contract = (new AskAiResponseContractService())->buildContract('property_standout', $context);

        $result = $service->buildPromptPackage('What makes this property stand out?', $context, $contract);

        $this->assertSame('prompt_ready', $result['status']);
        $this->assertTrue($result['success']);
    }

    // =========================================================================
    // Step 4H — Prompt layer: source_attribution.required_sources includes PI
    // =========================================================================

    public function test_step4H_prompt_layer_source_attribution_required_sources_includes_property_intelligence(): void
    {
        $service  = new AskAiPromptBuilderService();
        $context  = $this->makeAssembledSellerContext();
        $contract = (new AskAiResponseContractService())->buildContract('property_standout', $context);

        $result = $service->buildPromptPackage('What makes this property stand out?', $context, $contract);

        $this->assertArrayHasKey('source_attribution', $result);
        $this->assertArrayHasKey('required_sources', $result['source_attribution']);
        $this->assertContains(
            'property_intelligence',
            $result['source_attribution']['required_sources'],
            "source_attribution.required_sources must include 'property_intelligence'"
        );
    }

    public function test_step4H_prompt_layer_source_attribution_required_sources_includes_pi_for_marketing_angles(): void
    {
        $service  = new AskAiPromptBuilderService();
        $context  = $this->makeAssembledSellerContext();
        $contract = (new AskAiResponseContractService())->buildContract('marketing_angles', $context);

        $result = $service->buildPromptPackage('What marketing angles apply?', $context, $contract);

        $this->assertSame('prompt_ready', $result['status']);
        $this->assertContains(
            'property_intelligence',
            $result['source_attribution']['required_sources']
        );
    }

    // =========================================================================
    // Step 4I — Prompt layer: source_attribution.versions carries PI version key
    // =========================================================================

    public function test_step4I_prompt_layer_source_attribution_versions_carries_property_intelligence_version_key(): void
    {
        $service  = new AskAiPromptBuilderService();
        $context  = $this->makeAssembledSellerContext();
        $contract = (new AskAiResponseContractService())->buildContract('property_standout', $context);

        $result = $service->buildPromptPackage('What makes this property stand out?', $context, $contract);

        $this->assertArrayHasKey('versions', $result['source_attribution']);
        $this->assertArrayHasKey(
            'property_intelligence_version',
            $result['source_attribution']['versions'],
            "source_attribution.versions must carry 'property_intelligence_version' key"
        );
    }

    public function test_step4I_prompt_layer_source_attribution_versions_property_intelligence_version_is_populated(): void
    {
        $service  = new AskAiPromptBuilderService();
        $context  = $this->makeAssembledSellerContext();
        $contract = (new AskAiResponseContractService())->buildContract('property_standout', $context);

        $result = $service->buildPromptPackage('What makes this property stand out?', $context, $contract);

        $this->assertSame(
            'PROPERTY_INTELLIGENCE_V1',
            $result['source_attribution']['versions']['property_intelligence_version']
        );
    }

    // =========================================================================
    // Step 4J — Prompt layer: insufficient_context contract → non-prompt_ready status
    // =========================================================================

    public function test_step4J_prompt_layer_insufficient_context_contract_produces_non_prompt_ready_status(): void
    {
        $service  = new AskAiPromptBuilderService();
        $context  = $this->makeContextWithoutPropertyIntelligence();
        $contract = (new AskAiResponseContractService())->buildContract('property_standout', $context);

        $this->assertSame('insufficient_context', $contract['status']);

        $result = $service->buildPromptPackage('What makes this property stand out?', $context, $contract);

        $this->assertNotSame('prompt_ready', $result['status']);
        $this->assertFalse($result['success']);
    }

    public function test_step4J_prompt_layer_insufficient_context_status_propagates_correctly(): void
    {
        $service  = new AskAiPromptBuilderService();
        $context  = $this->makeContextWithoutPropertyIntelligence();
        $contract = (new AskAiResponseContractService())->buildContract('marketing_angles', $context);

        $result = $service->buildPromptPackage('What marketing angles apply?', $context, $contract);

        $this->assertSame('insufficient_context', $result['status']);
    }

    // =========================================================================
    // Step 4K — Prompt layer: allowed_context filtered to PI dot-paths; no bleed-through
    // =========================================================================

    public function test_step4K_prompt_layer_allowed_context_contains_only_pi_dot_paths_from_contract(): void
    {
        $service  = new AskAiPromptBuilderService();
        $context  = $this->makeAssembledSellerContext();
        $contract = (new AskAiResponseContractService())->buildContract('property_standout', $context);

        $result = $service->buildPromptPackage('What makes this property stand out?', $context, $contract);

        $this->assertSame('prompt_ready', $result['status']);

        $allowedContext = $result['allowed_context'];

        // All returned top-level keys must be sanctioned by the property_standout contract.
        // property_standout allowed paths: property_intelligence.*, listing.*, location_intelligence.*
        $allowedTopKeys = array_unique(array_map(
            static fn(string $path) => explode('.', $path)[0],
            $contract['allowed_context']
        ));

        foreach (array_keys($allowedContext) as $topKey) {
            $this->assertContains(
                $topKey,
                $allowedTopKeys,
                "allowed_context contains unrestricted top-level key '{$topKey}' — context bleed-through detected"
            );
        }
    }

    public function test_step4K_prompt_layer_allowed_context_does_not_bleed_through_buyer_avatar_or_tenant_avatar(): void
    {
        $service  = new AskAiPromptBuilderService();
        $context  = $this->makeAssembledSellerContext();
        $contract = (new AskAiResponseContractService())->buildContract('property_standout', $context);

        $result = $service->buildPromptPackage('What makes this property stand out?', $context, $contract);

        $this->assertArrayNotHasKey('buyer_avatar', $result['allowed_context']);
        $this->assertArrayNotHasKey('tenant_avatar', $result['allowed_context']);
        $this->assertArrayNotHasKey('compatibility', $result['allowed_context']);
        $this->assertArrayNotHasKey('offer_analysis', $result['allowed_context']);
    }

    public function test_step4K_prompt_layer_allowed_context_for_property_standout_includes_pi_subkeys(): void
    {
        $service  = new AskAiPromptBuilderService();
        $context  = $this->makeAssembledSellerContext();
        $contract = (new AskAiResponseContractService())->buildContract('property_standout', $context);

        $result = $service->buildPromptPackage('What makes this property stand out?', $context, $contract);

        $this->assertArrayHasKey('property_intelligence', $result['allowed_context']);

        $pi = $result['allowed_context']['property_intelligence'];
        $this->assertArrayHasKey('property_highlights', $pi);
        $this->assertArrayHasKey('property_strengths', $pi);
        $this->assertArrayHasKey('property_story', $pi);
    }

    // =========================================================================
    // Step 5L — End-to-end: Context → Contract → Prompt for property_standout
    // =========================================================================

    public function test_step5L_end_to_end_property_standout_chain_produces_prompt_ready_with_source_attribution(): void
    {
        // Wire the context builder with mocked finders.
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')
            ->willReturn($this->makeIntelligencePayload());

        $contextBuilder = $this->makeContextService($intelligenceService);
        $contextBuilder->method('findListing')->willReturn($this->makeListingStub());
        $contextBuilder->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfile());
        $contextBuilder->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        // Phase 1: assemble context.
        $context = $contextBuilder->buildForListing('seller', 1);
        $this->assertTrue($context['success'], 'Context assembly must succeed');
        $this->assertNotNull($context['property_intelligence'], 'Context must include property_intelligence');

        // Phase 2: build contract.
        $contractService = new AskAiResponseContractService();
        $contract        = $contractService->buildContract('property_standout', $context);
        $this->assertSame('contract_ready', $contract['status'], 'Contract must be contract_ready');

        // Phase 3: build prompt package.
        $promptService = new AskAiPromptBuilderService();
        $prompt        = $promptService->buildPromptPackage(
            'What makes this property stand out?',
            $context,
            $contract
        );

        // Assert final prompt package is prompt_ready.
        $this->assertSame('prompt_ready', $prompt['status']);
        $this->assertTrue($prompt['success']);

        // Assert source_attribution mentions property_intelligence.
        $this->assertContains('property_intelligence', $prompt['source_attribution']['required_sources']);
        $this->assertArrayHasKey('property_intelligence_version', $prompt['source_attribution']['versions']);
    }

    public function test_step5L_end_to_end_chain_context_version_propagates_through_to_prompt(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')
            ->willReturn($this->makeIntelligencePayload());

        $contextBuilder = $this->makeContextService($intelligenceService);
        $contextBuilder->method('findListing')->willReturn($this->makeListingStub());
        $contextBuilder->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfile());
        $contextBuilder->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $context  = $contextBuilder->buildForListing('seller', 1);
        $contract = (new AskAiResponseContractService())->buildContract('property_standout', $context);
        $prompt   = (new AskAiPromptBuilderService())->buildPromptPackage('q', $context, $contract);

        $this->assertSame('ASK_AI_CONTEXT_V1', $prompt['context_versions']['ask_ai_context']);
        $this->assertSame('PROPERTY_INTELLIGENCE_V1', $prompt['context_versions']['property_intelligence_version']);
        $this->assertSame('ASK_AI_RESPONSE_CONTRACT_V1', $prompt['context_versions']['contract_version']);
    }

    // =========================================================================
    // Step 6 (extra) — missing_data surfaces missing_sources from context
    // =========================================================================

    public function test_missing_data_with_listing_present_and_pi_absent_returns_contract_ready(): void
    {
        $service = new AskAiResponseContractService();
        $context = $this->makeContextWithoutPropertyIntelligence();

        // missing_data only requires 'listing' as required source, not property_intelligence.
        $result = $service->buildContract('missing_data', $context);

        $this->assertSame('contract_ready', $result['status']);
        $this->assertTrue($result['success']);
    }

    public function test_missing_data_allowed_context_includes_missing_sources_path(): void
    {
        $service = new AskAiResponseContractService();
        $context = $this->makeContextWithoutPropertyIntelligence();

        $result = $service->buildContract('missing_data', $context);

        $this->assertContains(
            'missing_sources',
            $result['allowed_context'],
            "missing_data allowed_context must include the 'missing_sources' path"
        );
    }

    public function test_missing_data_prompt_package_surfaces_pi_as_missing_context(): void
    {
        $service  = new AskAiPromptBuilderService();
        $context  = $this->makeContextWithoutPropertyIntelligence();
        $contract = (new AskAiResponseContractService())->buildContract('missing_data', $context);

        $this->assertSame('contract_ready', $contract['status']);

        $prompt = $service->buildPromptPackage('What data is missing for this listing?', $context, $contract);

        $this->assertSame('prompt_ready', $prompt['status']);

        // The allowed_context should surface missing_sources from the context
        $this->assertArrayHasKey('missing_sources', $prompt['allowed_context']);
        $this->assertContains('property_intelligence', $prompt['allowed_context']['missing_sources']);
    }

    // =========================================================================
    // Step 6M — Governance: no write calls in any of the four service files
    // =========================================================================

    /**
     * Strip comment lines so governance docblock keywords do not false-positive.
     */
    private function stripCommentLines(string $source): string
    {
        return implode("\n", array_filter(
            explode("\n", $source),
            static function (string $line): bool {
                $trimmed = ltrim($line);
                return !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
            }
        ));
    }

    public function test_step6M_AskAiContextBuilderService_contains_no_write_calls(): void
    {
        $path = $this->serviceDir() . 'AskAiContextBuilderService.php';
        $this->assertFileExists($path);

        $code = $this->stripCommentLines(file_get_contents($path));

        foreach (['->save(', '->create(', '->update(', '->delete(', 'DB::insert', 'DB::update', 'DB::delete'] as $call) {
            $this->assertStringNotContainsString(
                $call,
                $code,
                "AskAiContextBuilderService must not contain write call '{$call}'"
            );
        }
    }

    public function test_step6M_AskAiResponseContractService_contains_no_write_calls(): void
    {
        $path = $this->serviceDir() . 'AskAiResponseContractService.php';
        $this->assertFileExists($path);

        $code = $this->stripCommentLines(file_get_contents($path));

        foreach (['->save(', '->create(', '->update(', '->delete(', 'DB::insert', 'DB::update', 'DB::delete'] as $call) {
            $this->assertStringNotContainsString(
                $call,
                $code,
                "AskAiResponseContractService must not contain write call '{$call}'"
            );
        }
    }

    public function test_step6M_AskAiPromptBuilderService_contains_no_write_calls(): void
    {
        $path = $this->serviceDir() . 'AskAiPromptBuilderService.php';
        $this->assertFileExists($path);

        $code = $this->stripCommentLines(file_get_contents($path));

        foreach (['->save(', '->create(', '->update(', '->delete(', 'DB::insert', 'DB::update', 'DB::delete'] as $call) {
            $this->assertStringNotContainsString(
                $call,
                $code,
                "AskAiPromptBuilderService must not contain write call '{$call}'"
            );
        }
    }

    public function test_step6M_AskAiKnowledgeSourceRegistry_contains_no_write_calls(): void
    {
        $path = $this->serviceDir() . 'AskAiKnowledgeSourceRegistry.php';
        $this->assertFileExists($path);

        $code = $this->stripCommentLines(file_get_contents($path));

        foreach (['->save(', '->create(', '->update(', '->delete(', 'DB::insert', 'DB::update', 'DB::delete'] as $call) {
            $this->assertStringNotContainsString(
                $call,
                $code,
                "AskAiKnowledgeSourceRegistry must not contain write call '{$call}'"
            );
        }
    }
}
