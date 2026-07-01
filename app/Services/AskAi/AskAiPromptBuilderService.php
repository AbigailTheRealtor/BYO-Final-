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
     * Per-question-type synthesis directives injected into developer_instructions.
     *
     * These guide the LLM toward prose paragraphs rather than raw field echoes or
     * bullet lists.  The '_default' entry fires for any question type not explicitly
     * listed.  Each entry is an array of plain-English instruction strings.
     *
     * Rules for adding entries:
     *   - Every directive must be a complete, imperative sentence.
     *   - Never reference protected-class characteristics.
     *   - Directives supplement (not replace) the governance response_rules from the
     *     contract; they carry no binding authority on their own.
     */
    private const SYNTHESIS_DIRECTIVES = [
        '_default' => [
            'Compose all answers as complete natural-language paragraphs written in full sentences.',
            'When multiple related data points are available, weave them into a single coherent sentence or paragraph rather than listing field names or raw values separately.',
            'Always end your answer with a period, exclamation mark, or question mark. Never return a sentence fragment or a phrase that trails off without terminal punctuation.',
            'Never echo a raw field value verbatim. If a context value is a comma-separated list (e.g. "Central Air, Mini-Split Unit(s)" or "Electric, Gas"), describe it in a complete sentence (e.g. "The property is equipped with central air conditioning and a mini-split unit system.").',
        ],

        'listing_facts' => [
            'Compose all answers as complete natural-language paragraphs written in full sentences. Never echo field names, JSON keys, or raw data values.',
            'Always end your answer with a period, exclamation mark, or question mark. Sentence fragments and unpunctuated phrases are not acceptable.',
            'Never return a raw comma-separated list as the answer. Always embed values inside a complete sentence — for example, if cooling is "Central Air, Mini-Split Unit(s)", write "This property features central air conditioning along with a mini-split unit system." not the bare list.',
            'When multiple related fields are available — for example HOA presence + fee + payment schedule, or pets allowed + breed restrictions + pet fee, or seller credit offered + concession amount, or financing type + pre-approval status — combine them into a single coherent sentence that reads naturally.',
            'When ownership-cost fields are present (HOA fee, CDD fee, annual property taxes), summarize all applicable costs together in one paragraph so the reader understands the full picture.',
            'When pet policy fields are present (allowed status, breed/weight limits, pet deposit, monthly pet fee), describe the complete pet policy in one flowing paragraph.',
            'When financing or buyer-criteria fields are present (loan pre-approved, financing type, inspection/appraisal/financing contingencies), describe the buyer\'s financial situation in one coherent paragraph.',
            'When agent profile or agent preset data is present alongside listing fields, synthesize both into a unified answer — for example, state the listing detail and then describe how the agent\'s services relate to it.',
        ],

        'property_standout' => [
            'Compose a narrative paragraph using all available highlights, strengths, and story data.',
            'Weave specific details from property_highlights, property_strengths, and property_story into one flowing paragraph — do not enumerate them as a bullet list.',
            'Always end your answer with a period, exclamation mark, or question mark.',
        ],

        'marketing_angles' => [
            'Write a single marketing-oriented paragraph that draws on property positioning, personality tags, and location context together.',
            'Avoid bullet lists — produce one cohesive paragraph a marketing professional would use.',
            'Always end your answer with a period, exclamation mark, or question mark.',
        ],

        'suited_audience' => [
            'Describe the ideal audience in one paragraph using lifestyle and preference terms only.',
            'Blend property features with avatar preference data when both are present.',
            'Always end your answer with a period, exclamation mark, or question mark.',
        ],

        'buyer_tenant_match' => [
            'Summarize compatibility in one paragraph that cites both the overall score and the key matching signals.',
            'Do not enumerate scores as raw numbers alone — reference them in context, e.g., "strong financial compatibility (score: 87)".',
            'Always end your answer with a period, exclamation mark, or question mark.',
        ],

        'compatibility_signals' => [
            'Report the most meaningful compatibility signals in one paragraph, referencing specific scores only to provide context.',
            'Distinguish highlights from warnings in the same paragraph if both are present.',
            'Always end your answer with a period, exclamation mark, or question mark.',
        ],

        'agent_profile' => [
            'Describe the agent\'s services and credentials in one flowing paragraph.',
            'When preset data is available, include key services and specializations in the description.',
            'Always end your answer with a period, exclamation mark, or question mark.',
        ],

        'educational' => [
            'Provide a clear, instructive paragraph using plain language appropriate for a home buyer or renter new to the process.',
            'Always end your answer with a period, exclamation mark, or question mark.',
        ],
    ];

    /**
     * Twelve deterministic system-instruction strings included in every non-failed
     * prompt package. These are governance directives for the LLM layer and must
     * not be generated dynamically.
     */
    private const SYSTEM_INSTRUCTIONS = [
        'You are an AI assistant for a real estate platform. You may only use the approved context data explicitly provided to you in this prompt.',
        'Do not reference protected class characteristics including race, color, national origin, religion, sex, familial status, or disability. Never describe, profile, or speculate about the people who live in an area or who a property is "for." Do not steer: never characterize a neighborhood, area, or school as good, bad, safe, dangerous, desirable, or use phrases like "perfect for families," "great for young professionals," or "family-friendly." Describe locations only with objective, listing-sourced facts (nearby parks, dining, shopping, transit, commute times, and disclosed school district names). When describing suitability, frame it around the property\'s features and uses, never around the type of person.',
        'Do not invent, estimate, or infer data that is not present in the provided context.',
        'Do not generate recommendations beyond what the structured data explicitly supports.',
        'All platform data referenced in your response must be attributed to its stated source as specified in the source_attribution block.',
        'Do not call any external service, API, or database. Operate solely on the data provided in this prompt package.',
        'Do not store, log, repeat, or expose any user-identifying or personally identifiable information.',
        'Responses must be factual, neutral in tone, and entirely free of speculation or conjecture. Do not use superlatives or unsupported comparatives such as best, safest, perfect, guaranteed, ideal, highest quality, unbeatable, rare, or "better than" — unless you are directly quoting disclosed listing information, in which case attribute it as a quote. Prefer neutral framing such as "according to the listing," "based on the information provided," "the seller disclosed," or "the landlord indicated."',
        'If required_disclosures are provided, every disclosure must appear verbatim in your response without alteration.',
        'If source_attribution is required, it must appear clearly in your response before or after the substantive content.',
        'You must not generate or imply legal, financial, tax, investment, lending, appraisal, or inspection advice of any kind. You may explain what a term means (for example NOI, cap rate, NNN, CAM, SBA, 1031) and restate the disclosed value for this listing, but you must never compute or judge investment quality, predict returns, benchmark against the market, or opine on whether a figure is good. Never recommend negotiation strategy, offer prices, or whether to accept, reject, or counter an offer; never recommend approving or denying a tenant or buyer; never identify or evaluate any party\'s negotiating position, leverage, strengths, or weaknesses. You explain disclosed information; you do not advise on it.',
        'Do not make decisions on behalf of users. Present data and analysis only; all decisions remain with the user. Respond using a JSON object with exactly one key named "answer". The value of "answer" must be a complete, natural-language paragraph written in full sentences that ends with a period, exclamation mark, or question mark. Never return bare booleans ("Yes", "No"), single words, raw field values, comma-separated lists, or JSON sub-objects as the answer — always compose a coherent, readable sentence or paragraph using the data from the context. For example, if the context contains "cooling: Central Air, Mini-Split Unit(s)", write "The property is equipped with central air conditioning and a mini-split unit system." — never echo the raw comma-separated value.',
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
     *   synthesis_directives  — per-question-type prose composition instructions
     */
    private function buildDeveloperInstructions(array $contract): array
    {
        $questionType = $contract['question_type'] ?? '_default';

        return [
            'response_rules'        => $contract['response_rules'] ?? [],
            'required_disclosures'  => $contract['required_disclosures'] ?? [],
            'required_sources'      => $contract['required_sources'] ?? [],
            'allowed_context_paths' => $contract['allowed_context'] ?? [],
            'synthesis_directives'  => self::SYNTHESIS_DIRECTIVES[$questionType]
                                           ?? self::SYNTHESIS_DIRECTIVES['_default'],
        ];
    }

    /**
     * Fields from an enriched faq_answers entry that are safe to forward to the LLM.
     * Internal model attributes (id, listing_id, listing_type, created_at, updated_at)
     * must never be forwarded.
     */
    private const FAQ_SAFE_FIELDS = [
        'answer_text',
        'question_label',
        'question_group',
        'intelligence_category',
    ];

    /**
     * Filter the full assembled context to only the dot-notation paths permitted by the contract.
     *
     * Each path in $allowedPaths is a dot-notation string such as
     * 'property_intelligence.property_highlights'. Only those nested keys are extracted;
     * no additional context bleeds through.
     *
     * Special handling for 'faq_answers': when entries are enriched objects (arrays),
     * only the four safe fields (answer_text, question_label, question_group,
     * intelligence_category) are forwarded. Internal model attributes are stripped.
     * Legacy raw-string entries are forwarded as-is for backward compatibility.
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
                    $filtered[$key] = $key === 'faq_answers'
                        ? $this->sanitizeFaqAnswers($context[$key])
                        : $context[$key];
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
     * Sanitize the faq_answers array for safe LLM forwarding.
     *
     * For enriched entries (arrays), only FAQ_SAFE_FIELDS are kept.
     * Legacy raw-string entries are forwarded unchanged for backward compatibility.
     *
     * @param  mixed $faqAnswers  The faq_answers value from the assembled context.
     * @return array
     */
    private function sanitizeFaqAnswers(mixed $faqAnswers): array
    {
        if (!is_array($faqAnswers)) {
            return [];
        }

        $sanitized = [];
        foreach ($faqAnswers as $key => $entry) {
            if (is_array($entry)) {
                $safe = [];
                foreach (self::FAQ_SAFE_FIELDS as $field) {
                    if (array_key_exists($field, $entry)) {
                        $safe[$field] = $entry[$field];
                    }
                }
                $sanitized[$key] = $safe;
            } else {
                $sanitized[$key] = $entry;
            }
        }

        return $sanitized;
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
