<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiPromptBuilderService;
use PHPUnit\Framework\TestCase;

/**
 * AskAiPromptBuilderServiceTest
 *
 * Pure unit tests — no database, no Laravel TestCase, no DB traits.
 * AskAiPromptBuilderService is stateless and requires no mocking.
 *
 * Test coverage (cases A–O):
 *   A. contract_ready contract produces 'prompt_ready' status
 *   B. refusal_required contract produces 'blocked' status
 *   C. insufficient_context contract produces 'insufficient_context' status
 *   D. unsupported contract produces 'unsupported' status
 *   E. Unrecognised contract status produces 'failed' status
 *   F. Exception thrown inside buildPromptPackage produces 'failed' status
 *   G. PROMPT_PACKAGE_VERSION constant equals 'ASK_AI_PROMPT_PACKAGE_V1'
 *   H. All 15 output keys present in a prompt_ready response
 *   I. All 15 output keys present in blocked/unsupported/insufficient_context responses
 *   J. All 15 output keys present on the failed path
 *   K. allowed_context is filtered to only dot-notation paths from contract; no extra context bleeds through
 *   L. source_attribution contains required_sources and a versions sub-array with correct keys
 *   M. insufficient_context appends the fixed unavailable-data disclosure; missing_required_sources surfaced
 *   N. Service file contains no OpenAI or HTTP calls (static grep on non-comment lines)
 *   O. Service file contains no write calls (static grep on non-comment lines)
 */
class AskAiPromptBuilderServiceTest extends TestCase
{
    /**
     * The approved 15 keys that must be present in every buildPromptPackage() response.
     */
    private const REQUIRED_PACKAGE_KEYS = [
        'success',
        'status',
        'prompt_package_version',
        'question',
        'question_type',
        'system_instructions',
        'developer_instructions',
        'allowed_context',
        'source_attribution',
        'required_disclosures',
        'refusal_template',
        'missing_required_sources',
        'context_versions',
        'response_format',
        'error',
    ];

    /**
     * Required keys inside every source_attribution array.
     */
    private const SOURCE_ATTRIBUTION_KEYS = [
        'required_sources',
        'versions',
    ];

    /**
     * Required keys inside source_attribution['versions'].
     */
    private const SOURCE_ATTRIBUTION_VERSION_KEYS = [
        'property_intelligence_version',
        'ask_ai_context',
        'contract_version',
    ];

    /**
     * Required keys inside context_versions.
     */
    private const CONTEXT_VERSIONS_KEYS = [
        'ask_ai_context',
        'property_intelligence_version',
        'location_dna_lifestyle_version',
        'buyer_avatar_version',
        'tenant_avatar_version',
        'compatibility_version',
        'contract_version',
        'assembled_at',
    ];

    /**
     * Absolute path to the service file — derived without base_path() so this
     * works in pure PHPUnit\Framework\TestCase (no Laravel container).
     */
    private function serviceFilePath(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiPromptBuilderService.php';
    }

    private function makeService(): AskAiPromptBuilderService
    {
        return new AskAiPromptBuilderService();
    }

    /**
     * Build a minimal contract_ready contract stub.
     */
    private function makeContractReady(array $overrides = []): array
    {
        return array_merge([
            'success'                  => true,
            'status'                   => 'contract_ready',
            'question_type'            => 'property_standout',
            'allowed_context'          => [
                'property_intelligence.property_highlights',
                'property_intelligence.property_strengths',
                'listing.listing_title',
            ],
            'required_sources'         => ['property_intelligence'],
            'missing_required_sources' => [],
            'response_rules'           => [
                'Base response only on provided property highlights and strengths.',
                'Do not reference protected class characteristics.',
                'Attribute all claims to the property intelligence source.',
            ],
            'required_disclosures'     => [
                'Information is derived from structured property data and may not reflect all property features.',
            ],
            'refusal_template'         => null,
            'contract_version'         => 'ASK_AI_RESPONSE_CONTRACT_V1',
        ], $overrides);
    }

