<?php

namespace App\Services\AskAi;

/**
 * AskAiQuestionClassifierService — Deterministic Question Classifier
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: Deterministic keyword-based classifier for Ask AI user questions.
 * Accepts a plain question string and returns a structured classification
 * (question_type, confidence, reason) using keyword matching only.
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
class AskAiQuestionClassifierService
{
    /**
     * Keyword rule map for each question type.
     *
     * Each entry is an array of keyword strings. A match is detected when any
     * keyword appears in the lowercased question (case-insensitive substring match).
     *
     * Order matters: types are evaluated top-to-bottom; the first match wins.
     * 'prohibited' is checked before all other types to ensure hard refusals fire first.
     * 'compatibility_signals' is checked before 'buyer_tenant_match' so that
     * score-specific phrases (e.g. "match score") are not swallowed by buyer/tenant terms.
     * 'unsupported' is never listed here — it is the fallback when nothing matches.
     */
    private const KEYWORD_RULES = [
        'prohibited' => [
            'race',
            'racial',
            'race of',
            'religion',
            'religious',
            'nationality',
            'national origin',
            'ethnic',
            'ethnicity',
            'familial status',
            'disability',
            'handicap',
            'sex offender',
            'gender identity',
            'sexual orientation',
            'protected class',
            'fair housing',
            'discrimination',
            'discriminate',
            'neighborhood demographic',
            'neighborhood demographics',
            'diverse neighborhood',
            'neighborhood diversity',
            'demographics',
            'who lives there',
            'type of people',
            'kind of people',
            'school district',
            'school district quality',
            'school quality',
            'best school',
            'good school',
            'best schools',
            'worst schools',
            'schools near',
            'crime statistics',
            'crime rate',
            'crime in',
            'criminal activity',
            'how safe is',
            'is it safe',
            'safe neighborhood',
            'safe area',
            'dangerous',
            'gang',
            'good for kids',
            'good for families',
            'kid friendly',
            'child friendly',
            'families with children',
        ],

        'compatibility_signals' => [
            'compatibility',
            'compatible',
            'compatibility score',
            'match score',
            'score breakdown',
            'how strong is the match',
            'financial compatibility',
            'financial match',
            'physical match',
            'terms match',
            'location match',
            'compatibility warning',
            'compatibility signal',
            'compatibility highlight',
            'how compatible',
        ],

        'property_standout' => [
            'stand out',
            'stands out',
            'what makes this',
            'what makes it',
            'what makes the property',
            'makes this property',
            'unique feature',
            'special about',
            'what is special',
            'best feature',
            'best features',
            'highlight',
            'highlights',
            'selling point',
            'selling points',
            'notable',
            'key feature',
            'top feature',
            'most impressive',
            'most appealing feature',
            'distinguish',
            'differentiator',
            'standout',
            'strength',
            'strengths',
            'benefit',
            'benefits',
            'bidding process',
        ],

        'suited_audience' => [
            'suited for',
            'suitable for',
            'ideal for',
            'ideal buyer',
            'ideal tenant',
            'who would',
            'who would want',
            'who is this for',
            'who is this property for',
            'who is this listing for',
            'target audience',
            'target buyer',
            'target renter',
            'who should',
            'best fit for',
            'good fit for',
            'right buyer',
            'right renter',
            'right tenant',
            'who might',
            'appeal to',
            'type of buyer',
            'type of tenant',
            'what kind of buyer',
            'what kind of tenant',
            'lifestyle',
            'who is this good for',
            'good for who',
        ],

        'buyer_tenant_match' => [
            'good match for',
            'right match',
            'match for a buyer',
            'match for a tenant',
            'match for buyer',
            'match for tenant',
            'fit for the buyer',
            'fit for the tenant',
            'fit for buyer',
            'fit for tenant',
            'buyer fit',
            'tenant fit',
            'buyer match',
            'tenant match',
            'how well does',
            'how well do',
            'would a buyer',
            'would a tenant',
            'aligned with',
            'align with',
            'meet the buyer',
            'meet the tenant',
            'rent budget',
            'rental budget',
            'within budget',
            'lease length',
            'lease term',
            'move-in date',
            'move in date',
        ],

        'missing_data' => [
            'what is missing',
            "what's missing",
            'missing data',
            'missing information',
            'missing from this listing',
            'missing field',
            'missing fields',
            'incomplete',
            'not filled',
            'not filled in',
            'not provided',
            'what data is missing',
            'no information',
            'lacking',
            'absent',
            'gaps in',
            'gap in',
            'what needs to be added',
            'what needs to be filled',
        ],

        'marketing_angles' => [
            'how to market',
            'marketing angle',
            'marketing strategy',
            'marketing approach',
            'best way to market',
            'how should i market',
            'how would you market',
            'how should this be marketed',
            'advertise this',
            'promote this listing',
            'listing pitch',
            'best pitch',
            'positioning for this',
            'listing description',
            'write a description',
            'draft a description',
            'property description',
            'tagline',
            'ad copy',
            'marketing idea',
            'marketing ideas',
        ],

        'educational' => [
            'what is a ',
            'what is an ',
            'what is escrow',
            'what is cap rate',
            'what is earnest',
            'what is closing',
            'what is a mortgage',
            'what is contingency',
            'how does escrow',
            'how does closing',
            'how does the appraisal',
            'how does a mortgage',
            'how does ',
            'how do ',
            'explain ',
            'define ',
            'definition',
            'overview',
            'introduction to',
            'teach me',
            'help me understand',
            'in general',
            'generally speaking',
            'real estate term',
            'real estate process',
            'closing cost',
            'closing costs',
            'escrow',
            'contingency',
            'earnest money',
            'mortgage',
            'appraisal',
            'inspection',
            'title insurance',
            'cap rate',
            'cash flow',
        ],
    ];

    /**
     * Classify a plain user question into one of the nine approved question types.
     *
     * Output contract — always returns exactly these three keys:
     *   question_type  string  — one of the nine approved types, or 'unsupported'
     *   confidence     float   — value between 0.0 and 1.0 (rule-based, not probabilistic)
     *   reason         string  — human-readable explanation of why this type was selected
     *
     * @param  string $question  The raw user question string.
     * @return array{question_type: string, confidence: float, reason: string}
     */
    public function classify(string $question): array
    {
        $lower = mb_strtolower(trim($question));

        if ($lower === '') {
            return [
                'question_type' => 'unsupported',
                'confidence'    => 0.0,
                'reason'        => 'Empty question string provided; no classification possible.',
            ];
        }

        foreach (self::KEYWORD_RULES as $type => $keywords) {
            $matched = $this->findFirstMatch($lower, $keywords);

            if ($matched !== null) {
                return [
                    'question_type' => $type,
                    'confidence'    => $this->confidenceFor($type),
                    'reason'        => "Matched keyword rule for '{$type}': \"{$matched}\".",
                ];
            }
        }

        return [
            'question_type' => 'unsupported',
            'confidence'    => 0.0,
            'reason'        => 'No keyword rule matched; question does not map to any approved type.',
        ];
    }

    /**
     * Return the first keyword from the list that appears in the haystack, or null.
     */
    private function findFirstMatch(string $haystack, array $keywords): ?string
    {
        foreach ($keywords as $keyword) {
            if (str_contains($haystack, mb_strtolower($keyword))) {
                return $keyword;
            }
        }

        return null;
    }

    /**
     * Return a deterministic confidence value for each question type.
     *
     * Confidence reflects how reliably the keyword rule identifies the type,
     * not a probabilistic model score. 'prohibited' gets 1.0 because it is a
     * hard governance refusal and must never be downgraded. 'unsupported' always
     * returns 0.0 and is handled directly in classify().
     */
    private function confidenceFor(string $type): float
    {
        return match ($type) {
            'prohibited'            => 1.0,
            'compatibility_signals' => 0.85,
            'property_standout'     => 0.85,
            'suited_audience'       => 0.80,
            'buyer_tenant_match'    => 0.80,
            'missing_data'          => 0.80,
            'marketing_angles'      => 0.75,
            'educational'           => 0.70,
            default                 => 0.0,
        };
    }
}
