<?php

namespace Tests\Unit\Services\AskAi;

use App\Models\BuyerTenantDnaProfile;
use App\Models\ListingCompatibilityScore;
use App\Models\PropertyDnaProfile;
use App\Models\PropertyLocationDna;
use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\AskAi\AskAiPromptBuilderService;
use App\Services\AskAi\AskAiResponseContractService;
use App\Services\Dna\PropertyIntelligenceProfileService;
use App\Services\LocationDna\LocationDnaIntelligenceContextService;
use App\Services\LocationDna\LocationDnaMarketingContextService;
use PHPUnit\Framework\TestCase;

/**
 * AskAiIntelligenceIntegrationSmokeTest
 *
 * Pure unit tests — no database, no Laravel TestCase, no DB traits, no HTTP calls.
 * Covers multi-source combined scenarios that no single-source integration test addresses.
 *
 * Each of the six smoke scenarios traces the FULL Context → Contract → Prompt chain:
 *   buildForListing() via a partial mock (all six finder methods stubbed, Context layer executed)
 *   → buildContract()
 *   → buildPromptPackage()
 *
 * Smoke scenarios:
 *   1. property_standout with combined property_intelligence + location_intelligence
 *   2. marketing_angles with property_intelligence + location_intelligence (including marketing_context)
 *   3. suited_audience with optional buyer_avatar (present vs. absent)
 *   4. buyer_tenant_match with compatibility + avatar (present and absent)
 *   5. compatibility_signals with compatibility only
 *   6. Missing required source degrades gracefully through all three layers
 *
 * Governance assertions:
 *   a. No protected-class terms in assembled context payloads
 *   b. No legal/tax/lending/brokerage/investment advice language in response_rules
 *   c. No ranking/auto-decisioning language in response_rules
 *   d. No write calls in the four governed service files (static scan)
 *   e. No Http:: / OpenAI:: / openai calls in the four governed service files (static scan)
 */
class AskAiIntelligenceIntegrationSmokeTest extends TestCase
{
    private const PROTECTED_CLASS_TERMS = [
        'race',
        'color',
        'national origin',
        'religion',
        'sex',
        'familial status',
        'disability',
        'ethnicity',
        'gender',
        'marital status',
        'sexual orientation',
        'source of income',
    ];

    private const RANKING_TERMS = [
        'rank',
        'ranked',
        'ranking',
        'auto-decision',
        'auto decision',
        'automatically decide',
        'recommend this listing',
        'best match',
        'top match',
        'approve',
        'reject',
    ];

    private const LEGAL_ADVICE_TERMS = [
        'legal advice',
        'tax advice',
        'lending advice',
        'brokerage advice',
        'investment advice',
        'constitute legal',
        'constitute tax',
        'constitute lending',
        'constitute brokerage',
        'constitute investment',
    ];

    private const WRITE_PATTERNS = [
        '->save(',
        '->update(',
        '->create(',
        '->delete(',
        'DB::insert(',
        'DB::update(',
        'DB::delete(',
    ];

    private const HTTP_OPENAI_PATTERNS = [
        'Http::',
        'OpenAI::',
        'openai(',
    ];

    // =========================================================================
    // Service file paths (no base_path() — pure PHPUnit\Framework\TestCase)
    // =========================================================================

    private function serviceDir(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/';
    }

    private function contextBuilderPath(): string
    {
        return $this->serviceDir() . 'AskAiContextBuilderService.php';
    }

    private function responseContractPath(): string
    {
        return $this->serviceDir() . 'AskAiResponseContractService.php';
    }

    private function promptBuilderPath(): string
    {
        return $this->serviceDir() . 'AskAiPromptBuilderService.php';
    }

    private function registryPath(): string
    {
        return $this->serviceDir() . 'AskAiKnowledgeSourceRegistry.php';
    }

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
    // Constructor-service mock factories
    // (three required by AskAiContextBuilderService::__construct)
    // =========================================================================

    /**
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
     * @return LocationDnaIntelligenceContextService&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeLocationDnaIntelligenceServiceMock(
        ?array $returnValue = null
    ): LocationDnaIntelligenceContextService {
        $mock = $this->getMockBuilder(LocationDnaIntelligenceContextService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getForListing'])
            ->getMock();

        $mock->method('getForListing')->willReturn($returnValue ?? [
            'success'                       => false,
            'status'                        => 'missing',
            'listing_type'                  => 'seller',
            'listing_id'                    => 1,
            'location_intelligence_context' => null,
            'error'                         => 'No property_location_dna record found for this listing',
        ]);

        return $mock;
    }

    /**
     * @return LocationDnaMarketingContextService&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeLocationDnaMarketingServiceMock(
        ?array $returnValue = null
    ): LocationDnaMarketingContextService {
        $mock = $this->getMockBuilder(LocationDnaMarketingContextService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getForListing'])
            ->getMock();

        $mock->method('getForListing')->willReturn($returnValue ?? [
            'success'                    => false,
            'status'                     => 'missing',
            'listing_type'               => 'seller',
            'listing_id'                 => 1,
            'marketing_location_context' => null,
            'error'                      => 'No property_location_dna record found for this listing',
        ]);

        return $mock;
    }

    /**
     * Factory for AskAiResponseContractService.
     * Centralised so that if a constructor dependency is added in a future task,
     * only this one method needs updating.
     */
    private function makeContractService(): AskAiResponseContractService
    {
        return new AskAiResponseContractService();
    }

    /**
     * Factory for AskAiPromptBuilderService.
     *
     * Centralised so that when task #2060 (Source Attribution Enhancements) adds
     * AskAiKnowledgeSourceRegistry as a constructor dependency, only this one method
     * needs updating — no other test code changes required.
     *
     * Once #2060 merges, update to:
     *   return new AskAiPromptBuilderService(new AskAiKnowledgeSourceRegistry());
     */
    private function makePromptBuilder(): AskAiPromptBuilderService
    {
        return new AskAiPromptBuilderService();
    }

