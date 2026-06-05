<?php

namespace App\Services\AskAi;

/**
 * AskAiFollowUpQuestionService — Static Follow-Up Question Engine
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: Returns up to 3 safe, deterministic follow-up question entries based on
 * the incoming question_type from a completed Ask AI classification result.
 * All questions are drawn from a governed static mapping keyed on question_type.
 *
 * This service MUST NEVER:
 *   - Call any external LLM, language model, or external HTTP service.
 *   - Execute any database read or write (no DB calls whatsoever).
 *   - Infer, estimate, or invent values for missing data.
 *   - Generate AI answer text or call OpenAI.
 *   - Reference or infer protected class characteristics.
 *   - Surface questions in the prohibited categories defined in the spec
 *     (legal, brokerage/negotiation, lending, tax, investment, market prediction,
 *     fair housing/demographic, protected class suitability, crime, safety,
 *     school-demographic).
 * ==================================================================================
 */
class AskAiFollowUpQuestionService
{
    /**
     * Approved category types — all follow-up question_type values must be one of these.
     */
    private const APPROVED_TYPES = [
        'property_standout',
        'suited_audience',
        'buyer_tenant_match',
        'compatibility_signals',
        'missing_data',
        'marketing_angles',
        'educational',
    ];

    /**
     * Maximum follow-up questions returned per call.
     */
    private const MAX_FOLLOW_UPS = 3;

    /**
     * Static mapping of incoming question_type → follow-up question pool.
     *
     * Each pool contains up to 5 entries; at most MAX_FOLLOW_UPS are returned.
     * Every question must be free of prohibited phrases:
     *   legal, tax, lending, brokerage, investment, crime, safety,
     *   school-demographic, market-prediction, fair-housing, protected class.
     */
    private const FOLLOW_UP_MAP = [

        'property_standout' => [
            [
                'label'         => 'Who would find this listing a practical fit?',
                'question'      => 'Who would find this listing a practical fit?',
                'question_type' => 'suited_audience',
            ],
            [
                'label'         => 'How does this listing compare to what a typical buyer or tenant seeks?',
                'question'      => 'How does this listing compare to what a typical buyer or tenant seeks?',
                'question_type' => 'buyer_tenant_match',
            ],
            [
                'label'         => 'What are the strongest marketing angles for this listing?',
                'question'      => 'What are the strongest marketing angles for this listing?',
                'question_type' => 'marketing_angles',
            ],
        ],

        'suited_audience' => [
            [
                'label'         => 'What are the standout features of this listing?',
                'question'      => 'What are the standout features of this listing?',
                'question_type' => 'property_standout',
            ],
            [
                'label'         => 'What compatibility signals stand out for an interested party?',
                'question'      => 'What compatibility signals stand out for an interested party?',
                'question_type' => 'compatibility_signals',
            ],
            [
                'label'         => 'What information is missing that would help identify the right audience?',
                'question'      => 'What information is missing that would help identify the right audience?',
                'question_type' => 'missing_data',
            ],
        ],

        'buyer_tenant_match' => [
            [
                'label'         => 'What are the standout features of this listing?',
                'question'      => 'What are the standout features of this listing?',
                'question_type' => 'property_standout',
            ],
            [
                'label'         => 'What compatibility signals are present in this listing?',
                'question'      => 'What compatibility signals are present in this listing?',
                'question_type' => 'compatibility_signals',
            ],
            [
                'label'         => 'What additional information would improve a match evaluation?',
                'question'      => 'What additional information would improve a match evaluation?',
                'question_type' => 'missing_data',
            ],
        ],

        'compatibility_signals' => [
            [
                'label'         => 'How well does this listing align with a typical buyer or tenant profile?',
                'question'      => 'How well does this listing align with a typical buyer or tenant profile?',
                'question_type' => 'buyer_tenant_match',
            ],
            [
                'label'         => 'Who would find this listing the most practical fit?',
                'question'      => 'Who would find this listing the most practical fit?',
                'question_type' => 'suited_audience',
            ],
            [
                'label'         => 'What key information is absent from this listing?',
                'question'      => 'What key information is absent from this listing?',
                'question_type' => 'missing_data',
            ],
        ],

        'missing_data' => [
            [
                'label'         => 'What information is available about this listing\'s key features?',
                'question'      => 'What information is available about this listing\'s key features?',
                'question_type' => 'property_standout',
            ],
            [
                'label'         => 'What marketing angles are available based on the current listing details?',
                'question'      => 'What marketing angles are available based on the current listing details?',
                'question_type' => 'marketing_angles',
            ],
            [
                'label'         => 'How does this platform\'s auction process work?',
                'question'      => 'How does this platform\'s auction process work?',
                'question_type' => 'educational',
            ],
        ],

        'marketing_angles' => [
            [
                'label'         => 'What are the standout features of this listing?',
                'question'      => 'What are the standout features of this listing?',
                'question_type' => 'property_standout',
            ],
            [
                'label'         => 'Who would benefit most from this listing?',
                'question'      => 'Who would benefit most from this listing?',
                'question_type' => 'suited_audience',
            ],
            [
                'label'         => 'What additional details would strengthen the listing\'s presentation?',
                'question'      => 'What additional details would strengthen the listing\'s presentation?',
                'question_type' => 'missing_data',
            ],
        ],

        'educational' => [
            [
                'label'         => 'What are the key details of this specific listing?',
                'question'      => 'What are the key details of this specific listing?',
                'question_type' => 'property_standout',
            ],
            [
                'label'         => 'Who would be a practical fit for this listing?',
                'question'      => 'Who would be a practical fit for this listing?',
                'question_type' => 'suited_audience',
            ],
            [
                'label'         => 'How does this listing align with what buyers or tenants typically seek?',
                'question'      => 'How does this listing align with what buyers or tenants typically seek?',
                'question_type' => 'buyer_tenant_match',
            ],
        ],
    ];

    /**
     * Return up to 3 follow-up question entries for the given final response.
     *
     * Returns [] immediately when:
     *   - $finalResponse['success'] is false
     *   - $finalResponse['status'] is not 'ready'
     *   - The classification question_type is unrecognised
     *
     * @param  array  $finalResponse    Normalised final response from AskAiFinalResponseBuilderService.
     * @param  array  $classification   Output of AskAiQuestionClassifierService::classify(); may be empty.
     * @param  array  $sourceAttribution Source attribution array from the final response; reserved for future use.
     * @return array<int, array{label: string, question: string, question_type: string}>
     */
    public function forResult(array $finalResponse, array $classification = [], array $sourceAttribution = []): array
    {
        // $sourceAttribution is accepted per spec signature and reserved for context-aware filtering (see #2075).
        // It is intentionally unused in this static phase — do not remove the parameter.

        if (($finalResponse['success'] ?? false) !== true) {
            return [];
        }

        $status = $finalResponse['status'] ?? '';

        if ($status !== 'ready') {
            return [];
        }

        $questionType = $classification['question_type'] ?? '';

        if (!in_array($questionType, self::APPROVED_TYPES, true)) {
            return [];
        }

        $pool = self::FOLLOW_UP_MAP[$questionType] ?? [];

        return array_slice($pool, 0, self::MAX_FOLLOW_UPS);
    }
}
