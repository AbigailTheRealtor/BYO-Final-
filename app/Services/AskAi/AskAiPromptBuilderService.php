<?php

namespace App\Services\AskAi;

/**
 * AskAiPromptBuilderService — Phase 3 Prompt Package Assembler
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: Deterministic prompt-package assembler for Ask AI (Phase 3).
 * Consumes context output from AskAiContextBuilderService and a contract from
 * AskAiResponseContractService, then produces a fully-governed, structured prompt
 * package that a future answer-generation phase can forward to an LLM.
 *
 * This service MUST NEVER:
 *   - Call any external LLM, language model, or external HTTP service.
 *   - Execute any database write (save, update, create, delete).
 *   - Infer, estimate, or invent values for missing data.
 *   - Generate any AI answer text or call OpenAI.
 *   - Create routes, controllers, Blade views, or database migrations.
 *   - Rank, sort, or recommend any listing, offer, buyer, or agent.
 *   - Reference or infer protected class characteristics.
 * ==================================================================================
 */
class AskAiPromptBuilderService
{
    public const PROMPT_PACKAGE_VERSION = 'ASK_AI_PROMPT_PACKAGE_V1';

    public function __construct(private AskAiKnowledgeSourceRegistry $registry)
    {
    }

    /**
     * Twelve deterministic system-instruction strings included in every non-failed
     * prompt package. These are governance directives for the LLM layer and must
     * not be generated dynamically.
     */
    private const SYSTEM_INSTRUCTIONS = [
        'You are an AI assistant for a real estate platform. You may only use the approved context data explicitly provided to you in this prompt.',
        'Do not reference protected class characteristics including race, color, national origin, religion, sex, familial status, or disability.',
        'Do not invent, estimate, or infer data that is not present in the provided context.',
        'Do not generate recommendations beyond what the structured data explicitly supports.',
        'All platform data referenced in your response must be attributed to its stated source as specified in the source_attribution block.',
        'Do not call any external service, API, or database. Operate solely on the data provided in this prompt package.',
        'Do not store, log, repeat, or expose any user-identifying or personally identifiable information.',
        'Responses must be factual, neutral in tone, and entirely free of speculation or conjecture.',
        'If required_disclosures are provided, every disclosure must appear verbatim in your response without alteration.',
        'If source_attribution is required, it must appear clearly in your response before or after the substantive content.',
        'You must not generate or imply legal, financial, investment, or professional advice of any kind.',
        'Do not make decisions on behalf of users. Present data and analysis only; all decisions remain with the user.',
    ];

    /**
     * Static list of content categories that must never appear in a generated response.
     */
    private const MUST_NOT_INCLUDE = [
        'protected_class_characteristics',
        'speculation_or_conjecture',
        'externally_sourced_data',
        'personally_identifiable_information',
        'legal_or_financial_advice',
        'invented_or_inferred_values',
    ];

    /**
     * Fixed disclosure string appended when the status is 'insufficient_context'.
     */
    private const UNAVAILABLE_DATA_DISCLOSURE =
        'Unavailable Data Notice: One or more required data sources are not available for this listing. ' .
        'The response may be incomplete or cannot be generated until the missing data is provided.';

    /**
     * Assemble a fully-governed prompt package from a user question, assembled context,
     * and a response contract.
     *
     * Output contract — always returns exactly these 15 keys:
     *   success                  bool        — true only when status is 'prompt_ready'
     *   status                   string      — 'prompt_ready' | 'blocked' | 'insufficient_context' | 'unsupported' | 'failed'
     *   prompt_package_version   string      — always 'ASK_AI_PROMPT_PACKAGE_V1'
     *   question                 string      — the question as supplied by the caller
     *   question_type            string      — from contract['question_type']
     *   system_instructions      string[]    — 12 deterministic governance strings
     *   developer_instructions   array       — assembled from contract governance fields
     *   allowed_context          array       — context filtered to only contract-approved dot-notation paths
     *   source_attribution       array       — required_sources + optional used sources + versions sub-array
     *   required_disclosures     string[]    — from contract; augmented on insufficient_context
     *   refusal_template         string|null — from contract['refusal_template']; non-null on blocked
     *   missing_required_sources string[]    — from contract['missing_required_sources']
     *   context_versions         array       — compound version map: context source_versions + contract_version + assembled_at
     *   response_format          array       — static response shape directives
     *   error                    string|null — null on non-failed paths; error message on 'failed'
     *
     * Status routing:
     *   contract['status'] === 'contract_ready'      → 'prompt_ready'
     *   contract['status'] === 'refusal_required'    → 'blocked'
     *   contract['status'] === 'insufficient_context'→ 'insufficient_context'
     *   contract['status'] === 'unsupported'         → 'unsupported'
     *   anything else                                → 'failed'
     *
     * @param  string $userQuestion  The user's question text.
     * @param  array  $context       Assembled context from AskAiContextBuilderService.
     * @param  array  $contract      Response contract from AskAiResponseContractService.
     * @return array
     */
    public function buildPromptPackage(string $userQuestion, array $context, array $contract): array
    {
        try {
            $contractStatus = $contract['status'] ?? '';
            $questionType   = $contract['question_type'] ?? '';

            return match ($contractStatus) {
                'contract_ready'       => $this->buildPromptReadyPackage(
                    $userQuestion, $context, $contract, $questionType
                ),
                'refusal_required'     => $this->buildBlockedPackage(
                    $userQuestion, $context, $contract, $questionType
                ),
                'insufficient_context' => $this->buildInsufficientContextPackage(
                    $userQuestion, $context, $contract, $questionType
                ),
                'unsupported'          => $this->buildUnsupportedPackage(
                    $userQuestion, $context, $contract, $questionType
                ),
                default                => $this->buildFailedPackage(
                    $userQuestion, $questionType, $context, $contract,
                    'Unrecognised contract status: ' . $contractStatus
                ),
            };
        } catch (\Throwable $e) {
            return $this->buildFailedPackage($userQuestion, '', [], [], $e->getMessage());
        }
    }

