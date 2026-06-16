<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\AskAi\AskAiKnowledgeSourceRegistry;
use App\Services\AskAi\AskAiPromptBuilderService;
use App\Services\AskAi\AskAiQuestionClassifierService;
use App\Services\AskAi\AskAiResponseContractService;
use PHPUnit\Framework\TestCase;

/**
 * AskAiAgentProfileTest
 *
 * Pure unit tests — no database, no Laravel TestCase, no DB traits.
 * Validates that the agent_profile question type and context keys are
 * correctly wired across the entire Ask AI pipeline.
 *
 * Test coverage (cases A–I):
 *   A. Classifier — agent_profile keywords route to 'agent_profile' type
 *   B. Classifier — agent_profile confidence is 0.90
 *   C. Classifier — listing_facts keywords are not intercepted by agent_profile keywords
 *   D. Classifier — prohibited still fires before agent_profile
 *   E. Contract — agent_profile contract is present and has correct shape
 *   F. Contract — required_sources is empty (contract always ready)
 *   G. Contract — allowed_context includes agent_profile and agent_presets top-level keys
 *   H. Prompt builder — filterAllowedContext passes agent_profile and agent_presets to allowed context
 *   I. Knowledge source registry — agent_profile and agent_presets sources are registered with correct fields
 */
class AskAiAgentProfileTest extends TestCase
{
    // =========================================================================
    // Case A — Classifier keywords
    // =========================================================================

    /**
     * @dataProvider agentProfileKeywordProvider
     */
    public function test_case_A_agent_profile_keywords_classify_as_agent_profile(string $question): void
    {
        $classifier = new AskAiQuestionClassifierService();
        $result     = $classifier->classify($question);

        $this->assertSame(
            'agent_profile',
            $result['question_type'],
            "Question \"{$question}\" should classify as 'agent_profile', got '{$result['question_type']}'"
        );
    }

    public static function agentProfileKeywordProvider(): array
    {
        return [
            ['Who is the agent for this listing?'],
            ['Tell me about the agent'],
            ['What is the agent bio?'],
            ['What are the agent credentials?'],
            ['What services does the agent offer?'],
            ['Tell me about this agent profile'],
            ['What is the agent brokerage?'],
            ['Is the agent available on weekends?'],
            ['What agent specialties are listed?'],
            ['How many transactions has the agent completed?'],
            ['What are the agent service offerings?'],
            ['Who is my agent?'],
            ['What is the agent communication style?'],
            ['How long has the agent been licensed?'],
        ];
    }

    // =========================================================================
    // Case B — Confidence is 0.90
    // =========================================================================

    public function test_case_B_agent_profile_confidence_is_0_90(): void
    {
        $classifier = new AskAiQuestionClassifierService();
        $result     = $classifier->classify('Who is the agent for this listing?');

        $this->assertSame('agent_profile', $result['question_type']);
        $this->assertEqualsWithDelta(0.90, $result['confidence'], 0.001);
    }

    // =========================================================================
    // Case C — listing_facts keywords are not swallowed by agent_profile
    // =========================================================================

    /**
     * @dataProvider listingFactsKeywordsNotInterceptedProvider
     */
    public function test_case_C_listing_facts_keywords_not_intercepted_by_agent_profile(string $question): void
    {
        $classifier = new AskAiQuestionClassifierService();
        $result     = $classifier->classify($question);

        $this->assertNotSame(
            'agent_profile',
            $result['question_type'],
            "Listing-facts question \"{$question}\" must NOT be intercepted by agent_profile"
        );
        $this->assertSame(
            'listing_facts',
            $result['question_type'],
            "Listing-facts question \"{$question}\" must classify as listing_facts"
        );
    }

    public static function listingFactsKeywordsNotInterceptedProvider(): array
    {
        return [
            ['How many bedrooms does this property have?'],
            ['What is the asking price?'],
            ['What is the monthly rent?'],
            ['Does it have a pool?'],
            ['What is the square footage?'],
            ['When was this home built?'],
        ];
    }

