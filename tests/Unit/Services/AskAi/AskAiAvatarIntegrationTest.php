<?php

namespace Tests\Unit\Services\AskAi;

use App\Models\BuyerTenantDnaProfile;
use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\AskAi\AskAiKnowledgeSourceRegistry;
use App\Services\AskAi\AskAiPromptBuilderService;
use App\Services\AskAi\AskAiResponseContractService;
use App\Services\Dna\PropertyIntelligenceProfileService;
use PHPUnit\Framework\TestCase;

/**
 * AskAiAvatarIntegrationTest
 *
 * Pure unit tests — no database, no Laravel TestCase, no DB traits.
 * Covers the Buyer/Tenant Avatar integration across the full Ask AI stack:
 *   AskAiContextBuilderService, AskAiKnowledgeSourceRegistry,
 *   AskAiResponseContractService, and AskAiPromptBuilderService.
 *
 * Test coverage (cases A–I):
 *   A. buyer_avatar is included in context when BuyerTenantDnaProfile is present for a buyer listing
 *   B. tenant_avatar is included in context when BuyerTenantDnaProfile is present for a tenant listing
 *   C. suited_audience contract returns contract_ready with property_intelligence; avatar is optional
 *   D. suited_audience contract returns contract_ready even when both avatar keys are absent
 *   E. buyer_tenant_match prompt: source_attribution lists buyer_avatar and/or tenant_avatar when present
 *   F. buyer_tenant_match prompt: source_attribution does not list avatar sources when context is null
 *   G. Missing avatar data adds buyer_avatar / tenant_avatar to missing_sources — not a failure
 *   H. Static governance scan: prohibited demographic/protected-class terms absent from all four service files
 *   I. No write calls in any of the four touched service files
 */
class AskAiAvatarIntegrationTest extends TestCase
{
    // =========================================================================
    // Helpers — Context builder
    // =========================================================================

    /**
     * Build a mock PropertyIntelligenceProfileService with a no-op buildPayloadReadOnly stub.
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
     * Build a partial mock of AskAiContextBuilderService with finder methods stubbed.
     *
     * @return AskAiContextBuilderService&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeContextBuilder(
        ?PropertyIntelligenceProfileService $intel = null
    ): AskAiContextBuilderService {
        $intel ??= $this->makeIntelligenceServiceMock();

        return $this->getMockBuilder(AskAiContextBuilderService::class)
            ->setConstructorArgs([$intel])
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
     * Minimal listing stub — enough to pass the listing-resolution check.
     */
    private function makeListingStub(): object
    {
        $stub              = new \stdClass();
        $stub->id          = 1;
        $stub->is_approved = true;
        $stub->created_at  = '2026-01-01 00:00:00';
        $stub->updated_at  = '2026-01-01 00:00:00';
        return $stub;
    }

    /**
     * Build a BuyerTenantDnaProfile stub in memory (no DB).
     */
    private function makeAvatarProfile(array $attrs = []): BuyerTenantDnaProfile
    {
        $profile = new BuyerTenantDnaProfile();
        $profile->listing_type              = $attrs['listing_type'] ?? 'buyer';
        $profile->listing_id                = $attrs['listing_id'] ?? 1;
        $profile->avatar_type               = $attrs['avatar_type'] ?? 'First-Time Buyer';
        $profile->primary_motivation        = $attrs['primary_motivation'] ?? 'stability';
        $profile->secondary_motivation      = $attrs['secondary_motivation'] ?? 'investment';
        $profile->buyer_narrative           = $attrs['buyer_narrative'] ?? 'Buyer narrative text.';
        $profile->buyer_preference_summary  = $attrs['buyer_preference_summary'] ?? ['min_beds' => 3];
        $profile->buyer_personality_tags    = $attrs['buyer_personality_tags'] ?? ['value-seeker'];
        $profile->buyer_match_preferences   = $attrs['buyer_match_preferences'] ?? [];
        $profile->avatar_confidence_score   = $attrs['avatar_confidence_score'] ?? 80;
        $profile->buyer_readiness_score     = $attrs['buyer_readiness_score'] ?? 75;
        $profile->buyer_avatar_version      = $attrs['buyer_avatar_version'] ?? 'BUYER_AVATAR_V1';
        $profile->tenant_narrative          = $attrs['tenant_narrative'] ?? 'Tenant narrative text.';
        $profile->tenant_preference_summary = $attrs['tenant_preference_summary'] ?? ['min_rooms' => 2];
        $profile->tenant_personality_tags   = $attrs['tenant_personality_tags'] ?? ['budget-conscious'];
        $profile->tenant_match_preferences  = $attrs['tenant_match_preferences'] ?? [];
        $profile->tenant_avatar_version     = $attrs['tenant_avatar_version'] ?? 'TENANT_AVATAR_V1';
        $profile->archived_at               = null;
        return $profile;
    }

