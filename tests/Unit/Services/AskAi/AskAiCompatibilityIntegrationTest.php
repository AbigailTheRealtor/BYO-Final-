<?php

namespace Tests\Unit\Services\AskAi;

use App\Models\BuyerTenantDnaProfile;
use App\Models\ListingCompatibilityScore;
use App\Models\PropertyLocationDna;
use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\AskAi\AskAiKnowledgeSourceRegistry;
use App\Services\AskAi\AskAiPromptBuilderService;
use App\Services\AskAi\AskAiResponseContractService;
use App\Services\Dna\PropertyIntelligenceProfileService;
use PHPUnit\Framework\TestCase;

/**
 * AskAiCompatibilityIntegrationTest
 *
 * Pure unit tests — no database, no Laravel TestCase, no DB traits.
 * Covers the compatibility-integration scenarios for the Ask AI pipeline:
 *
 * Test coverage:
 *   1. Compatibility source included in context when ListingCompatibilityScore exists
 *      (including compatibility_trait_results when the persisted model carries it)
 *   2. compatibility_signals contract returns contract_ready when compatibility is present
 *   3. compatibility_signals contract returns insufficient_context when compatibility is null
 *   4. buyer_tenant_match contract returns contract_ready when compatibility is present
 *   5. buyer_tenant_match contract returns insufficient_context when compatibility is null
 *   6. source_attribution.versions includes compatibility_version when compatibility is required
 *   7. No protected-class or demographic language in context payload fields
 *   8. No ranking or auto-decisioning language in response rules for compatibility types
 *   9. Static governance scan: no write calls in AskAiContextBuilderService
 *  10. All existing AskAiContextBuilderServiceTest and AskAiResponseContractServiceTest
 *      cases pass as part of the --filter AskAi suite (verified by running the suite)
 */
