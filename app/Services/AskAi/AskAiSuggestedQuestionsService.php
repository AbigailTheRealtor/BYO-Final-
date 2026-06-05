<?php

namespace App\Services\AskAi;

/**
 * AskAiSuggestedQuestionsService — Static Suggested Questions Engine (Phase 4)
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: Returns a curated, compliance-reviewed list of up to 5 suggested questions
 * for the Ask AI panel on listing view pages, scoped by listing type.
 *
 * All returned questions are drawn from the approved static fallback pool defined in
 * ASK_AI_SUGGESTED_QUESTIONS_ENGINE_SPEC_V1 Section 7. Questions are ordered per
 * Section 6.5 category priority:
 *   property_standout → suited_audience → buyer_tenant_match → compatibility_signals
 *   → missing_data → marketing_angles → educational
 *
 * This service MUST NEVER:
 *   - Call any external LLM, language model, or external HTTP service.
 *   - Execute any database read or write (no DB calls whatsoever).
 *   - Infer, estimate, or invent values for missing data.
 *   - Generate AI answer text or call OpenAI.
 *   - Reference or infer protected class characteristics.
 *   - Surface questions in the prohibited categories defined in Section 4 of the spec
 *     (legal, brokerage/negotiation, lending, tax, investment, market prediction,
 *     fair housing/demographic, protected class suitability).
 * ==================================================================================
 */
class AskAiSuggestedQuestionsService
{
    /**
     * Canonical map of question_type → category_label and category_icon.
     * Fallback: label "General", icon "fa-comment-dots" for unmapped types.
     */
    private const CATEGORY_META = [
        'property_standout'     => ['category_label' => 'Property',      'category_icon' => 'fa-house'],
        'suited_audience'       => ['category_label' => 'Audience',       'category_icon' => 'fa-bullseye'],
        'buyer_tenant_match'    => ['category_label' => 'Match',          'category_icon' => 'fa-chart-simple'],
        'compatibility_signals' => ['category_label' => 'Compatibility',  'category_icon' => 'fa-scale-balanced'],
        'missing_data'          => ['category_label' => 'Missing Info',   'category_icon' => 'fa-circle-question'],
        'marketing_angles'      => ['category_label' => 'Marketing',      'category_icon' => 'fa-lightbulb'],
        'educational'           => ['category_label' => 'Education',      'category_icon' => 'fa-book-open'],
    ];

    /**
     * Approved category priority order per spec Section 6.5.
     */
    private const CATEGORY_ORDER = [
        'property_standout',
        'suited_audience',
        'buyer_tenant_match',
        'compatibility_signals',
        'missing_data',
        'marketing_angles',
        'educational',
    ];

    /**
     * Maximum suggestions surfaced at one time (spec Section 6.5).
     */
    private const MAX_SUGGESTIONS = 5;