    // =========================================================================
    // Status-specific builders
    // =========================================================================

    private function buildPromptReadyPackage(
        string $userQuestion,
        array $context,
        array $contract,
        string $questionType
    ): array {
        return [
            'success'                  => true,
            'status'                   => 'prompt_ready',
            'prompt_package_version'   => self::PROMPT_PACKAGE_VERSION,
            'question'                 => $userQuestion,
            'question_type'            => $questionType,
            'system_instructions'      => self::SYSTEM_INSTRUCTIONS,
            'developer_instructions'   => $this->buildDeveloperInstructions($contract),
            'allowed_context'          => $this->filterAllowedContext($context, $contract['allowed_context'] ?? []),
            'source_attribution'       => $this->buildSourceAttribution($context, $contract),
            'required_disclosures'     => $contract['required_disclosures'] ?? [],
            'refusal_template'         => $contract['refusal_template'] ?? null,
            'missing_required_sources' => $contract['missing_required_sources'] ?? [],
            'context_versions'         => $this->buildContextVersions($context, $contract),
            'response_format'          => $this->buildResponseFormat($contract),
            'error'                    => null,
        ];
    }

    private function buildBlockedPackage(
        string $userQuestion,
        array $context,
        array $contract,
        string $questionType
    ): array {
        return [
            'success'                  => false,
            'status'                   => 'blocked',
            'prompt_package_version'   => self::PROMPT_PACKAGE_VERSION,
            'question'                 => $userQuestion,
            'question_type'            => $questionType,
            'system_instructions'      => self::SYSTEM_INSTRUCTIONS,
            'developer_instructions'   => $this->buildDeveloperInstructions($contract),
            'allowed_context'          => [],
            'source_attribution'       => $this->buildSourceAttribution([], $contract),
            'required_disclosures'     => $contract['required_disclosures'] ?? [],
            'refusal_template'         => $contract['refusal_template'] ?? null,
            'missing_required_sources' => $contract['missing_required_sources'] ?? [],
            'context_versions'         => $this->buildContextVersions($context, $contract),
            'response_format'          => $this->buildResponseFormat($contract),
            'error'                    => null,
        ];
    }

    private function buildInsufficientContextPackage(
        string $userQuestion,
        array $context,
        array $contract,
        string $questionType
    ): array {
        $disclosures   = $contract['required_disclosures'] ?? [];
        $disclosures[] = self::UNAVAILABLE_DATA_DISCLOSURE;

        return [
            'success'                  => false,
            'status'                   => 'insufficient_context',
            'prompt_package_version'   => self::PROMPT_PACKAGE_VERSION,
            'question'                 => $userQuestion,
            'question_type'            => $questionType,
            'system_instructions'      => self::SYSTEM_INSTRUCTIONS,
            'developer_instructions'   => $this->buildDeveloperInstructions($contract),
            'allowed_context'          => [],
            'source_attribution'       => $this->buildSourceAttribution([], $contract),
            'required_disclosures'     => $disclosures,
            'refusal_template'         => $contract['refusal_template'] ?? null,
            'missing_required_sources' => $contract['missing_required_sources'] ?? [],
            'context_versions'         => $this->buildContextVersions($context, $contract),
            'response_format'          => $this->buildResponseFormat($contract),
            'error'                    => null,
        ];
    }