class AskAiCompatibilityIntegrationTest extends TestCase
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
        'age',
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

    private function serviceFilePath(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiContextBuilderService.php';
    }

    private function makeIntelligenceServiceMock(): PropertyIntelligenceProfileService
    {
        return $this->getMockBuilder(PropertyIntelligenceProfileService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['buildPayloadReadOnly'])
            ->getMock();
    }

    private function makeContextBuilderService(
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

    private function makeListingStub(): object
    {
        $stub = new \stdClass();
        $stub->id          = 1;
        $stub->is_approved = true;
        $stub->created_at  = '2026-01-01 00:00:00';
        $stub->updated_at  = '2026-01-01 00:00:00';
        return $stub;
    }

    private function makeLocationDna(): PropertyLocationDna
    {
        $dna = new PropertyLocationDna();
        $dna->listing_type   = 'buyer';
        $dna->listing_id     = 1;
        $dna->geocode_status = 'success';
        $dna->lifestyle_json = [
            'scores'     => ['walkability' => 72],
            'categories' => ['walkable'],
            'narrative'  => 'A walkable neighborhood.',
            'version'    => 'LIFESTYLE_V1',
        ];
        $dna->generated_at = null;
        return $dna;
    }

    private function makeCompatibilityScore(array $attrs = []): ListingCompatibilityScore
    {
        $score = new ListingCompatibilityScore();
        $score->overall_score                 = $attrs['overall_score'] ?? 82.5;
        $score->physical_match_score          = $attrs['physical_match_score'] ?? 85.0;
        $score->financial_match_score         = $attrs['financial_match_score'] ?? 80.0;
        $score->terms_match_score             = $attrs['terms_match_score'] ?? 78.0;
        $score->location_match_score          = $attrs['location_match_score'] ?? 87.0;
        $score->compatibility_summary_json    = $attrs['compatibility_summary_json'] ?? ['result' => 'strong'];
        $score->compatibility_highlights      = $attrs['compatibility_highlights'] ?? ['Price range aligns'];
        $score->compatibility_warnings        = $attrs['compatibility_warnings'] ?? [];
        $score->compatibility_readiness_score = $attrs['compatibility_readiness_score'] ?? 0.9;
        $score->compatibility_narrative       = $attrs['compatibility_narrative'] ?? 'Criteria terms recorded on both listings correspond.';
        $score->score_explanation             = $attrs['score_explanation'] ?? [];
        $score->version                       = $attrs['version'] ?? 'BYA_COMPAT_V1';
        $score->compatibility_trait_results   = $attrs['compatibility_trait_results'] ?? null;
        $score->computed_at                   = null;
        return $score;
    }

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
    // Test 1 — compatibility source included when score exists
    // =========================================================================

    public function test_compatibility_context_populated_when_score_exists(): void
    {
        $service = $this->makeContextBuilderService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());
        $service->method('findBuyerTenantDnaProfile')->willReturn(null);
        $service->method('findCompatibilityScore')->willReturn($this->makeCompatibilityScore());
        $service->method('findAcceptedBidSummary')->willReturn(null);

        $result = $service->buildForListing('buyer', 1, $this->makePairOptions());

        $this->assertNotNull($result['compatibility']);
        $this->assertSame(82.5, (float) $result['compatibility']['overall_score']);
        $this->assertArrayHasKey('compatibility_highlights', $result['compatibility']);
        $this->assertArrayHasKey('compatibility_warnings', $result['compatibility']);
        $this->assertArrayHasKey('compatibility_narrative', $result['compatibility']);
        $this->assertArrayHasKey('version', $result['compatibility']);
        $this->assertSame('BYA_COMPAT_V1', $result['compatibility']['version']);
    }

    public function test_compatibility_trait_results_included_when_score_carries_it(): void
    {
        $traitResults = [
            'aligned'     => [['dimension' => 'property_type_alignment', 'result' => 'aligned']],
            'conflicting' => [],
            'unresolved'  => [['dimension' => 'timeline_alignment', 'result' => 'unresolved']],
        ];

        $service = $this->makeContextBuilderService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());
        $service->method('findBuyerTenantDnaProfile')->willReturn(null);
        $service->method('findCompatibilityScore')->willReturn(
            $this->makeCompatibilityScore(['compatibility_trait_results' => $traitResults])
        );
        $service->method('findAcceptedBidSummary')->willReturn(null);

        $result = $service->buildForListing('buyer', 1, $this->makePairOptions());

        $this->assertNotNull($result['compatibility']);
        $this->assertArrayHasKey('compatibility_trait_results', $result['compatibility']);
        $this->assertSame($traitResults, $result['compatibility']['compatibility_trait_results']);
    }

    public function test_compatibility_trait_results_absent_when_score_has_none(): void
    {
        $service = $this->makeContextBuilderService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());
        $service->method('findBuyerTenantDnaProfile')->willReturn(null);
        $service->method('findCompatibilityScore')->willReturn(
            $this->makeCompatibilityScore(['compatibility_trait_results' => null])
        );
        $service->method('findAcceptedBidSummary')->willReturn(null);

        $result = $service->buildForListing('buyer', 1, $this->makePairOptions());

        $this->assertNotNull($result['compatibility']);
        $this->assertArrayNotHasKey(
            'compatibility_trait_results',
            $result['compatibility'],
            'compatibility_trait_results must not appear in payload when the score field is null'
        );
    }

    public function test_compatibility_is_null_when_no_pair_options_supplied(): void
    {
        $service = $this->makeContextBuilderService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());
        $service->method('findBuyerTenantDnaProfile')->willReturn(null);
        $service->method('findCompatibilityScore')->willReturn($this->makeCompatibilityScore());
        $service->method('findAcceptedBidSummary')->willReturn(null);

        $result = $service->buildForListing('buyer', 1);

        $this->assertNull($result['compatibility']);
    }

    public function test_compatibility_is_null_and_warning_added_when_score_not_found(): void
    {
        $service = $this->makeContextBuilderService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());
        $service->method('findBuyerTenantDnaProfile')->willReturn(null);
        $service->method('findCompatibilityScore')->willReturn(null);
        $service->method('findAcceptedBidSummary')->willReturn(null);

        $result = $service->buildForListing('buyer', 1, $this->makePairOptions());

        $this->assertNull($result['compatibility']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsStringIgnoringCase('not available', $result['warnings'][0]);
    }

    // =========================================================================
    // Test 2 — compatibility_signals returns contract_ready when compatibility present
    // =========================================================================

    public function test_compatibility_signals_contract_ready_when_compatibility_present(): void
    {
        $contractService = new AskAiResponseContractService();
        $context = [
            'compatibility' => [
                'overall_score'            => 82.5,
                'compatibility_highlights' => ['Price range aligns'],
                'compatibility_warnings'   => [],
                'physical_match_score'     => 85.0,
                'financial_match_score'    => 80.0,
                'terms_match_score'        => 78.0,
                'location_match_score'     => 87.0,
                'compatibility_trait_results' => [
                    'aligned'     => [['dimension' => 'property_type_alignment']],
                    'conflicting' => [],
                    'unresolved'  => [],
                ],
            ],
        ];

        $result = $contractService->buildContract('compatibility_signals', $context);

        $this->assertSame('contract_ready', $result['status']);
        $this->assertTrue($result['success']);
        $this->assertEmpty($result['missing_required_sources']);
        $this->assertContains('compatibility', $result['required_sources']);
    }

    // =========================================================================
    // Test 3 — compatibility_signals returns insufficient_context when absent
    // =========================================================================

    public function test_compatibility_signals_insufficient_context_when_compatibility_null(): void
    {
        $contractService = new AskAiResponseContractService();

        $result = $contractService->buildContract('compatibility_signals', ['compatibility' => null]);

        $this->assertSame('insufficient_context', $result['status']);
        $this->assertFalse($result['success']);
        $this->assertContains('compatibility', $result['missing_required_sources']);
    }

    public function test_compatibility_signals_insufficient_context_when_compatibility_absent(): void
    {
        $contractService = new AskAiResponseContractService();

        $result = $contractService->buildContract('compatibility_signals', []);

        $this->assertSame('insufficient_context', $result['status']);
        $this->assertContains('compatibility', $result['missing_required_sources']);
    }

    // =========================================================================
    // Test 4 — buyer_tenant_match returns contract_ready when compatibility present
    // =========================================================================

    public function test_buyer_tenant_match_contract_ready_when_compatibility_present(): void
    {
        $contractService = new AskAiResponseContractService();
        $context = [
            'compatibility' => [
                'overall_score'            => 90.0,
                'compatibility_highlights' => ['Lease term terms correspond'],
                'compatibility_summary_json' => ['result' => 'strong'],
                'compatibility_narrative'  => 'Criteria terms recorded on both listings correspond.',
            ],
        ];

        $result = $contractService->buildContract('buyer_tenant_match', $context);

        $this->assertSame('contract_ready', $result['status']);
        $this->assertTrue($result['success']);
        $this->assertEmpty($result['missing_required_sources']);
        $this->assertContains('compatibility', $result['required_sources']);
    }

    // =========================================================================
    // Test 5 — buyer_tenant_match returns insufficient_context when absent
    // =========================================================================

    public function test_buyer_tenant_match_insufficient_context_when_compatibility_null(): void
    {
        $contractService = new AskAiResponseContractService();

        $result = $contractService->buildContract('buyer_tenant_match', ['compatibility' => null]);

        $this->assertSame('insufficient_context', $result['status']);
        $this->assertFalse($result['success']);
        $this->assertContains('compatibility', $result['missing_required_sources']);
    }

    public function test_buyer_tenant_match_insufficient_context_when_compatibility_absent(): void
    {
        $contractService = new AskAiResponseContractService();

        $result = $contractService->buildContract('buyer_tenant_match', []);

        $this->assertSame('insufficient_context', $result['status']);
        $this->assertContains('compatibility', $result['missing_required_sources']);
    }

    // =========================================================================
    // Test 6 — source_attribution.versions includes compatibility_version
    // =========================================================================

    public function test_source_attribution_versions_includes_compatibility_version_when_present(): void
    {
        $promptService   = new AskAiPromptBuilderService(new AskAiKnowledgeSourceRegistry());
        $contractService = new AskAiResponseContractService();

        $context = [
            'success'         => true,
            'listing_type'    => 'buyer',
            'listing_id'      => 1,
            'context_version' => 'ASK_AI_CONTEXT_V1',
            'status'          => 'assembled',
            'listing'         => ['listing_id' => 1, 'listing_type' => 'buyer'],
            'compatibility'   => [
                'overall_score'            => 82.5,
                'compatibility_highlights' => ['Price range aligns'],
                'compatibility_warnings'   => [],
                'physical_match_score'     => 85.0,
                'financial_match_score'    => 80.0,
                'terms_match_score'        => 78.0,
                'location_match_score'     => 87.0,
                'version'                  => 'BYA_COMPAT_V1',
            ],
            'property_intelligence' => null,
            'location_intelligence' => null,
            'buyer_avatar'          => null,
            'tenant_avatar'         => null,
            'offer_analysis'        => null,
            'missing_sources'       => [],
            'warnings'              => [],
            'source_versions'       => [
                'ask_ai_context'                => 'ASK_AI_CONTEXT_V1',
                'property_intelligence_version' => null,
                'location_dna_lifestyle_version'=> null,
                'buyer_avatar_version'          => null,
                'tenant_avatar_version'         => null,
                'compatibility_version'         => 'BYA_COMPAT_V1',
            ],
            'assembled_at' => '2026-06-01T12:00:00.000000Z',
            'error'        => null,
        ];

        $contract = $contractService->buildContract('compatibility_signals', $context);
        $this->assertSame('contract_ready', $contract['status']);

        $package = $promptService->buildPromptPackage('What are the compatibility signals?', $context, $contract);

        $this->assertSame('prompt_ready', $package['status']);
        $this->assertArrayHasKey('versions', $package['source_attribution']);
        $this->assertArrayHasKey(
            'compatibility_version',
            $package['source_attribution']['versions'],
            'source_attribution.versions must contain compatibility_version'
        );
        $this->assertSame('BYA_COMPAT_V1', $package['source_attribution']['versions']['compatibility_version']);
    }

    public function test_source_attribution_versions_compatibility_version_null_when_no_compatibility(): void
    {
        $promptService   = new AskAiPromptBuilderService(new AskAiKnowledgeSourceRegistry());
        $contractService = new AskAiResponseContractService();

        $context = [
            'success'               => true,
            'listing_type'          => 'seller',
            'listing_id'            => 1,
            'context_version'       => 'ASK_AI_CONTEXT_V1',
            'status'                => 'assembled',
            'listing'               => ['listing_id' => 1, 'listing_type' => 'seller'],
            'property_intelligence' => [
                'property_highlights'           => ['Pool'],
                'property_strengths'            => ['Pool'],
                'property_positioning'          => 'Move-Up Home',
                'property_target_audiences'     => ['Families'],
                'property_personality_tags'     => ['Outdoor Living'],
                'property_story'                => 'Great home.',
                'property_intelligence_version' => 'PROPERTY_INTELLIGENCE_V1',
            ],
            'location_intelligence' => null,
            'buyer_avatar'          => null,
            'tenant_avatar'         => null,
            'compatibility'         => null,
            'offer_analysis'        => null,
            'missing_sources'       => [],
            'warnings'              => [],
            'source_versions'       => [
                'ask_ai_context'                => 'ASK_AI_CONTEXT_V1',
                'property_intelligence_version' => 'PROPERTY_INTELLIGENCE_V1',
                'location_dna_lifestyle_version'=> null,
                'buyer_avatar_version'          => null,
                'tenant_avatar_version'         => null,
                'compatibility_version'         => null,
            ],
            'assembled_at' => '2026-06-01T12:00:00.000000Z',
            'error'        => null,
        ];

        $contract = $contractService->buildContract('property_standout', $context);
        $this->assertSame('contract_ready', $contract['status']);

        $package = $promptService->buildPromptPackage('What makes this stand out?', $context, $contract);

        $this->assertSame('prompt_ready', $package['status']);
        $this->assertArrayHasKey('compatibility_version', $package['source_attribution']['versions']);
        $this->assertNull($package['source_attribution']['versions']['compatibility_version']);
    }

    // =========================================================================
    // Test 7 — No protected-class language in compatibility context payload fields
    // =========================================================================

    public function test_no_protected_class_language_in_compatibility_context_payload(): void
    {
        $service = $this->makeContextBuilderService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());
        $service->method('findBuyerTenantDnaProfile')->willReturn(null);
        $service->method('findCompatibilityScore')->willReturn($this->makeCompatibilityScore([
            'compatibility_trait_results' => [
                'aligned'     => [['dimension' => 'property_type_alignment']],
                'conflicting' => [],
                'unresolved'  => [],
            ],
        ]));
        $service->method('findAcceptedBidSummary')->willReturn(null);

        $result = $service->buildForListing('buyer', 1, $this->makePairOptions());

        $this->assertNotNull($result['compatibility']);

        $payloadText = strtolower(json_encode($result['compatibility']));

        foreach (self::PROTECTED_CLASS_TERMS as $term) {
            $this->assertStringNotContainsString(
                strtolower($term),
                $payloadText,
                "Protected-class term '{$term}' must not appear in compatibility context payload"
            );
        }
    }

    // =========================================================================
    // Test 8 — No ranking or auto-decisioning language in response rules
    // =========================================================================

    public function test_no_ranking_language_in_compatibility_signals_response_rules(): void
    {
        $contractService = new AskAiResponseContractService();
        $context = [
            'compatibility' => ['overall_score' => 80.0, 'compatibility_highlights' => []],
        ];

        $result = $contractService->buildContract('compatibility_signals', $context);
        $this->assertSame('contract_ready', $result['status']);

        $rulesText = strtolower(implode(' ', $result['response_rules']));

        foreach (self::RANKING_TERMS as $term) {
            $this->assertStringNotContainsString(
                strtolower($term),
                $rulesText,
                "Ranking/auto-decisioning term '{$term}' must not appear in compatibility_signals response rules"
            );
        }
    }

    public function test_no_ranking_language_in_buyer_tenant_match_response_rules(): void
    {
        $contractService = new AskAiResponseContractService();
        $context = [
            'compatibility' => ['overall_score' => 80.0, 'compatibility_highlights' => []],
        ];

        $result = $contractService->buildContract('buyer_tenant_match', $context);
        $this->assertSame('contract_ready', $result['status']);

        $rulesText = strtolower(implode(' ', $result['response_rules']));

        foreach (self::RANKING_TERMS as $term) {
            $this->assertStringNotContainsString(
                strtolower($term),
                $rulesText,
                "Ranking/auto-decisioning term '{$term}' must not appear in buyer_tenant_match response rules"
            );
        }
    }

    // =========================================================================
    // Test 9 — Static governance scan: no write calls in AskAiContextBuilderService
    // =========================================================================

    public function test_context_builder_service_contains_no_write_calls(): void
    {
        $path = $this->serviceFilePath();
        $this->assertFileExists($path, 'AskAiContextBuilderService file not found at expected path');

        $content = file_get_contents($path);

        $codeLines = implode("\n", array_filter(
            explode("\n", $content),
            static function (string $line): bool {
                $trimmed = ltrim($line);
                return !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
            }
        ));

        $prohibitedWriteCalls = [
            '->save(',
            '->create(',
            '->update(',
            '->delete(',
            '->insert(',
            'DB::statement(',
            'DB::insert(',
            'DB::update(',
            'DB::delete(',
        ];

        foreach ($prohibitedWriteCalls as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "AskAiContextBuilderService must not contain write call '{$term}'"
            );
        }
    }

    public function test_context_builder_service_contains_no_openai_or_http_calls(): void
    {
        $path    = $this->serviceFilePath();
        $content = file_get_contents($path);

        $codeLines = implode("\n", array_filter(
            explode("\n", $content),
            static function (string $line): bool {
                $trimmed = ltrim($line);
                return !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
            }
        ));

        $prohibited = [
            'use OpenAI\\',
            'use OpenAi\\',
            'use GuzzleHttp\\',
            'OpenAI::',
            'Http::post',
            'Http::get',
            'curl_exec',
        ];

        foreach ($prohibited as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "AskAiContextBuilderService must not import or call '{$term}'"
            );
        }
    }
}