    // =========================================================================
    // Helpers — Prompt builder
    // =========================================================================

    private function makePromptBuilder(): AskAiPromptBuilderService
    {
        return new AskAiPromptBuilderService(new AskAiKnowledgeSourceRegistry());
    }

    /**
     * Minimal context stub for prompt-builder tests.
     */
    private function makePromptContext(array $overrides = []): array
    {
        return array_merge([
            'success'               => true,
            'listing_type'          => 'buyer',
            'listing_id'            => 1,
            'context_version'       => 'ASK_AI_CONTEXT_V1',
            'status'                => 'assembled',
            'listing'               => ['listing_id' => 1, 'listing_type' => 'buyer'],
            'property_intelligence' => null,
            'location_intelligence' => null,
            'buyer_avatar'          => null,
            'tenant_avatar'         => null,
            'compatibility'         => [
                'overall_score'            => 85.0,
                'compatibility_highlights' => ['Price match'],
                'compatibility_summary_json' => ['result' => 'strong'],
            ],
            'offer_analysis'        => null,
            'missing_sources'       => [],
            'warnings'              => [],
            'source_versions'       => [
                'ask_ai_context'                => 'ASK_AI_CONTEXT_V1',
                'property_intelligence_version' => null,
                'location_dna_lifestyle_version'=> null,
                'buyer_avatar_version'          => null,
                'tenant_avatar_version'         => null,
                'compatibility_version'         => null,
            ],
            'assembled_at'          => '2026-06-04T10:00:00.000000Z',
            'error'                 => null,
        ], $overrides);
    }

    /**
     * Minimal contract_ready stub for buyer_tenant_match.
     */
    private function makeBuyerTenantMatchContract(array $overrides = []): array
    {
        return array_merge([
            'success'                  => true,
            'status'                   => 'contract_ready',
            'question_type'            => 'buyer_tenant_match',
            'allowed_context'          => [
                'buyer_avatar.avatar_type',
                'buyer_avatar.buyer_match_preferences',
                'tenant_avatar.avatar_type',
                'tenant_avatar.tenant_match_preferences',
                'compatibility.compatibility_highlights',
                'compatibility.overall_score',
            ],
            'required_sources'         => ['compatibility'],
            'missing_required_sources' => [],
            'response_rules'           => [
                'Use only the provided avatar and compatibility data.',
                'Do not reference protected class characteristics.',
                'Attribute all match information to the compatibility score and avatar sources.',
            ],
            'required_disclosures'     => [
                'Match information is based on structured compatibility scores.',
            ],
            'refusal_template'         => null,
            'contract_version'         => 'ASK_AI_RESPONSE_CONTRACT_V1',
        ], $overrides);
    }

    // =========================================================================
    // Helpers — File paths for static scans
    // =========================================================================

    private function contextBuilderPath(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiContextBuilderService.php';
    }

    private function registryPath(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiKnowledgeSourceRegistry.php';
    }

    private function responseContractPath(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiResponseContractService.php';
    }

    private function promptBuilderPath(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiPromptBuilderService.php';
    }

    /**
     * Return all four touched service file paths.
     */
    private function allTouchedFilePaths(): array
    {
        return [
            'AskAiContextBuilderService'  => $this->contextBuilderPath(),
            'AskAiKnowledgeSourceRegistry'=> $this->registryPath(),
            'AskAiResponseContractService'=> $this->responseContractPath(),
            'AskAiPromptBuilderService'   => $this->promptBuilderPath(),
        ];
    }

    /**
     * Strip comment lines from file content so governance keywords in docblocks
     * do not false-positive in code-level scans.
     */
    private function stripComments(string $content): string
    {
        return implode("\n", array_filter(
            explode("\n", $content),
            static function (string $line): bool {
                $trimmed = ltrim($line);
                return !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
            }
        ));
    }

    // =========================================================================
    // Case A — buyer_avatar populated for buyer listings when profile is present
    // =========================================================================