    /**
     * Build a partial mock of AskAiContextBuilderService with all six finder methods stubbed.
     * All three constructor services are accepted, mirroring AskAiContextBuilderServiceTest.makeService().
     *
     * @return AskAiContextBuilderService&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeContextBuilder(
        ?PropertyIntelligenceProfileService $intelligenceService = null,
        ?LocationDnaIntelligenceContextService $locationDnaIntelligenceService = null,
        ?LocationDnaMarketingContextService $locationDnaMarketingService = null
    ): AskAiContextBuilderService {
        $intelligenceService          ??= $this->makeIntelligenceServiceMock();
        $locationDnaIntelligenceService ??= $this->makeLocationDnaIntelligenceServiceMock();
        $locationDnaMarketingService    ??= $this->makeLocationDnaMarketingServiceMock();

        return $this->getMockBuilder(AskAiContextBuilderService::class)
            ->setConstructorArgs([
                $intelligenceService,
                $locationDnaIntelligenceService,
                $locationDnaMarketingService,
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

    // =========================================================================
    // In-memory model stub helpers (no DB — property assignment only)
    // =========================================================================

    private function makeListingStub(): object
    {
        $stub              = new \stdClass();
        $stub->id          = 1;
        $stub->is_approved = true;
        $stub->created_at  = '2026-01-01 00:00:00';
        $stub->updated_at  = '2026-01-01 00:00:00';
        return $stub;
    }

    private function makePropertyDnaProfileStub(array $attrs = []): PropertyDnaProfile
    {
        $profile                          = new PropertyDnaProfile();
        $profile->id                      = $attrs['id'] ?? 10;
        $profile->listing_type            = $attrs['listing_type'] ?? 'seller';
        $profile->listing_id              = $attrs['listing_id'] ?? 1;
        $profile->version                 = $attrs['version'] ?? 'v1';
        $profile->overall_dna_completeness= $attrs['overall_dna_completeness'] ?? 70.0;
        $profile->ai_buyer_archetype_tags = $attrs['ai_buyer_archetype_tags'] ?? ['amenity:pool'];
        $profile->ai_marketing_hooks      = $attrs['ai_marketing_hooks'] ?? [];
        $profile->location_intelligence_context = $attrs['location_intelligence_context'] ?? null;
        $profile->computed_at             = $attrs['computed_at'] ?? null;
        $profile->archived_at             = null;
        return $profile;
    }

    private function makePropertyLocationDnaStub(array $attrs = []): PropertyLocationDna
    {
        $dna                = new PropertyLocationDna();
        $dna->listing_type  = $attrs['listing_type'] ?? 'seller';
        $dna->listing_id    = $attrs['listing_id'] ?? 1;
        $dna->geocode_status= $attrs['geocode_status'] ?? 'success';
        $dna->lifestyle_json= $attrs['lifestyle_json'] ?? [
            'scores'     => ['walkability' => 72],
            'categories' => ['walkable'],
            'narrative'  => 'A walkable area.',
            'version'    => 'LIFESTYLE_V1',
        ];
        $dna->generated_at  = $attrs['generated_at'] ?? null;
        return $dna;
    }

    private function makeAvatarProfileStub(array $attrs = []): BuyerTenantDnaProfile
    {
        $profile                           = new BuyerTenantDnaProfile();
        $profile->listing_type             = $attrs['listing_type'] ?? 'buyer';
        $profile->listing_id               = $attrs['listing_id'] ?? 1;
        $profile->avatar_type              = $attrs['avatar_type'] ?? 'First-Time Buyer';
        $profile->primary_motivation       = $attrs['primary_motivation'] ?? 'stability';
        $profile->secondary_motivation     = $attrs['secondary_motivation'] ?? 'investment';
        $profile->buyer_narrative          = $attrs['buyer_narrative'] ?? 'Buyer narrative.';
        $profile->buyer_preference_summary = $attrs['buyer_preference_summary'] ?? ['min_beds' => 3];
        $profile->buyer_personality_tags   = $attrs['buyer_personality_tags'] ?? ['value-seeker'];
        $profile->buyer_match_preferences  = $attrs['buyer_match_preferences'] ?? [];
        $profile->avatar_confidence_score  = $attrs['avatar_confidence_score'] ?? 80;
        $profile->buyer_readiness_score    = $attrs['buyer_readiness_score'] ?? 75;
        $profile->buyer_avatar_version     = $attrs['buyer_avatar_version'] ?? 'BUYER_AVATAR_V1';
        $profile->tenant_narrative         = $attrs['tenant_narrative'] ?? 'Tenant narrative.';
        $profile->tenant_preference_summary= $attrs['tenant_preference_summary'] ?? [];
        $profile->tenant_personality_tags  = $attrs['tenant_personality_tags'] ?? [];
        $profile->tenant_match_preferences = $attrs['tenant_match_preferences'] ?? [];
        $profile->tenant_avatar_version    = $attrs['tenant_avatar_version'] ?? 'TENANT_AVATAR_V1';
        $profile->archived_at              = null;
        return $profile;
    }

    private function makeCompatibilityScoreStub(array $attrs = []): ListingCompatibilityScore
    {
        $score                                = new ListingCompatibilityScore();
        $score->overall_score                 = $attrs['overall_score'] ?? 82.5;
        $score->physical_match_score          = $attrs['physical_match_score'] ?? 85.0;
        $score->financial_match_score         = $attrs['financial_match_score'] ?? 80.0;
        $score->terms_match_score             = $attrs['terms_match_score'] ?? 78.0;
        $score->location_match_score          = $attrs['location_match_score'] ?? 87.0;
        $score->compatibility_summary_json    = $attrs['compatibility_summary_json'] ?? ['result' => 'strong'];
        $score->compatibility_highlights      = $attrs['compatibility_highlights'] ?? ['Price range aligns'];
        $score->compatibility_warnings        = $attrs['compatibility_warnings'] ?? [];
        $score->compatibility_readiness_score = $attrs['compatibility_readiness_score'] ?? 0.9;
        $score->compatibility_narrative       = $attrs['compatibility_narrative'] ?? 'Criteria correspond.';
        $score->score_explanation             = $attrs['score_explanation'] ?? [];
        $score->version                       = $attrs['version'] ?? 'BYA_COMPAT_V1';
        $score->compatibility_trait_results   = $attrs['compatibility_trait_results'] ?? null;
        $score->computed_at                   = null;
        return $score;
    }

    // =========================================================================
    // Data payload helpers for mocked services
    // =========================================================================

    /**
     * Successful buildPayloadReadOnly() return value with approved property intelligence fields.
     */
    private function makeIntelligencePayload(array $overrides = []): array
    {
        return array_merge([
            'success'                       => true,
            'status'                        => 'generated',
            'listing_type'                  => 'seller',
            'listing_id'                    => 1,
            'property_strengths'            => ['Pool', 'Covered Parking'],
            'property_highlights'           => ['Pool', 'Covered Parking', 'Pets Allowed'],
            'property_positioning'          => 'Move-Up Home',
            'property_target_audiences'     => ['Move-Up Families'],
            'property_personality_tags'     => ['Outdoor Living', 'Family-Friendly'],
            'property_story'                => 'A beautifully updated Move-Up Home.',
            'location_intelligence_context' => null,
            'property_intelligence_version' => 'PROPERTY_INTELLIGENCE_V1',
            'error'                         => null,
        ], $overrides);
    }