    private function buildUnsupportedPackage(
        string $userQuestion,
        array $context,
        array $contract,
        string $questionType
    ): array {
        return [
            'success'                  => false,
            'status'                   => 'unsupported',
            'prompt_package_version'   => self::PROMPT_PACKAGE_VERSION,
            'question'                 => $userQuestion,
            'question_type'            => $questionType,
            'system_instructions'      => self::SYSTEM_INSTRUCTIONS,
            'developer_instructions'   => $this->buildDeveloperInstructions($contract),
            'allowed_context'          => [],
            'source_attribution'       => $this->buildSourceAttribution([], $contract),
            'required_disclosures'     => $contract['required_disclosures'] ?? [],
            'refusal_template'         => $contract['refusal_template'] ?? null,
            'missing_required_sources' => $contract['missing_required_sources'] ?? [],
            'context_versions'         => $this->buildContextVersions($context, $contract),
            'response_format'          => $this->buildResponseFormat($contract),
            'error'                    => null,
        ];
    }

    private function buildFailedPackage(
        string $userQuestion,
        string $questionType,
        array $context,
        array $contract,
        string $errorMessage
    ): array {
        return [
            'success'                  => false,
            'status'                   => 'failed',
            'prompt_package_version'   => self::PROMPT_PACKAGE_VERSION,
            'question'                 => $userQuestion,
            'question_type'            => $questionType,
            'system_instructions'      => [],
            'developer_instructions'   => [],
            'allowed_context'          => [],
            'source_attribution'       => ['sources' => [], 'required_sources' => [], 'versions' => []],
            'required_disclosures'     => [],
            'refusal_template'         => null,
            'missing_required_sources' => [],
            'context_versions'         => $this->buildContextVersions($context, $contract),
            'response_format'          => $this->buildResponseFormat([]),
            'error'                    => $errorMessage,
        ];
    }

    // =========================================================================
    // Component builders
    // =========================================================================

    /**
     * Build the developer_instructions array from the contract governance fields.
     *
     * Assembles a structured map of:
     *   response_rules        — governance rules from the contract
     *   required_disclosures  — disclosures mandated by the contract
     *   required_sources      — top-level source names required for this question type
     *   allowed_context_paths — dot-notation paths permitted for context use
     */
    private function buildDeveloperInstructions(array $contract): array
    {
        return [
            'response_rules'        => $contract['response_rules'] ?? [],
            'required_disclosures'  => $contract['required_disclosures'] ?? [],
            'required_sources'      => $contract['required_sources'] ?? [],
            'allowed_context_paths' => $contract['allowed_context'] ?? [],
        ];
    }

    /**
     * Filter the full assembled context to only the dot-notation paths permitted by the contract.
     *
     * Each path in $allowedPaths is a dot-notation string such as
     * 'property_intelligence.property_highlights'. Only those nested keys are extracted;
     * no additional context bleeds through.
     *
     * @param  array    $context      Full assembled context from AskAiContextBuilderService.
     * @param  string[] $allowedPaths Dot-notation paths from contract['allowed_context'].
     * @return array
     */
    private function filterAllowedContext(array $context, array $allowedPaths): array
    {
        $filtered = [];

        foreach ($allowedPaths as $path) {
            $segments = explode('.', $path);

            if (count($segments) === 1) {
                $key = $segments[0];
                if (array_key_exists($key, $context)) {
                    $filtered[$key] = $context[$key];
                }
                continue;
            }

            $topKey   = $segments[0];
            $childKey = $segments[1];

            if (!isset($context[$topKey]) || !is_array($context[$topKey])) {
                continue;
            }

            if (!array_key_exists($childKey, $context[$topKey])) {
                continue;
            }

            if (!isset($filtered[$topKey])) {
                $filtered[$topKey] = [];
            }

            $filtered[$topKey][$childKey] = $context[$topKey][$childKey];
        }

        return $filtered;
    }