    /**
     * Approved static fallback question pools per listing type.
     * Source: ASK_AI_SUGGESTED_QUESTIONS_ENGINE_SPEC_V1 Section 7.
     *
     * Each entry contains:
     *   label         — short display label (shown on chip; truncated in UI if > 80 chars)
     *   question      — full question text (populated into textarea on chip click)
     *   question_type — approved category from spec Section 3
     */
    private const POOLS = [

        'seller' => [
            [
                'label'         => 'What are the key features of this property?',
                'question'      => 'What are the key features of this property?',
                'question_type' => 'property_standout',
            ],
            [
                'label'         => 'How does this home compare to similar listings on the platform?',
                'question'      => 'How does this home compare to similar listings on the platform?',
                'question_type' => 'property_standout',
            ],
            [
                'label'         => 'What sale terms has the seller specified?',
                'question'      => 'What sale terms has the seller specified?',
                'question_type' => 'property_standout',
            ],
            [
                'label'         => 'What type of buyer might find this property a practical fit?',
                'question'      => 'What type of buyer might find this property a practical fit?',
                'question_type' => 'suited_audience',
            ],
            [
                'label'         => 'What information is missing from this listing?',
                'question'      => 'What information is missing from this listing?',
                'question_type' => 'missing_data',
            ],
            [
                'label'         => 'What marketing angles could work for this property?',
                'question'      => 'What marketing angles could work for this property?',
                'question_type' => 'marketing_angles',
            ],
            [
                'label'         => 'How does the seller agent auction process work?',
                'question'      => 'How does the seller agent auction process work?',
                'question_type' => 'educational',
            ],
        ],

        'buyer' => [
            [
                'label'         => 'What are the strongest criteria I\'ve stated in this listing?',
                'question'      => 'What are the strongest criteria I\'ve stated in this listing?',
                'question_type' => 'buyer_tenant_match',
            ],
            [
                'label'         => 'How complete is my buyer criteria listing?',
                'question'      => 'How complete is my buyer criteria listing?',
                'question_type' => 'missing_data',
            ],
            [
                'label'         => 'What should I add to help agents better understand what I\'m looking for?',
                'question'      => 'What should I add to help agents better understand what I\'m looking for?',
                'question_type' => 'missing_data',
            ],
            [
                'label'         => 'How does the buyer agent auction process work?',
                'question'      => 'How does the buyer agent auction process work?',
                'question_type' => 'educational',
            ],
            [
                'label'         => 'What factors do agents consider most important in a buyer criteria listing?',
                'question'      => 'What factors do agents consider most important in a buyer criteria listing?',
                'question_type' => 'educational',
            ],
        ],

        'landlord' => [
            [
                'label'         => 'What are the key features of this rental property?',
                'question'      => 'What are the key features of this rental property?',
                'question_type' => 'property_standout',
            ],
            [
                'label'         => 'How does this rental compare to similar listings in the area?',
                'question'      => 'How does this rental compare to similar listings in the area?',
                'question_type' => 'property_standout',
            ],
            [
                'label'         => 'What lease terms has the landlord specified?',
                'question'      => 'What lease terms has the landlord specified?',
                'question_type' => 'property_standout',
            ],
            [
                'label'         => 'What type of renter might find this rental a practical fit?',
                'question'      => 'What type of renter might find this rental a practical fit?',
                'question_type' => 'suited_audience',
            ],
            [
                'label'         => 'What information is missing from this listing?',
                'question'      => 'What information is missing from this listing?',
                'question_type' => 'missing_data',
            ],
            [
                'label'         => 'What marketing angles could work for this rental?',
                'question'      => 'What marketing angles could work for this rental?',
                'question_type' => 'marketing_angles',
            ],
            [
                'label'         => 'How does the landlord auction process work on this platform?',
                'question'      => 'How does the landlord auction process work on this platform?',
                'question_type' => 'educational',
            ],
        ],

        'tenant' => [
            [
                'label'         => 'What are the strongest lease requirements I\'ve stated in this listing?',
                'question'      => 'What are the strongest lease requirements I\'ve stated in this listing?',
                'question_type' => 'buyer_tenant_match',
            ],
            [
                'label'         => 'How complete is my tenant criteria listing?',
                'question'      => 'How complete is my tenant criteria listing?',
                'question_type' => 'missing_data',
            ],
            [
                'label'         => 'What should I add to help landlords better understand what I need?',
                'question'      => 'What should I add to help landlords better understand what I need?',
                'question_type' => 'missing_data',
            ],
            [
                'label'         => 'How does the tenant agent auction process work?',
                'question'      => 'How does the tenant agent auction process work?',
                'question_type' => 'educational',
            ],
            [
                'label'         => 'What compatibility factors matter most when landlords evaluate tenant criteria?',
                'question'      => 'What compatibility factors matter most when landlords evaluate tenant criteria?',
                'question_type' => 'educational',
            ],
        ],
    ];

    /**
     * Return up to 5 approved suggested questions for the given listing type.
     *
     * Questions are drawn from the static fallback pool, ordered by category priority
     * per spec Section 6.5. Unrecognised listing types return an empty array.
     *
     * @param  string  $listingType  One of: seller, buyer, landlord, tenant
     * @param  array   $context      Reserved for Phase 4+ dynamic substitution (unused in static phase)
     * @return array<int, array{label: string, question: string, question_type: string}>
     */
    public function forListing(string $listingType, array $context = []): array
    {
        $pool = self::POOLS[$listingType] ?? null;

        if ($pool === null) {
            return [];
        }

        $ordered = $this->sortByCategory($pool);

        $sliced = array_slice($ordered, 0, self::MAX_SUGGESTIONS);

        return array_map(function (array $item): array {
            $meta = self::CATEGORY_META[$item['question_type']] ?? [
                'category_label' => 'General',
                'category_icon'  => 'fa-comment-dots',
            ];
            return array_merge($item, $meta);
        }, $sliced);
    }

    /**
     * Sort a question pool by the canonical category priority order.
     *
     * @param  array  $pool
     * @return array
     */
    private function sortByCategory(array $pool): array
    {
        $priorityMap = array_flip(self::CATEGORY_ORDER);

        usort($pool, function (array $a, array $b) use ($priorityMap): int {
            $pa = $priorityMap[$a['question_type']] ?? PHP_INT_MAX;
            $pb = $priorityMap[$b['question_type']] ?? PHP_INT_MAX;
            return $pa <=> $pb;
        });

        return $pool;
    }
}