    /**
     * Available response from LocationDnaIntelligenceContextService.
     */
    private function makeIntelligenceContextAvailable(array $overrides = []): array
    {
        return array_merge([
            'success'                       => true,
            'status'                        => 'available',
            'listing_type'                  => 'seller',
            'listing_id'                    => 1,
            'location_intelligence_context' => [
                'coastal_features'     => ['nearest_beach_miles' => 2.3],
                'daily_convenience'    => ['nearest_grocery_miles' => 0.4],
                'outdoor_recreation'   => ['nearest_park_miles' => 0.7],
                'transportation'       => ['nearest_transit_miles' => 0.2],
                'nearest_highlights'   => [
                    'nearest_beach_miles'   => 2.3,
                    'nearest_grocery_miles' => 0.4,
                    'nearest_park_miles'    => 0.7,
                    'nearest_transit_miles' => 0.2,
                ],
                'available_categories' => ['coastal_features', 'daily_convenience', 'outdoor_recreation', 'transportation'],
                'missing_categories'   => [],
            ],
            'error' => null,
        ], $overrides);
    }

    /**
     * Available response from LocationDnaMarketingContextService.
     */
    private function makeMarketingContextAvailable(array $overrides = []): array
    {
        return array_merge([
            'success'                    => true,
            'status'                     => 'available',
            'listing_type'               => 'seller',
            'listing_id'                 => 1,
            'marketing_location_context' => [
                'coastal_features'     => ['nearest_beach_miles' => 2.3],
                'daily_convenience'    => ['nearest_grocery_miles' => 0.4],
                'outdoor_recreation'   => ['nearest_park_miles' => 0.7],
                'transportation'       => ['nearest_transit_miles' => 0.2],
                'available_categories' => ['coastal_features', 'daily_convenience', 'outdoor_recreation', 'transportation'],
                'missing_categories'   => [],
            ],
            'error' => null,
        ], $overrides);
    }

    /**
     * Pair options for compatibility queries.
     */
    private function makePairOptions(): array
    {
        return [
            'demand_listing_type' => 'buyer',
            'demand_listing_id'   => 1,
            'supply_listing_type' => 'seller',
            'supply_listing_id'   => 2,
        ];
    }

    // =========================================================================
    // Smoke Scenario 1 — property_standout with combined intelligence
    //
    // Full chain: buildForListing('seller') → buildContract('property_standout')
    //             → buildPromptPackage
    // Context layer exercises: PropertyIntelligenceProfileService (property_intelligence)
    //                          + both LocationDna services (location_intelligence)
    // =========================================================================

    public function test_smoke1_property_standout_full_chain_produces_prompt_ready(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')
            ->willReturn($this->makeIntelligencePayload());

        $builder = $this->makeContextBuilder(
            $intelligenceService,
            $this->makeLocationDnaIntelligenceServiceMock($this->makeIntelligenceContextAvailable()),
            $this->makeLocationDnaMarketingServiceMock($this->makeMarketingContextAvailable())
        );
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfileStub());
        $builder->method('findPropertyLocationDna')->willReturn($this->makePropertyLocationDnaStub());
        $builder->method('findBuyerTenantDnaProfile')->willReturn(null);
        $builder->method('findCompatibilityScore')->willReturn(null);
        $builder->method('findAcceptedBidSummary')->willReturn(null);

        $context = $builder->buildForListing('seller', 1);

        $this->assertNotNull($context['property_intelligence'],
            'Context layer must produce property_intelligence for seller listing');
        $this->assertNotNull($context['location_intelligence'],
            'Context layer must produce location_intelligence when LocationDna record is present');

        $contract = $this->makeContractService()->buildContract('property_standout', $context);
        $this->assertSame('contract_ready', $contract['status'],
            'property_standout with property_intelligence present must be contract_ready');

        $package = $this->makePromptBuilder()->buildPromptPackage(
            'What makes this property stand out?', $context, $contract
        );