    /**
     * Build the source_attribution array from required_sources and context version metadata.
     *
     * required_sources starts with the contract-declared required sources, then is augmented
     * with any optional top-level source keys that appear in the contract's allowed_context
     * paths AND exist as non-null values in the assembled context. This ensures that
     * location_intelligence (and other optional sources) are attributed whenever they
     * contribute data to the prompt package.
     *
     * Versions sub-array always contains:
     *   property_intelligence_version — from context['source_versions']['property_intelligence_version']
     *   ask_ai_context                — from context['source_versions']['ask_ai_context']
     *   compatibility_version         — from context['source_versions']['compatibility_version']
     *   contract_version              — from contract['contract_version']
     *
     * Optional source attribution uses two complementary mechanisms:
     *   1. allowed_context path loop — any top-level source key present in the contract's
     *      allowed_context paths AND non-null in context is appended (covers location_intelligence
     *      and any future optional sources declared per question type).
     *   2. Explicit avatar attribution — buyer_avatar and tenant_avatar are appended when
     *      non-null in context regardless of allowed_context, ensuring attribution is complete
     *      even when avatar paths are not enumerated in a contract.
     */
    private function buildSourceAttribution(array $context, array $contract): array
    {
        $sourceVersions  = $context['source_versions'] ?? [];
        $requiredSources = $contract['required_sources'] ?? [];

        // Add any optional source whose top-level key appears in allowed_context paths
        // AND exists as non-null in the assembled context (covers location_intelligence
        // and any other optional source declared in a question-type contract).
        $allowedPaths = $contract['allowed_context'] ?? [];
        foreach ($allowedPaths as $path) {
            $topKey = explode('.', $path)[0];
            if (
                !in_array($topKey, $requiredSources, true)
                && isset($context[$topKey])
                && $context[$topKey] !== null
            ) {
                $requiredSources[] = $topKey;
            }
        }

        // Explicit avatar attribution: append avatar sources when non-null in context
        // even when not listed in allowed_context, ensuring attribution is complete.
        if (isset($context['buyer_avatar']) && $context['buyer_avatar'] !== null) {
            if (!in_array('buyer_avatar', $requiredSources, true)) {
                $requiredSources[] = 'buyer_avatar';
            }
        }

        if (isset($context['tenant_avatar']) && $context['tenant_avatar'] !== null) {
            if (!in_array('tenant_avatar', $requiredSources, true)) {
                $requiredSources[] = 'tenant_avatar';
            }
        }

        // Build structured sources array from registry lookup.
        // Unknown keys fall back to null label/version rather than throwing.
        $sources = [];
        foreach ($requiredSources as $key) {
            $sourceDef = $this->registry->getSource($key);
            if ($sourceDef === null) {
                $sources[] = ['key' => $key, 'label' => null, 'version' => null];
            } else {
                $sources[] = [
                    'key'     => $key,
                    'label'   => $sourceDef['label'],
                    'version' => $sourceDef['version_key'],
                ];
            }
        }

        return [
            'sources'          => $sources,
            'required_sources' => $requiredSources,
            'versions'         => [
                'property_intelligence_version' => $sourceVersions['property_intelligence_version'] ?? null,
                'ask_ai_context'                => $sourceVersions['ask_ai_context'] ?? null,
                'compatibility_version'         => $sourceVersions['compatibility_version'] ?? null,
                'contract_version'              => $contract['contract_version'] ?? null,
            ],
        ];
    }

    /**
     * Build the compound context_versions map.
     *
     * Aggregates all version identifiers from the assembled context's source_versions,
     * plus the contract_version and assembled_at timestamp, into a single top-level key
     * so downstream runners have a complete audit trail of which data versions were used.
     *
     * Keys:
     *   ask_ai_context                — context assembly version
     *   property_intelligence_version — property DNA intelligence version
     *   location_dna_lifestyle_version— location DNA lifestyle version
     *   buyer_avatar_version          — buyer avatar version
     *   tenant_avatar_version         — tenant avatar version
     *   compatibility_version         — compatibility score version
     *   contract_version              — response contract version
     *   assembled_at                  — ISO-8601 context assembly timestamp
     */
    private function buildContextVersions(array $context, array $contract): array
    {
        $sv = $context['source_versions'] ?? [];

        return [
            'ask_ai_context'                => $sv['ask_ai_context'] ?? null,
            'property_intelligence_version' => $sv['property_intelligence_version'] ?? null,
            'location_dna_lifestyle_version'=> $sv['location_dna_lifestyle_version'] ?? null,
            'buyer_avatar_version'          => $sv['buyer_avatar_version'] ?? null,
            'tenant_avatar_version'         => $sv['tenant_avatar_version'] ?? null,
            'compatibility_version'         => $sv['compatibility_version'] ?? null,
            'contract_version'              => $contract['contract_version'] ?? null,
            'assembled_at'                  => $context['assembled_at'] ?? null,
        ];
    }

    /**
     * Build the static response_format shape.
     *
     * must_include_source_attribution is true whenever the contract declares required_sources.
     */
    private function buildResponseFormat(array $contract): array
    {
        $requiredSources = $contract['required_sources'] ?? [];

        return [
            'type'                           => 'structured_text',
            'must_include_source_attribution' => !empty($requiredSources),
            'must_include_disclosures'        => true,
            'must_not_include'                => self::MUST_NOT_INCLUDE,
        ];
    }
}
