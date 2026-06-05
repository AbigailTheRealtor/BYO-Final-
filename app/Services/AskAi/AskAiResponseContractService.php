<?php

namespace App\Services\AskAi;

/**
 * AskAiResponseContractService — Phase 2 Response Contract Layer
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: Deterministic response-contract layer for Ask AI (Phase 2).
 * Maps approved question types to allowed context fields, enforces refusal rules,
 * mandates source attribution, and returns deterministic contract shapes.
 * This is the governance and routing layer that all future Ask AI answer-generation
 * phases must call first.
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
class AskAiResponseContractService
{
    public const CONTRACT_VERSION = 'ASK_AI_RESPONSE_CONTRACT_V1';

    /**
     * Per-type contract definitions.
     *
     * Keys:
     *   allowed_context   — dot-notation paths to context fields permitted for this type
     *   required_sources  — top-level context keys that must be non-null for contract_ready
     *   response_rules    — governance rules the answer-generation layer must obey
     *   required_disclosures — disclosures that must accompany any generated response
     *   refusal_template  — non-null only for types that always refuse
     */
    private const TYPE_CONTRACTS = [
        'property_standout' => [
            'allowed_context' => [
                'property_intelligence.property_highlights',
                'property_intelligence.property_strengths',
                'property_intelligence.property_story',
                'listing.listing_title',
                'listing.property_type',
                'location_intelligence.location_narrative',
            ],
            'required_sources' => ['property_intelligence'],
            'response_rules' => [
                'Base response only on provided property highlights and strengths.',
                'Do not reference protected class characteristics.',
                'Do not invent or infer data not present in the context.',
                'Attribute all claims to the property intelligence source.',
            ],
            'required_disclosures' => [
                'Information is derived from structured property data and may not reflect all property features.',
            ],
            'refusal_template' => null,
        ],

        'suited_audience' => [
            'allowed_context' => [
                'property_intelligence.property_target_audiences',
                'property_intelligence.property_positioning',
                'property_intelligence.property_personality_tags',
                'listing.property_type',
                'location_intelligence.lifestyle_categories',
                'buyer_avatar.avatar_type',
                'buyer_avatar.buyer_personality_tags',
                'buyer_avatar.buyer_preference_summary',
                'tenant_avatar.avatar_type',
                'tenant_avatar.tenant_personality_tags',
                'tenant_avatar.tenant_preference_summary',
            ],
            'required_sources' => ['property_intelligence'],
            'response_rules' => [
                'Describe audiences using lifestyle and preference terms only.',
                'Do not reference protected class characteristics such as race, religion, national origin, familial status, disability, sex, or color.',
                'Base audience descriptions only on property and lifestyle data provided.',
                'Attribute all audience descriptions to the property intelligence source.',
                'When buyer or tenant avatar data is present, use only avatar_type, personality tags, and preference summary; never infer demographic identity from avatar data.',
            ],
            'required_disclosures' => [
                'Audience descriptions are based on property features and lifestyle data only. No protected class characteristics are referenced.',
                'Buyer and tenant avatar descriptions reflect lifestyle preferences and property fit signals only; they do not represent or infer any protected class characteristics.',
            ],
            'refusal_template' => null,
        ],

        'buyer_tenant_match' => [
            'allowed_context' => [
                'buyer_avatar.avatar_type',
                'buyer_avatar.buyer_match_preferences',
                'tenant_avatar.avatar_type',
                'tenant_avatar.tenant_match_preferences',
                'compatibility.compatibility_highlights',
                'compatibility.compatibility_summary_json',
                'compatibility.overall_score',
                'compatibility.compatibility_narrative',
            ],
            'required_sources' => ['compatibility'],
            'response_rules' => [
                'Use only the provided avatar and compatibility data.',
                'Do not reference protected class characteristics.',
                'Do not generate match recommendations beyond what the score and avatar data indicates.',
                'Attribute all match information to the compatibility score and avatar sources.',
            ],
            'required_disclosures' => [
                'Match information is based on structured compatibility scores and does not constitute a recommendation or guarantee.',
            ],
            'refusal_template' => null,
        ],

        'compatibility_signals' => [
            'allowed_context' => [
                'compatibility.compatibility_highlights',
                'compatibility.compatibility_warnings',
                'compatibility.physical_match_score',
                'compatibility.financial_match_score',
                'compatibility.terms_match_score',
                'compatibility.location_match_score',
                'compatibility.overall_score',
            ],
            'required_sources' => ['compatibility'],
            'response_rules' => [
                'Report only compatibility signals present in the provided score data.',
                'Do not infer signals not present in the data.',
                'Do not reference protected class characteristics.',
                'Attribute all signals to the compatibility score source.',
            ],
            'required_disclosures' => [
                'Compatibility signals are derived from structured scoring data and do not constitute legal or financial advice.',
            ],
            'refusal_template' => null,
        ],

        'missing_data' => [
            'allowed_context' => [
                'listing.listing_id',
                'listing.listing_type',
                'listing.property_type',
                'listing.listing_status',
                'missing_sources',
            ],
            'required_sources' => ['listing'],
            'response_rules' => [
                'Report only what is explicitly indicated as missing in the provided context.',
                'Do not infer or guess what data might be missing.',
                'Do not reference protected class characteristics.',
                'Attribute all missing-data statements to the listing and context assembly source.',
            ],
            'required_disclosures' => [
                'Missing data indicators are based on structured context assembly and may not reflect all available platform data.',
            ],
            'refusal_template' => null,
        ],

        'marketing_angles' => [
            'allowed_context' => [
                'property_intelligence.property_positioning',
                'property_intelligence.property_personality_tags',
                'property_intelligence.property_story',
                'property_intelligence.property_highlights',
                'location_intelligence.location_narrative',
                'location_intelligence.lifestyle_categories',
                'listing.listing_title',
                'listing.property_type',
            ],
            'required_sources' => ['property_intelligence'],
            'response_rules' => [
                'Use only property positioning, personality tags, and story from the provided context.',
                'Do not reference protected class characteristics.',
                'Do not invent marketing claims not supported by the context data.',
                'Attribute all marketing angles to the property intelligence source.',
            ],
            'required_disclosures' => [
                'Marketing angles are derived from structured property intelligence data and do not constitute legal or financial advice.',
            ],
            'refusal_template' => null,
        ],

        'educational' => [
            'allowed_context' => [],
            'required_sources' => [],
            'response_rules' => [
                'Provide only general real estate educational information.',
                'Do not reference specific platform listings, users, or data.',
                'Do not reference protected class characteristics.',
                'Label all responses as general educational information.',
            ],
            'required_disclosures' => [
                'General Educational Information: This response is for general informational purposes only and does not constitute legal, financial, or real estate advice specific to any listing or transaction.',
            ],
            'refusal_template' => null,
        ],

        'prohibited' => [
            'allowed_context' => [],
            'required_sources' => [],
            'response_rules' => [],
            'required_disclosures' => [],
            'refusal_template' => 'This question type is not permitted on this platform. No response can be generated.',
        ],
    ];

    /**
     * Build a deterministic response contract for the given question type and assembled context.
     *
     * Output contract — always returns exactly these keys:
     *   success                  bool        — true only when status is 'contract_ready'; false otherwise
     *   status                   string      — 'contract_ready' | 'insufficient_context' | 'refusal_required' | 'unsupported'
     *   question_type            string      — the question type as supplied by the caller
     *   allowed_context          string[]    — context key paths (dot-notation) permitted for this type
     *   required_sources         string[]    — top-level source names required for this question type
     *   missing_required_sources string[]    — required source names absent from the provided context
     *   response_rules           string[]    — governance rules the answer-generation layer must obey
     *   required_disclosures     string[]    — disclosures that must accompany any generated response
     *   refusal_template         string|null — refusal message; non-null only when status is 'refusal_required'
     *   contract_version         string      — always 'ASK_AI_RESPONSE_CONTRACT_V1'
     *
     * @param  string $questionType  One of the eight defined question types.
     * @param  array  $context       The assembled context array from AskAiContextBuilderService.
     * @return array
     */
    public function buildContract(string $questionType, array $context): array
    {
        if (!array_key_exists($questionType, self::TYPE_CONTRACTS)) {
            return $this->buildUnsupportedResponse($questionType);
        }

        $definition = self::TYPE_CONTRACTS[$questionType];

        if ($questionType === 'prohibited') {
            return $this->buildRefusalResponse($questionType, $definition);
        }

        $missingRequiredSources = $this->findMissingRequiredSources($definition['required_sources'], $context);

        if (!empty($missingRequiredSources)) {
            return $this->buildInsufficientContextResponse($questionType, $definition, $missingRequiredSources);
        }

        return $this->buildContractReadyResponse($questionType, $definition);
    }

    /**
     * Return which required source names are absent (null or missing) in the context.
     */
    private function findMissingRequiredSources(array $requiredSources, array $context): array
    {
        $missing = [];
        foreach ($requiredSources as $source) {
            if (!isset($context[$source]) || $context[$source] === null) {
                $missing[] = $source;
            }
        }
        return $missing;
    }

    /**
     * Build the shared base contract shape used by all response types.
     */
    private function baseContract(string $questionType, array $definition): array
    {
        return [
            'question_type'            => $questionType,
            'allowed_context'          => $definition['allowed_context'],
            'required_sources'         => $definition['required_sources'],
            'missing_required_sources' => [],
            'response_rules'           => $definition['response_rules'],
            'required_disclosures'     => $definition['required_disclosures'],
            'refusal_template'         => $definition['refusal_template'],
            'contract_version'         => self::CONTRACT_VERSION,
        ];
    }

    private function buildContractReadyResponse(string $questionType, array $definition): array
    {
        return array_merge(
            ['success' => true, 'status' => 'contract_ready'],
            $this->baseContract($questionType, $definition)
        );
    }

    private function buildInsufficientContextResponse(
        string $questionType,
        array $definition,
        array $missingRequiredSources
    ): array {
        $contract = array_merge(
            ['success' => false, 'status' => 'insufficient_context'],
            $this->baseContract($questionType, $definition)
        );
        $contract['missing_required_sources'] = $missingRequiredSources;
        return $contract;
    }

    private function buildRefusalResponse(string $questionType, array $definition): array
    {
        return array_merge(
            ['success' => false, 'status' => 'refusal_required'],
            $this->baseContract($questionType, $definition)
        );
    }

    private function buildUnsupportedResponse(string $questionType): array
    {
        return [
            'success'                  => false,
            'status'                   => 'unsupported',
            'question_type'            => $questionType,
            'allowed_context'          => [],
            'required_sources'         => [],
            'missing_required_sources' => [],
            'response_rules'           => [],
            'required_disclosures'     => [],
            'refusal_template'         => null,
            'contract_version'         => self::CONTRACT_VERSION,
        ];
    }
}