        $this->assertSame('prompt_ready', $package['status'],
            'Full chain for property_standout with combined intelligence must produce prompt_ready');
        $this->assertTrue($package['success']);
    }

    public function test_smoke1_property_standout_source_attribution_includes_property_intelligence(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')
            ->willReturn($this->makeIntelligencePayload());

        $builder = $this->makeContextBuilder($intelligenceService);
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfileStub());
        $builder->method('findPropertyLocationDna')->willReturn($this->makePropertyLocationDnaStub());
        $builder->method('findBuyerTenantDnaProfile')->willReturn(null);
        $builder->method('findCompatibilityScore')->willReturn(null);
        $builder->method('findAcceptedBidSummary')->willReturn(null);

        $context  = $builder->buildForListing('seller', 1);
        $contract = $this->makeContractService()->buildContract('property_standout', $context);
        $package  = $this->makePromptBuilder()->buildPromptPackage(
            'What makes this property stand out?', $context, $contract
        );

        $this->assertContains('property_intelligence', $package['source_attribution']['required_sources'],
            'source_attribution must include property_intelligence for property_standout');
    }

    public function test_smoke1_property_standout_source_attribution_includes_location_intelligence_when_present(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')
            ->willReturn($this->makeIntelligencePayload());

        $builder = $this->makeContextBuilder(
            $intelligenceService,
            $this->makeLocationDnaIntelligenceServiceMock($this->makeIntelligenceContextAvailable()),
            $this->makeLocationDnaMarketingServiceMock()
        );
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfileStub());
        $builder->method('findPropertyLocationDna')->willReturn($this->makePropertyLocationDnaStub());
        $builder->method('findBuyerTenantDnaProfile')->willReturn(null);
        $builder->method('findCompatibilityScore')->willReturn(null);
        $builder->method('findAcceptedBidSummary')->willReturn(null);

        $context  = $builder->buildForListing('seller', 1);
        $contract = $this->makeContractService()->buildContract('property_standout', $context);
        $package  = $this->makePromptBuilder()->buildPromptPackage(
            'What makes this property stand out?', $context, $contract
        );

        $this->assertContains('location_intelligence', $package['source_attribution']['required_sources'],
            'source_attribution must include location_intelligence when non-null and in allowed_context');
    }

    public function test_smoke1_property_standout_allowed_context_includes_property_intelligence_paths(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')
            ->willReturn($this->makeIntelligencePayload());

        $builder = $this->makeContextBuilder($intelligenceService);
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfileStub());
        $builder->method('findPropertyLocationDna')->willReturn($this->makePropertyLocationDnaStub());
        $builder->method('findBuyerTenantDnaProfile')->willReturn(null);
        $builder->method('findCompatibilityScore')->willReturn(null);
        $builder->method('findAcceptedBidSummary')->willReturn(null);

        $context  = $builder->buildForListing('seller', 1);
        $contract = $this->makeContractService()->buildContract('property_standout', $context);

        $piPaths = array_filter(
            $contract['allowed_context'],
            static fn(string $path) => str_starts_with($path, 'property_intelligence.')
        );
        $this->assertNotEmpty($piPaths,
            'property_standout allowed_context must contain property_intelligence dot-paths');
    }

    // =========================================================================
    // Smoke Scenario 2 — marketing_angles with location marketing_context
    //
    // Full chain: buildForListing('seller') → buildContract('marketing_angles')
    //             → buildPromptPackage
    // Context layer exercises: property_intelligence + location_intelligence with marketing_context
    // =========================================================================

    public function test_smoke2_marketing_angles_full_chain_produces_prompt_ready(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')
            ->willReturn($this->makeIntelligencePayload());

        $builder = $this->makeContextBuilder(
            $intelligenceService,
            $this->makeLocationDnaIntelligenceServiceMock($this->makeIntelligenceContextAvailable()),
            $this->makeLocationDnaMarketingServiceMock($this->makeMarketingContextAvailable())
        );
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfileStub());
        $builder->method('findPropertyLocationDna')->willReturn($this->makePropertyLocationDnaStub());
        $builder->method('findBuyerTenantDnaProfile')->willReturn(null);
        $builder->method('findCompatibilityScore')->willReturn(null);
        $builder->method('findAcceptedBidSummary')->willReturn(null);

        $context = $builder->buildForListing('seller', 1);

        $this->assertNotNull($context['location_intelligence']);
        $this->assertArrayHasKey('marketing_context', $context['location_intelligence'],
            'Context layer must merge marketing_context when marketing service returns available');

        $contract = $this->makeContractService()->buildContract('marketing_angles', $context);
        $this->assertSame('contract_ready', $contract['status'],
            'marketing_angles with property_intelligence present must be contract_ready');

        $package = $this->makePromptBuilder()->buildPromptPackage(
            'What are the best marketing angles?', $context, $contract
        );

        $this->assertSame('prompt_ready', $package['status'],
            'Full chain for marketing_angles with combined intelligence must produce prompt_ready');
    }

    public function test_smoke2_marketing_angles_source_attribution_includes_both_sources(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')
            ->willReturn($this->makeIntelligencePayload());

        $builder = $this->makeContextBuilder(
            $intelligenceService,
            $this->makeLocationDnaIntelligenceServiceMock($this->makeIntelligenceContextAvailable()),
            $this->makeLocationDnaMarketingServiceMock($this->makeMarketingContextAvailable())
        );
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfileStub());
        $builder->method('findPropertyLocationDna')->willReturn($this->makePropertyLocationDnaStub());
        $builder->method('findBuyerTenantDnaProfile')->willReturn(null);
        $builder->method('findCompatibilityScore')->willReturn(null);
        $builder->method('findAcceptedBidSummary')->willReturn(null);

        $context  = $builder->buildForListing('seller', 1);
        $contract = $this->makeContractService()->buildContract('marketing_angles', $context);
        $package  = $this->makePromptBuilder()->buildPromptPackage(
            'What are the best marketing angles?', $context, $contract
        );

        $sources = $package['source_attribution']['required_sources'];
        $this->assertContains('property_intelligence', $sources,
            'property_intelligence must be in source_attribution for marketing_angles');
        $this->assertContains('location_intelligence', $sources,
            'location_intelligence must be in source_attribution when present and in allowed_context');
    }

    public function test_smoke2_marketing_angles_contract_allowed_context_covers_marketing_context_path(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')
            ->willReturn($this->makeIntelligencePayload());

        $builder = $this->makeContextBuilder($intelligenceService);
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfileStub());
        $builder->method('findPropertyLocationDna')->willReturn($this->makePropertyLocationDnaStub());
        $builder->method('findBuyerTenantDnaProfile')->willReturn(null);
        $builder->method('findCompatibilityScore')->willReturn(null);
        $builder->method('findAcceptedBidSummary')->willReturn(null);

        $context  = $builder->buildForListing('seller', 1);
        $contract = $this->makeContractService()->buildContract('marketing_angles', $context);

        $this->assertSame('contract_ready', $contract['status']);
        $this->assertContains(
            'location_intelligence.marketing_context',
            $contract['allowed_context'],
            'marketing_angles allowed_context must include location_intelligence.marketing_context path'
        );
    }

    // =========================================================================
    // Smoke Scenario 3 — suited_audience with optional avatar
    //
    // Full chain: buildForListing('seller') → buildContract('suited_audience')
    //             → buildPromptPackage
    // Context layer: seller listing produces property_intelligence; buyer_avatar is null
    //                (by design for seller type). Tests prompt_ready both with and without avatar.
    // =========================================================================

    public function test_smoke3_suited_audience_full_chain_without_avatar_produces_prompt_ready(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')
            ->willReturn($this->makeIntelligencePayload());

        $builder = $this->makeContextBuilder($intelligenceService);
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfileStub());
        $builder->method('findPropertyLocationDna')->willReturn($this->makePropertyLocationDnaStub());
        $builder->method('findBuyerTenantDnaProfile')->willReturn(null);
        $builder->method('findCompatibilityScore')->willReturn(null);
        $builder->method('findAcceptedBidSummary')->willReturn(null);

        $context = $builder->buildForListing('seller', 1);

        $this->assertNotNull($context['property_intelligence']);
        $this->assertNull($context['buyer_avatar'],
            'seller listing must not produce buyer_avatar');

        $contract = $this->makeContractService()->buildContract('suited_audience', $context);
        $this->assertSame('contract_ready', $contract['status'],
            'suited_audience must be contract_ready with property_intelligence even without avatar');

        $package = $this->makePromptBuilder()->buildPromptPackage(
            'Who is this property suited for?', $context, $contract
        );

        $this->assertSame('prompt_ready', $package['status'],
            'suited_audience without avatar must produce prompt_ready when property_intelligence is present');
    }

    public function test_smoke3_suited_audience_avatar_not_in_required_sources_when_absent(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')
            ->willReturn($this->makeIntelligencePayload());

        $builder = $this->makeContextBuilder($intelligenceService);
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfileStub());
        $builder->method('findPropertyLocationDna')->willReturn($this->makePropertyLocationDnaStub());
        $builder->method('findBuyerTenantDnaProfile')->willReturn(null);
        $builder->method('findCompatibilityScore')->willReturn(null);
        $builder->method('findAcceptedBidSummary')->willReturn(null);

        $context  = $builder->buildForListing('seller', 1);
        $contract = $this->makeContractService()->buildContract('suited_audience', $context);
        $package  = $this->makePromptBuilder()->buildPromptPackage(
            'Who is this property suited for?', $context, $contract
        );

        $sources = $package['source_attribution']['required_sources'];
        $this->assertNotContains('buyer_avatar', $sources,
            'buyer_avatar must not appear in source_attribution when null in context');
        $this->assertNotContains('tenant_avatar', $sources,
            'tenant_avatar must not appear in source_attribution when null in context');
    }

    public function test_smoke3_suited_audience_source_attribution_includes_buyer_avatar_when_present(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')
            ->willReturn($this->makeIntelligencePayload());

        $builder = $this->makeContextBuilder($intelligenceService);
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfileStub());
        $builder->method('findPropertyLocationDna')->willReturn($this->makePropertyLocationDnaStub());
        $builder->method('findBuyerTenantDnaProfile')->willReturn(null);
        $builder->method('findCompatibilityScore')->willReturn(null);
        $builder->method('findAcceptedBidSummary')->willReturn(null);

        // Build seller context (has property_intelligence), then augment with buyer_avatar
        // to test the optional attribution behavior when avatar is present alongside PI.
        $context              = $builder->buildForListing('seller', 1);
        $context['buyer_avatar'] = [
            'avatar_type'              => 'First-Time Buyer',
            'buyer_personality_tags'   => ['value-seeker'],
            'buyer_preference_summary' => ['min_beds' => 3],
        ];

        $contract = $this->makeContractService()->buildContract('suited_audience', $context);
        $this->assertSame('contract_ready', $contract['status']);

        $package = $this->makePromptBuilder()->buildPromptPackage(
            'Who is this property suited for?', $context, $contract
        );

        $this->assertSame('prompt_ready', $package['status']);
        $this->assertContains('buyer_avatar', $package['source_attribution']['required_sources'],
            'buyer_avatar must appear in source_attribution when non-null in context');
    }

    // =========================================================================
    // Smoke Scenario 4 — buyer_tenant_match with compatibility + avatar
    //
    // Full chain: buildForListing('buyer', $pairOptions) → buildContract('buyer_tenant_match')
    //             → buildPromptPackage
    // Context layer: buyer listing → compatibility + buyer_avatar (when profile stubbed)
    // =========================================================================

    public function test_smoke4_buyer_tenant_match_with_compatibility_and_buyer_avatar_produces_prompt_ready(): void
    {
        $builder = $this->makeContextBuilder();
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyDnaProfile')->willReturn(null);
        $builder->method('findPropertyLocationDna')->willReturn($this->makePropertyLocationDnaStub());
        $builder->method('findBuyerTenantDnaProfile')
            ->willReturn($this->makeAvatarProfileStub(['listing_type' => 'buyer']));
        $builder->method('findCompatibilityScore')
            ->willReturn($this->makeCompatibilityScoreStub());
        $builder->method('findAcceptedBidSummary')->willReturn(null);

        $context = $builder->buildForListing('buyer', 1, $this->makePairOptions());

        $this->assertNotNull($context['compatibility'],
            'Context layer must produce compatibility when pair options and score are present');
        $this->assertNotNull($context['buyer_avatar'],
            'Context layer must produce buyer_avatar when BuyerTenantDnaProfile is present for buyer listing');

        $contract = $this->makeContractService()->buildContract('buyer_tenant_match', $context);
        $this->assertSame('contract_ready', $contract['status']);

        $package = $this->makePromptBuilder()->buildPromptPackage(
            'Does this buyer match the listing?', $context, $contract
        );

        $this->assertSame('prompt_ready', $package['status'],
            'Full chain for buyer_tenant_match with compatibility + avatar must produce prompt_ready');
    }

    public function test_smoke4_buyer_tenant_match_source_attribution_includes_compatibility_and_buyer_avatar(): void
    {
        $builder = $this->makeContextBuilder();
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyDnaProfile')->willReturn(null);
        $builder->method('findPropertyLocationDna')->willReturn($this->makePropertyLocationDnaStub());
        $builder->method('findBuyerTenantDnaProfile')
            ->willReturn($this->makeAvatarProfileStub(['listing_type' => 'buyer']));
        $builder->method('findCompatibilityScore')
            ->willReturn($this->makeCompatibilityScoreStub());
        $builder->method('findAcceptedBidSummary')->willReturn(null);

        $context  = $builder->buildForListing('buyer', 1, $this->makePairOptions());
        $contract = $this->makeContractService()->buildContract('buyer_tenant_match', $context);
        $package  = $this->makePromptBuilder()->buildPromptPackage(
            'Does this buyer match the listing?', $context, $contract
        );

        $sources = $package['source_attribution']['required_sources'];
        $this->assertContains('compatibility', $sources,
            'compatibility must be in source_attribution for buyer_tenant_match');
        $this->assertContains('buyer_avatar', $sources,
            'buyer_avatar must be in source_attribution when non-null in context');
    }

    public function test_smoke4_buyer_tenant_match_with_tenant_avatar_full_chain_produces_prompt_ready(): void
    {
        $builder = $this->makeContextBuilder();
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyDnaProfile')->willReturn(null);
        $builder->method('findPropertyLocationDna')->willReturn($this->makePropertyLocationDnaStub());
        $builder->method('findBuyerTenantDnaProfile')
            ->willReturn($this->makeAvatarProfileStub(['listing_type' => 'tenant']));
        $builder->method('findCompatibilityScore')
            ->willReturn($this->makeCompatibilityScoreStub(['version' => 'BYA_COMPAT_V1']));
        $builder->method('findAcceptedBidSummary')->willReturn(null);

        $context = $builder->buildForListing('tenant', 1, $this->makePairOptions());

        $this->assertNotNull($context['compatibility']);
        $this->assertNotNull($context['tenant_avatar'],
            'Context layer must produce tenant_avatar for tenant listing when profile is present');

        $contract = $this->makeContractService()->buildContract('buyer_tenant_match', $context);
        $package  = $this->makePromptBuilder()->buildPromptPackage(
            'Does this tenant match the listing?', $context, $contract
        );

        $this->assertSame('prompt_ready', $package['status']);
        $this->assertContains('tenant_avatar', $package['source_attribution']['required_sources'],
            'tenant_avatar must be reflected in attribution when non-null in context');
    }

    public function test_smoke4_buyer_tenant_match_without_avatar_still_produces_prompt_ready(): void
    {
        $builder = $this->makeContextBuilder();
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyDnaProfile')->willReturn(null);
        $builder->method('findPropertyLocationDna')->willReturn($this->makePropertyLocationDnaStub());
        $builder->method('findBuyerTenantDnaProfile')->willReturn(null);
        $builder->method('findCompatibilityScore')
            ->willReturn($this->makeCompatibilityScoreStub());
        $builder->method('findAcceptedBidSummary')->willReturn(null);

        $context = $builder->buildForListing('buyer', 1, $this->makePairOptions());

        $this->assertNotNull($context['compatibility']);
        $this->assertNull($context['buyer_avatar'],
            'buyer_avatar must be null when BuyerTenantDnaProfile is absent');

        $contract = $this->makeContractService()->buildContract('buyer_tenant_match', $context);
        $package  = $this->makePromptBuilder()->buildPromptPackage(
            'Does this buyer match the listing?', $context, $contract
        );

        $this->assertSame('prompt_ready', $package['status'],
            'buyer_tenant_match without avatar must still produce prompt_ready when compatibility is present');
        $sources = $package['source_attribution']['required_sources'];
        $this->assertContains('compatibility', $sources,
            'compatibility must still be attributed when avatar is absent');
        $this->assertNotContains('buyer_avatar', $sources,
            'buyer_avatar must not appear in attribution when null');
    }

    // =========================================================================
    // Smoke Scenario 5 — compatibility_signals with compatibility only
    //
    // Full chain: buildForListing('buyer', $pairOptions) → buildContract('compatibility_signals')
    //             → buildPromptPackage
    // Context layer: buyer listing with compatibility score; no property_intelligence, no avatar
    // =========================================================================

    public function test_smoke5_compatibility_signals_full_chain_produces_prompt_ready(): void
    {
        $builder = $this->makeContextBuilder();
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyDnaProfile')->willReturn(null);
        $builder->method('findPropertyLocationDna')->willReturn($this->makePropertyLocationDnaStub());
        $builder->method('findBuyerTenantDnaProfile')->willReturn(null);
        $builder->method('findCompatibilityScore')
            ->willReturn($this->makeCompatibilityScoreStub(['version' => 'BYA_COMPAT_V1']));
        $builder->method('findAcceptedBidSummary')->willReturn(null);

        $context = $builder->buildForListing('buyer', 1, $this->makePairOptions());

        $this->assertNotNull($context['compatibility'],
            'Context layer must produce compatibility when pair options and score are present');
        $this->assertNull($context['property_intelligence'],
            'property_intelligence must be null for buyer listing type');

        $contract = $this->makeContractService()->buildContract('compatibility_signals', $context);
        $this->assertSame('contract_ready', $contract['status'],
            'compatibility_signals with compatibility present must be contract_ready');

        $package = $this->makePromptBuilder()->buildPromptPackage(
            'What are the compatibility signals?', $context, $contract
        );

        $this->assertSame('prompt_ready', $package['status'],
            'compatibility_signals with compatibility only must produce prompt_ready');
        $this->assertTrue($package['success']);
    }

    public function test_smoke5_compatibility_signals_source_attribution_versions_contains_compatibility_version(): void
    {
        $builder = $this->makeContextBuilder();
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyDnaProfile')->willReturn(null);
        $builder->method('findPropertyLocationDna')->willReturn($this->makePropertyLocationDnaStub());
        $builder->method('findBuyerTenantDnaProfile')->willReturn(null);
        $builder->method('findCompatibilityScore')
            ->willReturn($this->makeCompatibilityScoreStub(['version' => 'BYA_COMPAT_V1']));
        $builder->method('findAcceptedBidSummary')->willReturn(null);

        $context  = $builder->buildForListing('buyer', 1, $this->makePairOptions());
        $contract = $this->makeContractService()->buildContract('compatibility_signals', $context);
        $package  = $this->makePromptBuilder()->buildPromptPackage(
            'What are the compatibility signals?', $context, $contract
        );

        $this->assertSame('prompt_ready', $package['status']);
        $this->assertArrayHasKey('versions', $package['source_attribution']);
        $this->assertArrayHasKey('compatibility_version', $package['source_attribution']['versions'],
            'source_attribution.versions must carry compatibility_version key');
        $this->assertSame(
            'BYA_COMPAT_V1',
            $package['source_attribution']['versions']['compatibility_version'],
            'compatibility_version must match the value from the compatibility score'
        );
    }

    // =========================================================================
    // Smoke Scenario 6 — missing required source degrades gracefully
    //
    // Full chain: buildForListing('seller') with no DNA profile → buildContract('property_standout')
    //             → buildPromptPackage
    // Context layer produces property_intelligence = null, status = 'partial'
    // =========================================================================

    public function test_smoke6_missing_property_dna_profile_produces_partial_context(): void
    {
        $builder = $this->makeContextBuilder();
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyDnaProfile')->willReturn(null);
        $builder->method('findPropertyLocationDna')->willReturn($this->makePropertyLocationDnaStub());
        $builder->method('findBuyerTenantDnaProfile')->willReturn(null);
        $builder->method('findCompatibilityScore')->willReturn(null);
        $builder->method('findAcceptedBidSummary')->willReturn(null);

        $context = $builder->buildForListing('seller', 1);

        $this->assertNull($context['property_intelligence'],
            'property_intelligence must be null when PropertyDnaProfile is absent');
        $this->assertContains('property_intelligence', $context['missing_sources'],
            'missing_sources must list property_intelligence when profile is absent');
        $this->assertSame('partial', $context['status'],
            'Context status must be partial when a required source is missing');
    }

    public function test_smoke6_property_standout_without_property_intelligence_returns_insufficient_context_contract(): void
    {
        $builder = $this->makeContextBuilder();
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyDnaProfile')->willReturn(null);
        $builder->method('findPropertyLocationDna')->willReturn($this->makePropertyLocationDnaStub());
        $builder->method('findBuyerTenantDnaProfile')->willReturn(null);
        $builder->method('findCompatibilityScore')->willReturn(null);
        $builder->method('findAcceptedBidSummary')->willReturn(null);

        $context  = $builder->buildForListing('seller', 1);
        $contract = $this->makeContractService()->buildContract('property_standout', $context);

        $this->assertSame('insufficient_context', $contract['status'],
            'Contract must be insufficient_context when required source is absent');
        $this->assertFalse($contract['success']);
        $this->assertContains('property_intelligence', $contract['missing_required_sources'],
            'missing_required_sources must include property_intelligence');
    }

    public function test_smoke6_full_chain_without_property_intelligence_produces_non_prompt_ready(): void
    {
        $builder = $this->makeContextBuilder();
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyDnaProfile')->willReturn(null);
        $builder->method('findPropertyLocationDna')->willReturn($this->makePropertyLocationDnaStub());
        $builder->method('findBuyerTenantDnaProfile')->willReturn(null);
        $builder->method('findCompatibilityScore')->willReturn(null);
        $builder->method('findAcceptedBidSummary')->willReturn(null);

        $context  = $builder->buildForListing('seller', 1);
        $contract = $this->makeContractService()->buildContract('property_standout', $context);
        $package  = $this->makePromptBuilder()->buildPromptPackage(
            'What makes this property stand out?', $context, $contract
        );

        $this->assertNotSame('prompt_ready', $package['status'],
            'Prompt package must not be prompt_ready when required source is missing');
        $this->assertFalse($package['success']);
        $this->assertContains('property_intelligence', $package['missing_required_sources'],
            'Prompt package must surface property_intelligence in missing_required_sources');
    }

    // =========================================================================
    // Governance — (a) No protected-class terms in assembled context payloads
    // Checks the context array shapes produced by each scenario type.
    // =========================================================================

    public function test_governance_a_no_protected_class_terms_in_seller_context_payload(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')
            ->willReturn($this->makeIntelligencePayload());

        $builder = $this->makeContextBuilder($intelligenceService);
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfileStub());
        $builder->method('findPropertyLocationDna')->willReturn($this->makePropertyLocationDnaStub());
        $builder->method('findBuyerTenantDnaProfile')->willReturn(null);
        $builder->method('findCompatibilityScore')->willReturn(null);
        $builder->method('findAcceptedBidSummary')->willReturn(null);

        $context     = $builder->buildForListing('seller', 1);
        $payloadText = strtolower(json_encode($context['property_intelligence'] ?? []));

        foreach (self::PROTECTED_CLASS_TERMS as $term) {
            $this->assertStringNotContainsString(
                strtolower($term),
                $payloadText,
                "Protected-class term '{$term}' must not appear in property_intelligence context payload"
            );
        }
    }

    public function test_governance_a_no_protected_class_terms_in_compatibility_payload(): void
    {
        $builder = $this->makeContextBuilder();
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyDnaProfile')->willReturn(null);
        $builder->method('findPropertyLocationDna')->willReturn($this->makePropertyLocationDnaStub());
        $builder->method('findBuyerTenantDnaProfile')
            ->willReturn($this->makeAvatarProfileStub(['listing_type' => 'buyer']));
        $builder->method('findCompatibilityScore')
            ->willReturn($this->makeCompatibilityScoreStub());
        $builder->method('findAcceptedBidSummary')->willReturn(null);

        $context     = $builder->buildForListing('buyer', 1, $this->makePairOptions());
        $payloadText = strtolower(json_encode($context['compatibility'] ?? []));

        foreach (self::PROTECTED_CLASS_TERMS as $term) {
            $this->assertStringNotContainsString(
                strtolower($term),
                $payloadText,
                "Protected-class term '{$term}' must not appear in compatibility context payload"
            );
        }
    }

    public function test_governance_a_no_protected_class_terms_in_buyer_avatar_payload(): void
    {
        $builder = $this->makeContextBuilder();
        $builder->method('findListing')->willReturn($this->makeListingStub());
        $builder->method('findPropertyDnaProfile')->willReturn(null);
        $builder->method('findPropertyLocationDna')->willReturn($this->makePropertyLocationDnaStub());
        $builder->method('findBuyerTenantDnaProfile')
            ->willReturn($this->makeAvatarProfileStub(['listing_type' => 'buyer']));
        $builder->method('findCompatibilityScore')->willReturn(null);
        $builder->method('findAcceptedBidSummary')->willReturn(null);

        $context     = $builder->buildForListing('buyer', 1);
        $payloadText = strtolower(json_encode($context['buyer_avatar'] ?? []));

        foreach (self::PROTECTED_CLASS_TERMS as $term) {
            $this->assertStringNotContainsString(
                strtolower($term),
                $payloadText,
                "Protected-class term '{$term}' must not appear in buyer_avatar context payload"
            );
        }
    }

    // =========================================================================
    // Governance — (b) No legal/tax/lending advice language in response_rules
    // =========================================================================

    /**
     * @dataProvider smokeQuestionTypeProvider
     */
    public function test_governance_b_no_legal_advice_language_in_response_rules(string $questionType, array $minimalContext): void
    {
        $contract = $this->makeContractService()->buildContract($questionType, $minimalContext);

        if ($contract['status'] === 'insufficient_context' || $contract['status'] === 'refusal_required') {
            $this->assertTrue(true);
            return;
        }

        $rulesText = strtolower(implode(' ', $contract['response_rules']));

        foreach (self::LEGAL_ADVICE_TERMS as $term) {
            $this->assertStringNotContainsString(
                strtolower($term),
                $rulesText,
                "Legal/advice term '{$term}' must not appear in {$questionType} response_rules"
            );
        }
    }

    // =========================================================================
    // Governance — (c) No ranking/auto-decisioning language in response_rules
    // =========================================================================

    /**
     * @dataProvider smokeQuestionTypeProvider
     */
    public function test_governance_c_no_ranking_language_in_response_rules(string $questionType, array $minimalContext): void
    {
        $contract = $this->makeContractService()->buildContract($questionType, $minimalContext);

        if ($contract['status'] === 'insufficient_context' || $contract['status'] === 'refusal_required') {
            $this->assertTrue(true);
            return;
        }

        $rulesText = strtolower(implode(' ', $contract['response_rules']));

        foreach (self::RANKING_TERMS as $term) {
            $this->assertStringNotContainsString(
                strtolower($term),
                $rulesText,
                "Ranking/auto-decisioning term '{$term}' must not appear in {$questionType} response_rules"
            );
        }
    }

    public static function smokeQuestionTypeProvider(): array
    {
        $minimalPropertyIntelligence = ['property_intelligence' => ['property_highlights' => ['Pool']]];
        $minimalCompatibility        = ['compatibility' => ['overall_score' => 82.5, 'compatibility_highlights' => []]];

        return [
            'property_standout'    => ['property_standout',     $minimalPropertyIntelligence],
            'marketing_angles'     => ['marketing_angles',      $minimalPropertyIntelligence],
            'suited_audience'      => ['suited_audience',       $minimalPropertyIntelligence],
            'buyer_tenant_match'   => ['buyer_tenant_match',    $minimalCompatibility],
            'compatibility_signals'=> ['compatibility_signals', $minimalCompatibility],
        ];
    }

    // =========================================================================
    // Governance — (d) Static file scan: no write calls in the four service files
    // =========================================================================

    public function test_governance_d_context_builder_has_no_write_calls(): void
    {
        $code = $this->stripComments(file_get_contents($this->contextBuilderPath()));
        foreach (self::WRITE_PATTERNS as $pattern) {
            $this->assertStringNotContainsString(
                $pattern, $code,
                "AskAiContextBuilderService must not contain write call '{$pattern}'"
            );
        }
    }

    public function test_governance_d_response_contract_has_no_write_calls(): void
    {
        $code = $this->stripComments(file_get_contents($this->responseContractPath()));
        foreach (self::WRITE_PATTERNS as $pattern) {
            $this->assertStringNotContainsString(
                $pattern, $code,
                "AskAiResponseContractService must not contain write call '{$pattern}'"
            );
        }
    }

    public function test_governance_d_prompt_builder_has_no_write_calls(): void
    {
        $code = $this->stripComments(file_get_contents($this->promptBuilderPath()));
        foreach (self::WRITE_PATTERNS as $pattern) {
            $this->assertStringNotContainsString(
                $pattern, $code,
                "AskAiPromptBuilderService must not contain write call '{$pattern}'"
            );
        }
    }

    public function test_governance_d_knowledge_registry_has_no_write_calls(): void
    {
        $code = $this->stripComments(file_get_contents($this->registryPath()));
        foreach (self::WRITE_PATTERNS as $pattern) {
            $this->assertStringNotContainsString(
                $pattern, $code,
                "AskAiKnowledgeSourceRegistry must not contain write call '{$pattern}'"
            );
        }
    }

    // =========================================================================
    // Governance — (e) Static file scan: no Http:: / OpenAI:: / openai calls
    // =========================================================================

    public function test_governance_e_context_builder_has_no_http_or_openai_calls(): void
    {
        $code = $this->stripComments(file_get_contents($this->contextBuilderPath()));
        foreach (self::HTTP_OPENAI_PATTERNS as $pattern) {
            $this->assertStringNotContainsString(
                $pattern, $code,
                "AskAiContextBuilderService must not contain HTTP/OpenAI call '{$pattern}'"
            );
        }
    }

    public function test_governance_e_response_contract_has_no_http_or_openai_calls(): void
    {
        $code = $this->stripComments(file_get_contents($this->responseContractPath()));
        foreach (self::HTTP_OPENAI_PATTERNS as $pattern) {
            $this->assertStringNotContainsString(
                $pattern, $code,
                "AskAiResponseContractService must not contain HTTP/OpenAI call '{$pattern}'"
            );
        }
    }

    public function test_governance_e_prompt_builder_has_no_http_or_openai_calls(): void
    {
        $code = $this->stripComments(file_get_contents($this->promptBuilderPath()));
        foreach (self::HTTP_OPENAI_PATTERNS as $pattern) {
            $this->assertStringNotContainsString(
                $pattern, $code,
                "AskAiPromptBuilderService must not contain HTTP/OpenAI call '{$pattern}'"
            );
        }
    }

    public function test_governance_e_knowledge_registry_has_no_http_or_openai_calls(): void
    {
        $code = $this->stripComments(file_get_contents($this->registryPath()));
        foreach (self::HTTP_OPENAI_PATTERNS as $pattern) {
            $this->assertStringNotContainsString(
                $pattern, $code,
                "AskAiKnowledgeSourceRegistry must not contain HTTP/OpenAI call '{$pattern}'"
            );
        }
    }
}