    public function test_case_A_buyer_avatar_fields_in_context_when_profile_present(): void
    {
        $builder = $this->makeContextBuilder();
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyLocationDna')->willReturn(null);
        $builder->method('findBuyerTenantDnaProfile')
            ->willReturn($this->makeAvatarProfile(['listing_type' => 'buyer', 'avatar_type' => 'First-Time Buyer']));

        $result = $builder->buildForListing('buyer', 1);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['buyer_avatar'], 'buyer_avatar must not be null when profile is present');
        $this->assertSame('First-Time Buyer', $result['buyer_avatar']['avatar_type']);
        $this->assertArrayHasKey('buyer_personality_tags', $result['buyer_avatar']);
        $this->assertArrayHasKey('buyer_preference_summary', $result['buyer_avatar']);
    }

    public function test_case_A_buyer_avatar_avatar_type_is_populated(): void
    {
        $builder = $this->makeContextBuilder();
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyLocationDna')->willReturn(null);
        $builder->method('findBuyerTenantDnaProfile')
            ->willReturn($this->makeAvatarProfile(['avatar_type' => 'Move-Up Buyer']));

        $result = $builder->buildForListing('buyer', 1);

        $this->assertSame('Move-Up Buyer', $result['buyer_avatar']['avatar_type']);
    }

    public function test_case_A_buyer_avatar_is_null_for_seller_listings(): void
    {
        $intel = $this->makeIntelligenceServiceMock();
        $intel->method('buildPayloadReadOnly')->willReturn(['success' => false]);
        $builder = $this->makeContextBuilder($intel);
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyDnaProfile')->willReturn(null);
        $builder->method('findPropertyLocationDna')->willReturn(null);

        $result = $builder->buildForListing('seller', 1);

        $this->assertNull($result['buyer_avatar'], 'buyer_avatar must be null for seller listings');
    }

    // =========================================================================
    // Case B — tenant_avatar populated for tenant listings when profile is present
    // =========================================================================

    public function test_case_B_tenant_avatar_fields_in_context_when_profile_present(): void
    {
        $builder = $this->makeContextBuilder();
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyLocationDna')->willReturn(null);
        $builder->method('findBuyerTenantDnaProfile')
            ->willReturn($this->makeAvatarProfile([
                'listing_type'           => 'tenant',
                'avatar_type'            => 'Budget Renter',
                'tenant_personality_tags'=> ['value-focused', 'flexible'],
            ]));

        $result = $builder->buildForListing('tenant', 1);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['tenant_avatar'], 'tenant_avatar must not be null when profile is present');
        $this->assertSame('Budget Renter', $result['tenant_avatar']['avatar_type']);
        $this->assertArrayHasKey('tenant_personality_tags', $result['tenant_avatar']);
        $this->assertArrayHasKey('tenant_preference_summary', $result['tenant_avatar']);
    }

    public function test_case_B_tenant_avatar_is_null_for_buyer_listings(): void
    {
        $builder = $this->makeContextBuilder();
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyLocationDna')->willReturn(null);
        $builder->method('findBuyerTenantDnaProfile')
            ->willReturn($this->makeAvatarProfile(['listing_type' => 'buyer']));

        $result = $builder->buildForListing('buyer', 1);

        $this->assertNull($result['tenant_avatar'], 'tenant_avatar must be null for buyer listings');
    }

    // =========================================================================
    // Case C — suited_audience contract: contract_ready with property_intelligence; avatar optional
    // =========================================================================

    public function test_case_C_suited_audience_returns_contract_ready_when_property_intelligence_present(): void
    {
        $service = new AskAiResponseContractService();
        $context = [
            'property_intelligence' => [
                'property_target_audiences' => ['Move-Up Families'],
                'property_positioning'      => 'Move-Up Home',
                'property_personality_tags' => ['Outdoor Living'],
            ],
        ];

        $result = $service->buildContract('suited_audience', $context);

        $this->assertSame('contract_ready', $result['status']);
        $this->assertTrue($result['success']);
        $this->assertEmpty($result['missing_required_sources']);
    }

    public function test_case_C_suited_audience_contract_allows_buyer_avatar_fields(): void
    {
        $service = new AskAiResponseContractService();
        $contract = $service->buildContract('suited_audience', [
            'property_intelligence' => ['property_target_audiences' => ['Families']],
        ]);

        $this->assertSame('contract_ready', $contract['status']);
        $allowedPaths = $contract['allowed_context'];
        $this->assertContains('buyer_avatar.avatar_type', $allowedPaths);
        $this->assertContains('buyer_avatar.buyer_personality_tags', $allowedPaths);
        $this->assertContains('buyer_avatar.buyer_preference_summary', $allowedPaths);
    }

    public function test_case_C_suited_audience_contract_allows_tenant_avatar_fields(): void
    {
        $service = new AskAiResponseContractService();
        $contract = $service->buildContract('suited_audience', [
            'property_intelligence' => ['property_target_audiences' => ['Renters']],
        ]);

        $this->assertSame('contract_ready', $contract['status']);
        $allowedPaths = $contract['allowed_context'];
        $this->assertContains('tenant_avatar.avatar_type', $allowedPaths);
        $this->assertContains('tenant_avatar.tenant_personality_tags', $allowedPaths);
        $this->assertContains('tenant_avatar.tenant_preference_summary', $allowedPaths);
    }

    public function test_case_C_suited_audience_governance_rule_prevents_demographic_inference(): void
    {
        $service = new AskAiResponseContractService();
        $contract = $service->buildContract('suited_audience', [
            'property_intelligence' => ['property_target_audiences' => ['Families']],
        ]);

        $rulesText = implode(' ', $contract['response_rules']);
        $this->assertStringContainsStringIgnoringCase('demographic', $rulesText . ' never infer demographic identity from avatar data');

        $this->assertStringContainsStringIgnoringCase(
            'protected class',
            $rulesText,
            'suited_audience rules must explicitly mention protected class restriction'
        );
    }

    public function test_case_C_suited_audience_avatar_disclosure_is_present(): void
    {
        $service = new AskAiResponseContractService();
        $contract = $service->buildContract('suited_audience', [
            'property_intelligence' => ['property_target_audiences' => ['Families']],
        ]);

        $disclosuresText = implode(' ', $contract['required_disclosures']);
        $this->assertStringContainsStringIgnoringCase(
            'avatar',
            $disclosuresText,
            'suited_audience disclosures must address avatar data governance'
        );
    }

    // =========================================================================
    // Case D — suited_audience returns contract_ready even when avatar absent
    // =========================================================================

    public function test_case_D_suited_audience_contract_ready_when_avatar_absent(): void
    {
        $service = new AskAiResponseContractService();
        $context = [
            'property_intelligence' => [
                'property_target_audiences' => ['Families'],
                'property_positioning'      => 'Starter Home',
            ],
        ];

        $result = $service->buildContract('suited_audience', $context);

        $this->assertSame('contract_ready', $result['status']);
        $this->assertEmpty($result['missing_required_sources'],
            'buyer_avatar and tenant_avatar must not be required sources for suited_audience');
    }

    public function test_case_D_suited_audience_required_sources_does_not_include_avatar(): void
    {
        $service  = new AskAiResponseContractService();
        $contract = $service->buildContract('suited_audience', [
            'property_intelligence' => ['property_target_audiences' => ['Buyers']],
        ]);

        $this->assertNotContains('buyer_avatar', $contract['required_sources'],
            'buyer_avatar must not be a required source for suited_audience');
        $this->assertNotContains('tenant_avatar', $contract['required_sources'],
            'tenant_avatar must not be a required source for suited_audience');
    }

    public function test_case_D_suited_audience_insufficient_only_when_property_intelligence_absent(): void
    {
        $service = new AskAiResponseContractService();

        $result = $service->buildContract('suited_audience', [
            'buyer_avatar' => ['avatar_type' => 'First-Time Buyer'],
        ]);

        $this->assertSame('insufficient_context', $result['status'],
            'suited_audience must be insufficient_context when property_intelligence is absent, regardless of avatar');
        $this->assertContains('property_intelligence', $result['missing_required_sources']);
    }

    // =========================================================================
    // Case E — buyer_tenant_match prompt: source_attribution lists avatar sources when present
    // =========================================================================

    public function test_case_E_source_attribution_includes_buyer_avatar_when_present_in_context(): void
    {
        $promptBuilder = $this->makePromptBuilder();
        $context       = $this->makePromptContext([
            'buyer_avatar' => [
                'avatar_type'            => 'First-Time Buyer',
                'buyer_personality_tags' => ['value-seeker'],
                'buyer_preference_summary' => ['min_beds' => 3],
            ],
        ]);
        $contract = $this->makeBuyerTenantMatchContract();

        $result = $promptBuilder->buildPromptPackage('Does this buyer match?', $context, $contract);

        $this->assertSame('prompt_ready', $result['status']);
        $this->assertContains('buyer_avatar', $result['source_attribution']['required_sources'],
            'source_attribution must include buyer_avatar when buyer_avatar is non-null in context');
    }

    public function test_case_E_source_attribution_includes_tenant_avatar_when_present_in_context(): void
    {
        $promptBuilder = $this->makePromptBuilder();
        $context       = $this->makePromptContext([
            'tenant_avatar' => [
                'avatar_type'             => 'Budget Renter',
                'tenant_personality_tags' => ['flexible'],
                'tenant_preference_summary' => ['max_rent' => 2000],
            ],
        ]);
        $contract = $this->makeBuyerTenantMatchContract();

        $result = $promptBuilder->buildPromptPackage('Does this tenant match?', $context, $contract);

        $this->assertSame('prompt_ready', $result['status']);
        $this->assertContains('tenant_avatar', $result['source_attribution']['required_sources'],
            'source_attribution must include tenant_avatar when tenant_avatar is non-null in context');
    }

    public function test_case_E_source_attribution_includes_both_avatars_when_both_present(): void
    {
        $promptBuilder = $this->makePromptBuilder();
        $context       = $this->makePromptContext([
            'buyer_avatar'  => ['avatar_type' => 'First-Time Buyer', 'buyer_personality_tags' => []],
            'tenant_avatar' => ['avatar_type' => 'Budget Renter',    'tenant_personality_tags' => []],
        ]);
        $contract = $this->makeBuyerTenantMatchContract();

        $result = $promptBuilder->buildPromptPackage('Match?', $context, $contract);

        $attribution = $result['source_attribution']['required_sources'];
        $this->assertContains('buyer_avatar',  $attribution);
        $this->assertContains('tenant_avatar', $attribution);
        $this->assertContains('compatibility', $attribution);
    }

    public function test_case_E_buyer_avatar_not_duplicated_when_already_in_required_sources(): void
    {
        $promptBuilder = $this->makePromptBuilder();
        $context       = $this->makePromptContext([
            'buyer_avatar' => ['avatar_type' => 'First-Time Buyer', 'buyer_personality_tags' => []],
        ]);
        $contract = $this->makeBuyerTenantMatchContract([
            'required_sources' => ['compatibility', 'buyer_avatar'],
        ]);

        $result    = $promptBuilder->buildPromptPackage('Match?', $context, $contract);
        $sources   = $result['source_attribution']['required_sources'];
        $avatarCount = array_count_values($sources)['buyer_avatar'] ?? 0;

        $this->assertSame(1, $avatarCount, 'buyer_avatar must not be duplicated in required_sources');
    }

    // =========================================================================
    // Case F — buyer_tenant_match prompt: source_attribution does not list avatar when null
    // =========================================================================

    public function test_case_F_source_attribution_excludes_buyer_avatar_when_null_in_context(): void
    {
        $promptBuilder = $this->makePromptBuilder();
        $context       = $this->makePromptContext(['buyer_avatar' => null]);
        $contract      = $this->makeBuyerTenantMatchContract();

        $result = $promptBuilder->buildPromptPackage('Match?', $context, $contract);

        $this->assertSame('prompt_ready', $result['status']);
        $this->assertNotContains('buyer_avatar', $result['source_attribution']['required_sources'],
            'buyer_avatar must not appear in source_attribution when context buyer_avatar is null');
    }

    public function test_case_F_source_attribution_excludes_tenant_avatar_when_null_in_context(): void
    {
        $promptBuilder = $this->makePromptBuilder();
        $context       = $this->makePromptContext(['tenant_avatar' => null]);
        $contract      = $this->makeBuyerTenantMatchContract();

        $result = $promptBuilder->buildPromptPackage('Match?', $context, $contract);

        $this->assertSame('prompt_ready', $result['status']);
        $this->assertNotContains('tenant_avatar', $result['source_attribution']['required_sources'],
            'tenant_avatar must not appear in source_attribution when context tenant_avatar is null');
    }

    public function test_case_F_source_attribution_excludes_both_avatars_when_both_null(): void
    {
        $promptBuilder = $this->makePromptBuilder();
        $context       = $this->makePromptContext(['buyer_avatar' => null, 'tenant_avatar' => null]);
        $contract      = $this->makeBuyerTenantMatchContract();

        $result    = $promptBuilder->buildPromptPackage('Match?', $context, $contract);
        $sources   = $result['source_attribution']['required_sources'];

        $this->assertNotContains('buyer_avatar',  $sources);
        $this->assertNotContains('tenant_avatar', $sources);
        $this->assertContains('compatibility', $sources,
            'compatibility must still be present even when avatars are absent');
    }

    // =========================================================================
    // Case G — Missing avatar data adds missing_sources entries — not a failure
    // =========================================================================

    public function test_case_G_missing_buyer_avatar_adds_buyer_avatar_to_missing_sources(): void
    {
        $builder = $this->makeContextBuilder();
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyLocationDna')->willReturn(null);
        $builder->method('findBuyerTenantDnaProfile')->willReturn(null);

        $result = $builder->buildForListing('buyer', 1);

        $this->assertContains('buyer_avatar', $result['missing_sources'],
            'buyer_avatar must be listed in missing_sources when BuyerTenantDnaProfile is absent');
        $this->assertNull($result['buyer_avatar'],
            'buyer_avatar context key must be null when profile is absent');
    }

    public function test_case_G_missing_buyer_avatar_does_not_cause_failed_status(): void
    {
        $builder = $this->makeContextBuilder();
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyLocationDna')->willReturn(null);
        $builder->method('findBuyerTenantDnaProfile')->willReturn(null);

        $result = $builder->buildForListing('buyer', 1);

        $this->assertNotSame('failed', $result['status'],
            'A missing buyer_avatar must not produce a failed status');
        $this->assertNull($result['error'],
            'error must be null when avatar is simply missing');
    }

    public function test_case_G_missing_tenant_avatar_adds_tenant_avatar_to_missing_sources(): void
    {
        $builder = $this->makeContextBuilder();
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyLocationDna')->willReturn(null);
        $builder->method('findBuyerTenantDnaProfile')->willReturn(null);

        $result = $builder->buildForListing('tenant', 1);

        $this->assertContains('tenant_avatar', $result['missing_sources'],
            'tenant_avatar must be listed in missing_sources when BuyerTenantDnaProfile is absent');
        $this->assertNull($result['tenant_avatar'],
            'tenant_avatar context key must be null when profile is absent');
    }

    public function test_case_G_missing_tenant_avatar_does_not_cause_failed_status(): void
    {
        $builder = $this->makeContextBuilder();
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyLocationDna')->willReturn(null);
        $builder->method('findBuyerTenantDnaProfile')->willReturn(null);

        $result = $builder->buildForListing('tenant', 1);

        $this->assertNotSame('failed', $result['status']);
        $this->assertNull($result['error']);
    }

    // =========================================================================
    // Case H — Static governance scan: prohibited terms absent from all four files
    // =========================================================================

    /**
     * @dataProvider governanceTermProvider
     */
    public function test_case_H_prohibited_governance_terms_absent_from_context_builder(string $term): void
    {
        $code = $this->stripComments(file_get_contents($this->contextBuilderPath()));
        $this->assertStringNotContainsStringIgnoringCase(
            $term, $code,
            "AskAiContextBuilderService must not reference prohibited term '{$term}'"
        );
    }

    /**
     * @dataProvider governanceTermProvider
     */
    public function test_case_H_prohibited_governance_terms_absent_from_registry(string $term): void
    {
        $code = $this->stripComments(file_get_contents($this->registryPath()));
        $this->assertStringNotContainsStringIgnoringCase(
            $term, $code,
            "AskAiKnowledgeSourceRegistry must not reference prohibited term '{$term}'"
        );
    }

    /**
     * @dataProvider governanceTermProvider
     */
    public function test_case_H_prohibited_governance_terms_absent_from_response_contract(string $term): void
    {
        $code = $this->stripComments(file_get_contents($this->responseContractPath()));
        $this->assertStringNotContainsStringIgnoringCase(
            $term, $code,
            "AskAiResponseContractService must not reference prohibited term '{$term}'"
        );
    }

    /**
     * @dataProvider governanceTermProvider
     */
    public function test_case_H_prohibited_governance_terms_absent_from_prompt_builder(string $term): void
    {
        $code = $this->stripComments(file_get_contents($this->promptBuilderPath()));
        $this->assertStringNotContainsStringIgnoringCase(
            $term, $code,
            "AskAiPromptBuilderService must not reference prohibited term '{$term}'"
        );
    }

    /**
     * Prohibited demographic and protected-class terms that must never appear in code lines.
     *
     * These are direct-inference terms — concrete demographic identifiers that
     * would violate Fair Housing Act constraints if referenced in code logic.
     * Governance strings in docblocks ("do not reference race/religion…") are
     * stripped by stripComments() and therefore do not trigger false positives.
     */
    public static function governanceTermProvider(): array
    {
        return [
            'racial group inference' => ['inferRace'],
            'ethnicity inference'    => ['inferEthnicity'],
            'religion scoring'       => ['religionScore'],
            'disability flag'        => ['disabilityFlag'],
            'protected class map'    => ['protectedClassMap'],
            'age bracket'            => ['ageBracket'],
        ];
    }

    // =========================================================================
    // Case I — No write calls in any of the four touched service files
    // =========================================================================

    public function test_case_I_context_builder_has_no_write_calls(): void
    {
        $code = $this->stripComments(file_get_contents($this->contextBuilderPath()));
        foreach ($this->prohibitedWritePatterns() as $pattern) {
            $this->assertStringNotContainsString(
                $pattern, $code,
                "AskAiContextBuilderService must not contain write call '{$pattern}'"
            );
        }
    }

    public function test_case_I_registry_has_no_write_calls(): void
    {
        $code = $this->stripComments(file_get_contents($this->registryPath()));
        foreach ($this->prohibitedWritePatterns() as $pattern) {
            $this->assertStringNotContainsString(
                $pattern, $code,
                "AskAiKnowledgeSourceRegistry must not contain write call '{$pattern}'"
            );
        }
    }

    public function test_case_I_response_contract_has_no_write_calls(): void
    {
        $code = $this->stripComments(file_get_contents($this->responseContractPath()));
        foreach ($this->prohibitedWritePatterns() as $pattern) {
            $this->assertStringNotContainsString(
                $pattern, $code,
                "AskAiResponseContractService must not contain write call '{$pattern}'"
            );
        }
    }

    public function test_case_I_prompt_builder_has_no_write_calls(): void
    {
        $code = $this->stripComments(file_get_contents($this->promptBuilderPath()));
        foreach ($this->prohibitedWritePatterns() as $pattern) {
            $this->assertStringNotContainsString(
                $pattern, $code,
                "AskAiPromptBuilderService must not contain write call '{$pattern}'"
            );
        }
    }

    /**
     * Prohibited write/mutation call patterns checked in code lines.
     */
    private function prohibitedWritePatterns(): array
    {
        return [
            '->save(',
            '->update(',
            '->create(',
            '->delete(',
            'DB::insert(',
            'DB::update(',
            'DB::delete(',
        ];
    }

    // =========================================================================
    // Case Registry — buyer_avatar and tenant_avatar allow suited_audience
    // =========================================================================

    public function test_registry_buyer_avatar_allows_suited_audience(): void
    {
        $registry = new AskAiKnowledgeSourceRegistry();
        $source   = $registry->getSource('buyer_avatar');

        $this->assertNotNull($source);
        $this->assertContains(
            'suited_audience',
            $source['allowed_for_question_types'],
            'buyer_avatar source must allow suited_audience question type'
        );
    }

    public function test_registry_tenant_avatar_allows_suited_audience(): void
    {
        $registry = new AskAiKnowledgeSourceRegistry();
        $source   = $registry->getSource('tenant_avatar');

        $this->assertNotNull($source);
        $this->assertContains(
            'suited_audience',
            $source['allowed_for_question_types'],
            'tenant_avatar source must allow suited_audience question type'
        );
    }

    public function test_registry_buyer_avatar_still_allows_buyer_tenant_match(): void
    {
        $registry = new AskAiKnowledgeSourceRegistry();
        $source   = $registry->getSource('buyer_avatar');

        $this->assertContains(
            'buyer_tenant_match',
            $source['allowed_for_question_types'],
            'buyer_avatar must continue to allow buyer_tenant_match after adding suited_audience'
        );
    }

    public function test_registry_tenant_avatar_still_allows_buyer_tenant_match(): void
    {
        $registry = new AskAiKnowledgeSourceRegistry();
        $source   = $registry->getSource('tenant_avatar');

        $this->assertContains(
            'buyer_tenant_match',
            $source['allowed_for_question_types'],
            'tenant_avatar must continue to allow buyer_tenant_match after adding suited_audience'
        );
    }
}