    // =========================================================================
    // Case D — prohibited still fires before agent_profile
    // =========================================================================

    public function test_case_D_prohibited_fires_before_agent_profile(): void
    {
        $classifier = new AskAiQuestionClassifierService();

        $result = $classifier->classify('What is the race of the agent for this listing?');
        $this->assertSame(
            'prohibited',
            $result['question_type'],
            "Questions containing prohibited keywords must classify as 'prohibited' even when agent keywords are present"
        );
    }

    // =========================================================================
    // Case E — Contract shape
    // =========================================================================

    public function test_case_E_agent_profile_contract_is_present(): void
    {
        $contract = new AskAiResponseContractService();
        $result   = $contract->buildContract('agent_profile', [
            'listing'        => ['listing_type' => 'seller', 'listing_id' => 1],
            'agent_profile'  => null,
            'agent_presets'  => null,
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('question_type', $result);
        $this->assertSame('agent_profile', $result['question_type']);
    }

    public function test_case_E_contract_has_all_required_keys(): void
    {
        $contract = new AskAiResponseContractService();
        $result   = $contract->buildContract('agent_profile', [
            'listing'       => ['listing_type' => 'seller', 'listing_id' => 1],
            'agent_profile' => null,
            'agent_presets' => null,
        ]);

        $requiredKeys = [
            'success', 'status', 'question_type', 'allowed_context',
            'required_sources', 'missing_required_sources', 'response_rules',
            'required_disclosures', 'refusal_template', 'contract_version',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Contract must have key '{$key}'");
        }
    }

    // =========================================================================
    // Case F — required_sources is empty (always contract_ready)
    // =========================================================================

    public function test_case_F_required_sources_is_empty(): void
    {
        $contract = new AskAiResponseContractService();
        $result   = $contract->buildContract('agent_profile', []);

        $this->assertSame([], $result['required_sources']);
    }

    public function test_case_F_contract_is_ready_even_with_null_agent_data(): void
    {
        $contract = new AskAiResponseContractService();
        $result   = $contract->buildContract('agent_profile', [
            'listing'       => ['listing_type' => 'seller', 'listing_id' => 1],
            'agent_profile' => null,
            'agent_presets' => null,
        ]);

        $this->assertTrue($result['success'], 'Contract must be ready even when agent_profile is null');
        $this->assertSame('contract_ready', $result['status']);
    }

    public function test_case_F_contract_is_ready_when_context_is_empty(): void
    {
        $contract = new AskAiResponseContractService();
        $result   = $contract->buildContract('agent_profile', []);

        $this->assertTrue($result['success'], 'Contract must be ready even with empty context');
        $this->assertSame('contract_ready', $result['status']);
    }

    // =========================================================================
    // Case G — allowed_context includes agent_profile and agent_presets
    // =========================================================================

    public function test_case_G_allowed_context_includes_agent_profile(): void
    {
        $contract = new AskAiResponseContractService();
        $result   = $contract->buildContract('agent_profile', []);

        $this->assertContains(
            'agent_profile',
            $result['allowed_context'],
            "agent_profile must be in allowed_context"
        );
    }

    public function test_case_G_allowed_context_includes_agent_presets(): void
    {
        $contract = new AskAiResponseContractService();
        $result   = $contract->buildContract('agent_profile', []);

        $this->assertContains(
            'agent_presets',
            $result['allowed_context'],
            "agent_presets must be in allowed_context"
        );
    }

    public function test_case_G_allowed_context_includes_listing_type_and_id(): void
    {
        $contract = new AskAiResponseContractService();
        $result   = $contract->buildContract('agent_profile', []);

        $this->assertContains('listing.listing_type', $result['allowed_context']);
        $this->assertContains('listing.listing_id', $result['allowed_context']);
    }

    // =========================================================================
    // Case H — Prompt builder includes agent_profile + agent_presets in allowed_context
    // =========================================================================

    public function test_case_H_prompt_builder_includes_agent_profile_in_allowed_context(): void
    {
        $registry = new AskAiKnowledgeSourceRegistry();
        $builder  = new AskAiPromptBuilderService($registry);

        $agentProfileData = [
            'agent_name'    => 'Jane Smith',
            'brokerage'     => 'Premier Realty',
            'bio'           => 'Experienced agent in the Tampa Bay area.',
            'license_no'    => 'BK12345',
        ];

        $context = [
            'listing'       => ['listing_type' => 'seller', 'listing_id' => 1],
            'agent_profile' => $agentProfileData,
            'agent_presets' => ['presets' => [], 'preset_count' => 0],
            'source_versions' => [
                'ask_ai_context'                => 'ASK_AI_CONTEXT_V1',
                'property_intelligence_version' => null,
                'location_dna_lifestyle_version'=> null,
                'buyer_avatar_version'          => null,
                'tenant_avatar_version'         => null,
                'compatibility_version'         => null,
            ],
            'assembled_at' => '2026-01-01T00:00:00Z',
        ];

        $contract = (new AskAiResponseContractService())->buildContract('agent_profile', $context);
        $package  = $builder->buildPromptPackage('Who is the agent?', $context, $contract);

        $this->assertSame('prompt_ready', $package['status'], 'Package status must be prompt_ready');
        $this->assertArrayHasKey('agent_profile', $package['allowed_context']);
        $this->assertSame($agentProfileData, $package['allowed_context']['agent_profile']);
    }

    public function test_case_H_prompt_builder_includes_agent_presets_in_allowed_context(): void
    {
        $registry = new AskAiKnowledgeSourceRegistry();
        $builder  = new AskAiPromptBuilderService($registry);

        $presetData = ['presets' => [['role' => 'Seller', 'services' => 'MLS listing, photography']], 'preset_count' => 1];

        $context = [
            'listing'       => ['listing_type' => 'seller', 'listing_id' => 1],
            'agent_profile' => ['agent_name' => 'John Doe'],
            'agent_presets' => $presetData,
            'source_versions' => [
                'ask_ai_context'                => 'ASK_AI_CONTEXT_V1',
                'property_intelligence_version' => null,
                'location_dna_lifestyle_version'=> null,
                'buyer_avatar_version'          => null,
                'tenant_avatar_version'         => null,
                'compatibility_version'         => null,
            ],
            'assembled_at' => '2026-01-01T00:00:00Z',
        ];

        $contract = (new AskAiResponseContractService())->buildContract('agent_profile', $context);
        $package  = $builder->buildPromptPackage('What are the agent service presets?', $context, $contract);

        $this->assertSame('prompt_ready', $package['status']);
        $this->assertArrayHasKey('agent_presets', $package['allowed_context']);
        $this->assertSame($presetData, $package['allowed_context']['agent_presets']);
    }

    public function test_case_H_prompt_builder_is_prompt_ready_when_agent_data_is_null(): void
    {
        $registry = new AskAiKnowledgeSourceRegistry();
        $builder  = new AskAiPromptBuilderService($registry);

        $context = [
            'listing'       => ['listing_type' => 'seller', 'listing_id' => 1],
            'agent_profile' => null,
            'agent_presets' => null,
            'source_versions' => [
                'ask_ai_context'                => 'ASK_AI_CONTEXT_V1',
                'property_intelligence_version' => null,
                'location_dna_lifestyle_version'=> null,
                'buyer_avatar_version'          => null,
                'tenant_avatar_version'         => null,
                'compatibility_version'         => null,
            ],
            'assembled_at' => '2026-01-01T00:00:00Z',
        ];

        $contract = (new AskAiResponseContractService())->buildContract('agent_profile', $context);
        $package  = $builder->buildPromptPackage('Who is the agent?', $context, $contract);

        $this->assertSame(
            'prompt_ready',
            $package['status'],
            'Package must be prompt_ready even when agent_profile and agent_presets are null (no required_sources gate)'
        );
    }

    // =========================================================================
    // Case I — Knowledge source registry
    // =========================================================================

    public function test_case_I_registry_has_agent_profile_source(): void
    {
        $registry = new AskAiKnowledgeSourceRegistry();

        $this->assertTrue($registry->isApproved('agent_profile'));

        $source = $registry->getSource('agent_profile');
        $this->assertNotNull($source);
        $this->assertSame('agent_profile', $source['key']);
        $this->assertSame('AGENT_PROFILE_V1', $source['version_key']);
        $this->assertContains('agent_profile', $source['allowed_for_question_types']);
    }

    public function test_case_I_registry_has_agent_presets_source(): void
    {
        $registry = new AskAiKnowledgeSourceRegistry();

        $this->assertTrue($registry->isApproved('agent_presets'));

        $source = $registry->getSource('agent_presets');
        $this->assertNotNull($source);
        $this->assertSame('agent_presets', $source['key']);
        $this->assertSame('AGENT_PRESETS_V1', $source['version_key']);
        $this->assertContains('agent_profile', $source['allowed_for_question_types']);
    }

    public function test_case_I_agent_profile_source_has_all_required_fields(): void
    {
        $registry = new AskAiKnowledgeSourceRegistry();

        foreach (['agent_profile', 'agent_presets'] as $key) {
            $source = $registry->getSource($key);
            $this->assertNotNull($source, "Source '{$key}' must be registered");

            foreach (['key', 'label', 'description', 'version_key', 'allowed_for_question_types'] as $field) {
                $this->assertArrayHasKey($field, $source, "Source '{$key}' must have field '{$field}'");
            }
        }
    }

    public function test_case_I_context_builder_buildEmptyPayload_includes_agent_keys(): void
    {
        $serviceFile = dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiContextBuilderService.php';
        $this->assertFileExists($serviceFile);

        $content = file_get_contents($serviceFile);

        $this->assertStringContainsString("'agent_profile'", $content, "buildEmptyPayload must include agent_profile key");
        $this->assertStringContainsString("'agent_presets'", $content, "buildEmptyPayload must include agent_presets key");
        $this->assertStringContainsString('AgentProfileLoader', $content, "Service must import AgentProfileLoader");
        $this->assertStringContainsString('AgentPresetLoader', $content, "Service must import AgentPresetLoader");
    }

    public function test_case_I_context_builder_buildForListing_returns_agent_keys(): void
    {
        $serviceFile = dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiContextBuilderService.php';
        $content     = file_get_contents($serviceFile);

        $this->assertStringContainsString('buildAgentProfile', $content, "Service must define buildAgentProfile()");
        $this->assertStringContainsString('buildAgentPresets', $content, "Service must define buildAgentPresets()");
    }

    // =========================================================================
    // Governance scan — no prohibited content in agent_profile contract or keywords
    // =========================================================================

    public function test_governance_agent_profile_contract_has_required_privacy_rule(): void
    {
        $contract = new AskAiResponseContractService();
        $result   = $contract->buildContract('agent_profile', []);

        $privateDataRule = false;
        foreach ($result['response_rules'] as $rule) {
            if (stripos($rule, 'private') !== false || stripos($rule, 'email') !== false || stripos($rule, 'phone') !== false) {
                $privateDataRule = true;
                break;
            }
        }

        $this->assertTrue(
            $privateDataRule,
            'agent_profile contract must include a response_rule about not revealing private contact info'
        );
    }

    public function test_governance_agent_profile_contract_has_disclosure(): void
    {
        $contract = new AskAiResponseContractService();
        $result   = $contract->buildContract('agent_profile', []);

        $this->assertNotEmpty(
            $result['required_disclosures'],
            'agent_profile contract must have at least one required_disclosure'
        );
    }
}
