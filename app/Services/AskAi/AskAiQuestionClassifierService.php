<?php

namespace App\Services\AskAi;

/**
 * AskAiQuestionClassifierService — Deterministic Question Classification Layer
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: Classifies a raw user question into a defined Ask AI question type using
 * deterministic keyword rules only. No inference, no LLM calls, no HTTP, no DB.
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
     * Priority-ordered keyword rules.
     *
     * Rules are evaluated top-to-bottom; the first matching rule wins.
     * 'prohibited' is always checked first as a safety gate.
     *
     * Each entry:
     *   keywords   string[]  — case-insensitive substrings to search for in the question.
     *   confidence float     — returned when any keyword in this group matches.
     *   reason     string    — human-readable explanation of why this type was chosen.
     */
    private const RULES = [
        'prohibited' => [
            'keywords' => [
                'school district', 'school quality', 'best school', 'good school',
                'nearby school', 'school nearby', 'schools near',
                'crime rate', 'crime in', 'criminal activity', 'how safe is',
                'is it safe', 'neighborhood safe', 'safe neighborhood',
                'safe area', 'dangerous', 'safety of the area', 'safety of this',
                'racial', 'race of', 'ethnicity', 'ethnic', 'diverse neighborhood',
                'neighborhood diversity', 'demographics', 'who lives there',
                'type of people', 'kind of people', 'what kind of people',
                'religion', 'church nearby', 'mosque', 'temple', 'synagogue',
                'families with children', 'good for kids', 'kid friendly',
                'child friendly', 'disability', 'handicap', 'national origin', 'gang',
            ],
            'confidence' => 0.95,
            'reason'     => 'Question matches a protected-class or fair-housing-sensitive keyword.',
        ],

        'missing_data' => [
            'keywords' => [
                'what is missing', "what's missing", 'missing data', 'missing information',
                'missing from this listing', 'incomplete', 'not provided',
                'what data', 'no information', 'not filled in',
                'lacking', 'gaps in',
            ],
            'confidence' => 0.90,
            'reason'     => 'Question asks about missing or incomplete listing data.',
        ],

        'compatibility_signals' => [
            'keywords' => [
                'compatibility', 'compatible', 'match score', 'compatibility score',
                'how well does this match', 'how well do i match',
                'compatibility signal', 'how strong is the match',
            ],
            'confidence' => 0.90,
            'reason'     => 'Question asks about compatibility scores or signals between a buyer/tenant and this listing.',
        ],

        'buyer_tenant_match' => [
            'keywords' => [
                'good match for', 'right match', 'match for a buyer', 'match for a tenant',
                'match for buyer', 'match for tenant',
                'ideal buyer', 'ideal tenant', 'fit for buyer', 'fit for tenant',
                'right buyer', 'right tenant', 'buyer fit', 'tenant fit',
                'would a buyer', 'would a tenant',
            ],
            'confidence' => 0.90,
            'reason'     => 'Question asks whether a specific buyer or tenant is a good match for this listing.',
        ],

        'marketing_angles' => [
            'keywords' => [
                'how to market', 'marketing angle', 'marketing strategy',
                'best way to market', 'advertise this', 'promote this listing',
                'listing pitch', 'best pitch', 'positioning for this',
                'how should this be marketed', 'marketing approach',
                'how would you market', 'how should i market',
            ],
            'confidence' => 0.85,
            'reason'     => 'Question asks about marketing strategy or angles for this listing.',
        ],

        'suited_audience' => [
            'keywords' => [
                'suited for', 'ideal for', 'who would', 'who is this for',
                'who should', 'target audience', 'best for whom', 'who might be interested',
                'appeal to', 'who would this appeal', 'type of buyer',
                'type of tenant', 'who is this property for', 'who is this listing for',
                'what kind of buyer', 'what kind of tenant',
            ],
            'confidence' => 0.85,
            'reason'     => 'Question asks which buyer or tenant profile this listing is best suited for.',
        ],

        'property_standout' => [
            'keywords' => [
                'stand out', 'what makes this', 'unique feature', 'best feature',
                'highlight', 'distinguishing', 'selling point', "what's special about",
                'what is special', 'notable', 'key feature', 'top feature',
                'what makes it', 'what makes the property', 'makes this property',
                'most impressive', 'most appealing feature',
            ],
            'confidence' => 0.85,
            'reason'     => 'Question asks about the distinguishing features or highlights of this property.',
        ],

        'educational' => [
            'keywords' => [
                'what is a ', 'what is an ', 'how does ', 'how do ',
                'explain ', 'define ', 'in general', 'typically ',
                'usually ', 'what does ', 'tell me about',
                'what are the steps', 'what is the difference',
                'how does escrow', 'how does closing', 'what is cap rate',
            ],
            'confidence' => 0.75,
            'reason'     => 'Question appears to be a general real estate educational inquiry.',
        ],
    ];

    /**
     * Classify a raw user question into a defined Ask AI question type.
     *
     * Output contract — always returns exactly these three keys:
     *   question_type  string  — one of the defined Ask AI question types
     *   confidence     float   — 0.0–1.0 confidence score for the classification
     *   reason         string  — human-readable explanation of the classification
     *
     * @param  string  $question  The raw question text from the user.
     * @return array
     */
    public function classify(string $question): array
    {
        $normalized = mb_strtolower(trim($question));

        foreach (self::RULES as $questionType => $rule) {
            foreach ($rule['keywords'] as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    return [
                        'question_type' => $questionType,
                        'confidence'    => $rule['confidence'],
                        'reason'        => $rule['reason'],
                    ];
                }
            }
        }

        return [
            'question_type' => 'unsupported',
            'confidence'    => 0.50,
            'reason'        => 'No keyword rule matched; question type is unsupported.',
        ];
    }
}