    /**
     * Build a minimal assembled context stub.
     */
    private function makeContext(array $overrides = []): array
    {
        return array_merge([
            'success'               => true,
            'listing_type'          => 'seller',
            'listing_id'            => 1,
            'context_version'       => 'ASK_AI_CONTEXT_V1',
            'status'                => 'assembled',
            'listing'               => [
                'listing_id'    => 1,
                'listing_title' => 'Beautiful Home',
                'city'          => 'Tampa',
                'state'         => 'FL',
                'property_type' => 'Single Family',
                'listing_status'=> 'approved',
            ],
            'property_intelligence' => [
                'property_strengths'            => ['Pool', 'Garage'],
                'property_highlights'           => ['Pool', 'Garage', 'Updated Kitchen'],
                'property_positioning'          => 'Move-Up Home',
                'property_target_audiences'     => ['Move-Up Families'],
                'property_personality_tags'     => ['Outdoor Living'],
                'property_story'                => 'A great home with a pool.',
                'property_intelligence_version' => 'PROPERTY_INTELLIGENCE_V1',
            ],
            'location_intelligence' => [
                'lifestyle_json'       => ['scores' => ['walkability' => 72]],
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
            'assembled_at'          => '2026-06-01T12:00:00.000000Z',
            'error'                 => null,
        ], $overrides);
    }

    // =========================================================================
    // Case A — contract_ready produces 'prompt_ready'
    // =========================================================================

    public function test_case_A_contract_ready_produces_prompt_ready_status(): void
    {
        $service = $this->makeService();
        $result  = $service->buildPromptPackage('What makes this property stand out?', $this->makeContext(), $this->makeContractReady());

        $this->assertSame('prompt_ready', $result['status']);
        $this->assertTrue($result['success']);
    }

    public function test_case_A_prompt_ready_carries_question(): void
    {
        $service  = $this->makeService();
        $question = 'What makes this property stand out?';
        $result   = $service->buildPromptPackage($question, $this->makeContext(), $this->makeContractReady());

        $this->assertSame($question, $result['question']);
    }

    public function test_case_A_prompt_ready_carries_question_type_from_contract(): void
    {
        $service = $this->makeService();
        $result  = $service->buildPromptPackage('q', $this->makeContext(), $this->makeContractReady(['question_type' => 'marketing_angles']));

        $this->assertSame('marketing_angles', $result['question_type']);
    }

    public function test_case_A_prompt_ready_system_instructions_has_twelve_entries(): void
    {
        $service = $this->makeService();
        $result  = $service->buildPromptPackage('q', $this->makeContext(), $this->makeContractReady());

        $this->assertIsArray($result['system_instructions']);
        $this->assertCount(12, $result['system_instructions']);
    }

    public function test_case_A_prompt_ready_system_instructions_include_no_decision_making_rule(): void
    {
        $service = $this->makeService();
        $result  = $service->buildPromptPackage('q', $this->makeContext(), $this->makeContractReady());

        $instructionText = implode(' ', $result['system_instructions']);
        $this->assertStringContainsStringIgnoringCase('decisions', $instructionText);
    }

    // =========================================================================
    // Case B — refusal_required produces 'blocked'
    // =========================================================================

    public function test_case_B_refusal_required_produces_blocked_status(): void
    {
        $service  = $this->makeService();
        $contract = $this->makeContractReady([
            'success'                  => false,
            'status'                   => 'refusal_required',
            'question_type'            => 'prohibited',
            'allowed_context'          => [],
            'required_sources'         => [],
            'missing_required_sources' => [],
            'response_rules'           => [],
            'required_disclosures'     => [],
            'refusal_template'         => 'This question type is not permitted on this platform.',
        ]);

        $result = $service->buildPromptPackage('Who is the best buyer for this?', $this->makeContext(), $contract);

        $this->assertSame('blocked', $result['status']);
        $this->assertFalse($result['success']);
    }

    public function test_case_B_blocked_allowed_context_is_empty(): void
    {
        $service  = $this->makeService();
        $contract = $this->makeContractReady([
            'status'                   => 'refusal_required',
            'allowed_context'          => ['property_intelligence.property_highlights'],
            'required_sources'         => [],
            'missing_required_sources' => [],
            'response_rules'           => [],
            'required_disclosures'     => [],
            'refusal_template'         => 'Not permitted.',
        ]);

        $result = $service->buildPromptPackage('q', $this->makeContext(), $contract);

        $this->assertSame('blocked', $result['status']);
        $this->assertSame([], $result['allowed_context']);
    }

    public function test_case_B_blocked_carries_refusal_template_from_contract(): void
    {
        $service  = $this->makeService();
        $contract = $this->makeContractReady([
            'status'           => 'refusal_required',
            'allowed_context'  => [],
            'required_sources' => [],
            'missing_required_sources' => [],
            'response_rules'   => [],
            'required_disclosures' => [],
            'refusal_template' => 'This question type is not permitted on this platform. No response can be generated.',
        ]);

        $result = $service->buildPromptPackage('q', $this->makeContext(), $contract);

        $this->assertSame(
            'This question type is not permitted on this platform. No response can be generated.',
            $result['refusal_template']
        );
    }

    // =========================================================================
    // Case C — insufficient_context produces 'insufficient_context'
    // =========================================================================

    public function test_case_C_insufficient_context_produces_correct_status(): void
    {
        $service  = $this->makeService();
        $contract = $this->makeContractReady([
            'success'                  => false,
            'status'                   => 'insufficient_context',
            'missing_required_sources' => ['property_intelligence'],
        ]);

        $result = $service->buildPromptPackage('q', $this->makeContext(), $contract);

        $this->assertSame('insufficient_context', $result['status']);
        $this->assertFalse($result['success']);
    }

    public function test_case_C_insufficient_context_allowed_context_is_empty(): void
    {
        $service  = $this->makeService();
        $contract = $this->makeContractReady([
            'status'                   => 'insufficient_context',
            'missing_required_sources' => ['property_intelligence'],
        ]);

        $result = $service->buildPromptPackage('q', $this->makeContext(), $contract);

        $this->assertSame([], $result['allowed_context']);
    }

    // =========================================================================
    // Case D — unsupported produces 'unsupported'
    // =========================================================================

    public function test_case_D_unsupported_produces_correct_status(): void
    {
        $service  = $this->makeService();
        $contract = [
            'success'                  => false,
            'status'                   => 'unsupported',
            'question_type'            => 'unknown_type',
            'allowed_context'          => [],
            'required_sources'         => [],
            'missing_required_sources' => [],
            'response_rules'           => [],
            'required_disclosures'     => [],
            'refusal_template'         => null,
            'contract_version'         => 'ASK_AI_RESPONSE_CONTRACT_V1',
        ];

        $result = $service->buildPromptPackage('q', $this->makeContext(), $contract);

        $this->assertSame('unsupported', $result['status']);
        $this->assertFalse($result['success']);
        $this->assertSame('unknown_type', $result['question_type']);
    }

    public function test_case_D_unsupported_allowed_context_is_empty(): void
    {
        $service  = $this->makeService();
        $contract = [
            'success' => false, 'status' => 'unsupported', 'question_type' => 'something_weird',
            'allowed_context' => [], 'required_sources' => [],
            'missing_required_sources' => [], 'response_rules' => [],
            'required_disclosures' => [], 'refusal_template' => null,
            'contract_version' => 'ASK_AI_RESPONSE_CONTRACT_V1',
        ];

        $result = $service->buildPromptPackage('q', $this->makeContext(), $contract);

        $this->assertSame([], $result['allowed_context']);
    }

    // =========================================================================
    // Case E — Unrecognised contract status produces 'failed'
    // =========================================================================

    public function test_case_E_unrecognised_contract_status_produces_failed(): void
    {
        $service  = $this->makeService();
        $contract = $this->makeContractReady(['status' => 'pending_review']);

        $result = $service->buildPromptPackage('q', $this->makeContext(), $contract);

        $this->assertSame('failed', $result['status']);
        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
    }

    public function test_case_E_empty_contract_status_produces_failed(): void
    {
        $service  = $this->makeService();
        $contract = $this->makeContractReady(['status' => '']);

        $result = $service->buildPromptPackage('q', $this->makeContext(), $contract);

        $this->assertSame('failed', $result['status']);
        $this->assertFalse($result['success']);
    }

    // =========================================================================
    // Case F — Exception thrown inside buildPromptPackage produces 'failed'
    // =========================================================================

    public function test_case_F_throwable_triggered_by_null_contract_status_produces_failed(): void
    {
        $service = $this->makeService();

        $result = $service->buildPromptPackage('q', $this->makeContext(), ['status' => null]);

        $this->assertSame('failed', $result['status']);
        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
    }

    public function test_case_F_failed_path_error_key_carries_message(): void
    {
        $service = $this->makeService();
        $result  = $service->buildPromptPackage('q', [], ['status' => null]);

        $this->assertSame('failed', $result['status']);
        $this->assertIsString($result['error']);
        $this->assertNotEmpty($result['error']);
    }

    // =========================================================================
    // Case G — PROMPT_PACKAGE_VERSION constant is correct
    // =========================================================================

    public function test_case_G_prompt_package_version_constant_is_correct(): void
    {
        $this->assertSame('ASK_AI_PROMPT_PACKAGE_V1', AskAiPromptBuilderService::PROMPT_PACKAGE_VERSION);
    }

    public function test_case_G_prompt_package_version_present_in_prompt_ready_response(): void
    {
        $service = $this->makeService();
        $result  = $service->buildPromptPackage('q', $this->makeContext(), $this->makeContractReady());

        $this->assertSame('ASK_AI_PROMPT_PACKAGE_V1', $result['prompt_package_version']);
    }

    public function test_case_G_prompt_package_version_present_in_failed_response(): void
    {
        $service = $this->makeService();
        $result  = $service->buildPromptPackage('q', $this->makeContext(), $this->makeContractReady(['status' => 'bogus']));

        $this->assertSame('ASK_AI_PROMPT_PACKAGE_V1', $result['prompt_package_version']);
    }

    // =========================================================================
    // Case H — All 15 output keys present in a prompt_ready response
    // =========================================================================

    public function test_case_H_all_15_keys_present_in_prompt_ready_response(): void
    {
        $service = $this->makeService();
        $result  = $service->buildPromptPackage('q', $this->makeContext(), $this->makeContractReady());

        foreach (self::REQUIRED_PACKAGE_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Key '{$key}' missing in prompt_ready response");
        }

        $this->assertCount(15, array_intersect_key($result, array_flip(self::REQUIRED_PACKAGE_KEYS)));
    }

    public function test_case_H_prompt_ready_error_key_is_null(): void
    {
        $service = $this->makeService();
        $result  = $service->buildPromptPackage('q', $this->makeContext(), $this->makeContractReady());

        $this->assertNull($result['error']);
    }

    public function test_case_H_prompt_ready_context_versions_carries_expected_keys(): void
    {
        $service = $this->makeService();
        $result  = $service->buildPromptPackage('q', $this->makeContext(), $this->makeContractReady());

        foreach (self::CONTEXT_VERSIONS_KEYS as $key) {
            $this->assertArrayHasKey($key, $result['context_versions'], "context_versions missing key '{$key}'");
        }

        $this->assertSame('ASK_AI_CONTEXT_V1', $result['context_versions']['ask_ai_context']);
        $this->assertSame('PROPERTY_INTELLIGENCE_V1', $result['context_versions']['property_intelligence_version']);
        $this->assertSame('ASK_AI_RESPONSE_CONTRACT_V1', $result['context_versions']['contract_version']);
        $this->assertSame('2026-06-01T12:00:00.000000Z', $result['context_versions']['assembled_at']);
    }

    public function test_case_H_prompt_ready_does_not_expose_question_as_user_question(): void
    {
        $service = $this->makeService();
        $result  = $service->buildPromptPackage('my question', $this->makeContext(), $this->makeContractReady());

        $this->assertArrayHasKey('question', $result);
        $this->assertSame('my question', $result['question']);
        $this->assertArrayNotHasKey('user_question', $result);
    }

    public function test_case_H_prompt_ready_does_not_expose_contract_version_as_top_level_key(): void
    {
        $service = $this->makeService();
        $result  = $service->buildPromptPackage('q', $this->makeContext(), $this->makeContractReady());

        $this->assertArrayNotHasKey('contract_version', $result);
        $this->assertArrayNotHasKey('assembled_at', $result);
    }

    // =========================================================================
    // Case I — All 15 output keys present in blocked/unsupported/insufficient responses
    // =========================================================================

    public function test_case_I_all_15_keys_present_in_blocked_response(): void
    {
        $service  = $this->makeService();
        $contract = $this->makeContractReady([
            'status'                   => 'refusal_required',
            'allowed_context'          => [],
            'required_sources'         => [],
            'missing_required_sources' => [],
            'response_rules'           => [],
            'required_disclosures'     => [],
            'refusal_template'         => 'Not permitted.',
        ]);
        $result = $service->buildPromptPackage('q', $this->makeContext(), $contract);

        foreach (self::REQUIRED_PACKAGE_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Key '{$key}' missing in blocked response");
        }
    }

    public function test_case_I_all_15_keys_present_in_unsupported_response(): void
    {
        $service  = $this->makeService();
        $contract = [
            'success' => false, 'status' => 'unsupported', 'question_type' => 'x',
            'allowed_context' => [], 'required_sources' => [],
            'missing_required_sources' => [], 'response_rules' => [],
            'required_disclosures' => [], 'refusal_template' => null,
            'contract_version' => 'ASK_AI_RESPONSE_CONTRACT_V1',
        ];
        $result = $service->buildPromptPackage('q', $this->makeContext(), $contract);

        foreach (self::REQUIRED_PACKAGE_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Key '{$key}' missing in unsupported response");
        }
    }

    public function test_case_I_all_15_keys_present_in_insufficient_context_response(): void
    {
        $service  = $this->makeService();
        $contract = $this->makeContractReady([
            'status'                   => 'insufficient_context',
            'missing_required_sources' => ['property_intelligence'],
        ]);
        $result = $service->buildPromptPackage('q', $this->makeContext(), $contract);

        foreach (self::REQUIRED_PACKAGE_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Key '{$key}' missing in insufficient_context response");
        }
    }

    // =========================================================================
    // Case J — All 15 output keys present on the failed path
    // =========================================================================

    public function test_case_J_all_15_keys_present_in_failed_response(): void
    {
        $service = $this->makeService();
        $result  = $service->buildPromptPackage('q', $this->makeContext(), $this->makeContractReady(['status' => 'mystery_status']));

        foreach (self::REQUIRED_PACKAGE_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Key '{$key}' missing in failed response");
        }
    }

    public function test_case_J_failed_response_success_is_false(): void
    {
        $service = $this->makeService();
        $result  = $service->buildPromptPackage('q', $this->makeContext(), $this->makeContractReady(['status' => 'mystery_status']));

        $this->assertFalse($result['success']);
        $this->assertSame('failed', $result['status']);
    }

    public function test_case_J_failed_response_refusal_template_is_null(): void
    {
        $service = $this->makeService();
        $result  = $service->buildPromptPackage('q', $this->makeContext(), $this->makeContractReady(['status' => 'mystery_status']));

        $this->assertNull($result['refusal_template']);
    }

    public function test_case_J_failed_response_missing_required_sources_is_empty_array(): void
    {
        $service = $this->makeService();
        $result  = $service->buildPromptPackage('q', $this->makeContext(), $this->makeContractReady(['status' => 'mystery_status']));

        $this->assertSame([], $result['missing_required_sources']);
    }

    // =========================================================================
    // Case K — allowed_context filtered to only contract dot-notation paths
    // =========================================================================

    public function test_case_K_allowed_context_contains_only_contract_approved_paths(): void
    {
        $service  = $this->makeService();
        $contract = $this->makeContractReady([
            'allowed_context' => [
                'property_intelligence.property_highlights',
                'property_intelligence.property_strengths',
            ],
        ]);
        $result   = $service->buildPromptPackage('q', $this->makeContext(), $contract);
        $filtered = $result['allowed_context'];

        $this->assertArrayHasKey('property_intelligence', $filtered);
        $this->assertArrayHasKey('property_highlights', $filtered['property_intelligence']);
        $this->assertArrayHasKey('property_strengths', $filtered['property_intelligence']);

        $this->assertArrayNotHasKey('property_positioning', $filtered['property_intelligence'] ?? []);
        $this->assertArrayNotHasKey('property_target_audiences', $filtered['property_intelligence'] ?? []);
        $this->assertArrayNotHasKey('location_intelligence', $filtered);
        $this->assertArrayNotHasKey('listing', $filtered);
    }

    public function test_case_K_extra_context_does_not_bleed_through(): void
    {
        $service  = $this->makeService();
        $contract = $this->makeContractReady([
            'allowed_context' => [
                'listing.listing_title',
            ],
        ]);
        $result   = $service->buildPromptPackage('q', $this->makeContext(), $contract);
        $filtered = $result['allowed_context'];

        $this->assertArrayNotHasKey('property_intelligence', $filtered);
        $this->assertArrayNotHasKey('location_intelligence', $filtered);
        $this->assertArrayNotHasKey('source_versions', $filtered);
        $this->assertArrayNotHasKey('buyer_avatar', $filtered);
        $this->assertArrayHasKey('listing', $filtered);
        $this->assertArrayHasKey('listing_title', $filtered['listing']);
        $this->assertArrayNotHasKey('city', $filtered['listing'] ?? []);
    }

    public function test_case_K_missing_context_key_in_path_is_silently_skipped(): void
    {
        $service  = $this->makeService();
        $contract = $this->makeContractReady([
            'allowed_context' => [
                'buyer_avatar.avatar_type',
                'listing.listing_title',
            ],
        ]);
        $context = $this->makeContext(['buyer_avatar' => null]);
        $result  = $service->buildPromptPackage('q', $context, $contract);

        $this->assertArrayNotHasKey('buyer_avatar', $result['allowed_context']);
        $this->assertArrayHasKey('listing', $result['allowed_context']);
    }

    // =========================================================================
    // Case L — source_attribution contains required_sources and versions sub-array
    // =========================================================================

    public function test_case_L_source_attribution_has_required_top_level_keys(): void
    {
        $service     = $this->makeService();
        $result      = $service->buildPromptPackage('q', $this->makeContext(), $this->makeContractReady());
        $attribution = $result['source_attribution'];

        foreach (self::SOURCE_ATTRIBUTION_KEYS as $key) {
            $this->assertArrayHasKey($key, $attribution, "source_attribution missing key '{$key}'");
        }
    }

    public function test_case_L_source_attribution_versions_has_required_keys(): void
    {
        $service  = $this->makeService();
        $result   = $service->buildPromptPackage('q', $this->makeContext(), $this->makeContractReady());
        $versions = $result['source_attribution']['versions'];

        foreach (self::SOURCE_ATTRIBUTION_VERSION_KEYS as $key) {
            $this->assertArrayHasKey($key, $versions, "source_attribution.versions missing key '{$key}'");
        }
    }

    public function test_case_L_source_attribution_required_sources_matches_contract(): void
    {
        $service  = $this->makeService();
        $contract = $this->makeContractReady(['required_sources' => ['property_intelligence', 'listing']]);
        $result   = $service->buildPromptPackage('q', $this->makeContext(), $contract);

        $this->assertSame(
            ['property_intelligence', 'listing'],
            $result['source_attribution']['required_sources']
        );
    }

    public function test_case_L_source_attribution_versions_sourced_from_context_source_versions(): void
    {
        $service  = $this->makeService();
        $result   = $service->buildPromptPackage('q', $this->makeContext(), $this->makeContractReady());
        $versions = $result['source_attribution']['versions'];

        $this->assertSame('PROPERTY_INTELLIGENCE_V1', $versions['property_intelligence_version']);
        $this->assertSame('ASK_AI_CONTEXT_V1', $versions['ask_ai_context']);
        $this->assertSame('ASK_AI_RESPONSE_CONTRACT_V1', $versions['contract_version']);
    }

    // =========================================================================
    // Case M — insufficient_context appends unavailable-data disclosure;
    //          missing_required_sources is surfaced at the package level
    // =========================================================================

    public function test_case_M_insufficient_context_appends_unavailable_data_disclosure(): void
    {
        $service  = $this->makeService();
        $contract = $this->makeContractReady([
            'status'                   => 'insufficient_context',
            'missing_required_sources' => ['property_intelligence'],
            'required_disclosures'     => [
                'Information is derived from structured property data.',
            ],
        ]);
        $result          = $service->buildPromptPackage('q', $this->makeContext(), $contract);
        $disclosureText  = implode(' ', $result['required_disclosures']);

        $this->assertStringContainsString('Unavailable Data Notice', $disclosureText);
    }

    public function test_case_M_insufficient_context_preserves_original_disclosures_and_appends(): void
    {
        $original = 'Information is derived from structured property data.';
        $service  = $this->makeService();
        $contract = $this->makeContractReady([
            'status'                   => 'insufficient_context',
            'missing_required_sources' => ['property_intelligence'],
            'required_disclosures'     => [$original],
        ]);
        $result      = $service->buildPromptPackage('q', $this->makeContext(), $contract);
        $disclosures = $result['required_disclosures'];

        $this->assertContains($original, $disclosures);
        $this->assertGreaterThan(1, count($disclosures));
    }

    public function test_case_M_missing_required_sources_surfaced_on_insufficient_context(): void
    {
        $service  = $this->makeService();
        $contract = $this->makeContractReady([
            'status'                   => 'insufficient_context',
            'missing_required_sources' => ['property_intelligence', 'location_intelligence'],
        ]);
        $result = $service->buildPromptPackage('q', $this->makeContext(), $contract);

        $this->assertContains('property_intelligence', $result['missing_required_sources']);
        $this->assertContains('location_intelligence', $result['missing_required_sources']);
    }

    public function test_case_M_missing_required_sources_empty_on_prompt_ready(): void
    {
        $service = $this->makeService();
        $result  = $service->buildPromptPackage('q', $this->makeContext(), $this->makeContractReady());

        $this->assertSame([], $result['missing_required_sources']);
    }

    public function test_case_M_prompt_ready_does_not_append_unavailable_data_disclosure(): void
    {
        $service        = $this->makeService();
        $result         = $service->buildPromptPackage('q', $this->makeContext(), $this->makeContractReady());
        $disclosureText = implode(' ', $result['required_disclosures']);

        $this->assertStringNotContainsString('Unavailable Data Notice', $disclosureText);
    }

    // =========================================================================
    // Case N — No OpenAI or HTTP calls exist in the service file
    // =========================================================================

    public function test_case_N_service_file_contains_no_openai_or_http_calls(): void
    {
        $path = $this->serviceFilePath();
        $this->assertFileExists($path, 'Service file does not exist at expected path');

        $content = file_get_contents($path);

        $codeLines = implode("\n", array_filter(
            explode("\n", $content),
            static function (string $line): bool {
                $trimmed = ltrim($line);
                return !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
            }
        ));

        $prohibitedImports = [
            'use OpenAI\\',
            'use OpenAi\\',
            'use GuzzleHttp\\',
            'OpenAI::',
            'ChatGPT::',
        ];

        foreach ($prohibitedImports as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "Service file must not import or call '{$term}'"
            );
        }

        $prohibitedHttpCalls = [
            'Http::post',
            'Http::get',
            'Http::put',
            'curl_exec',
            'file_get_contents(\'http',
            'file_get_contents("http',
        ];

        foreach ($prohibitedHttpCalls as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "Service file must not contain HTTP call '{$term}'"
            );
        }
    }

    // =========================================================================
    // Case O — No write calls exist in the service file
    // =========================================================================

    public function test_case_O_service_file_contains_no_write_calls(): void
    {
        $path = $this->serviceFilePath();
        $this->assertFileExists($path, 'Service file does not exist at expected path');

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
            '->update(',
            '->create(',
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
                "Service file must not contain write call '{$term}'"
            );
        }
    }
}
