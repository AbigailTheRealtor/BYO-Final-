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
 *   listing_facts → property_standout → suited_audience → buyer_tenant_match
 *   → compatibility_signals → missing_data → marketing_angles → educational
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
 *
 * CONTEXT FIELD NAME CONTRACT
 * ==================================================================================
 * The `required_context_path` values in POOLS must match the field names produced by
 * AskAiContextBuilderService::extractFactualFields() exactly. The authoritative
 * mapping per role is:
 *
 *   seller:   asking_price, bedrooms, bathrooms, address, rental_restrictions,
 *             lease_terms, pets_allowed, ...
 *   buyer:    max_price, financing_type (singular), bedrooms, bathrooms, ...
 *   landlord: rent_amount, bedrooms, pet_policy, available_date, utilities,
 *             smoking_policy, ...
 *   tenant:   max_rent, appliances, pet_information, bedrooms, ...
 *
 * faq_answers values are structured objects: { answer_text, question_label, ... }
 * The filter checks that answer_text is a non-empty string.
 * ==================================================================================
 */
class AskAiSuggestedQuestionsService
{
    /**
     * Canonical map of question_type → category_label and category_icon.
     * Fallback: label "General", icon "fa-comment-dots" for unmapped types.
     */
    private const CATEGORY_META = [
        'listing_facts'         => ['category_label' => 'Listing Facts',  'category_icon' => 'fa-circle-info'],
        'property_standout'     => ['category_label' => 'Property',       'category_icon' => 'fa-house'],
        'suited_audience'       => ['category_label' => 'Audience',        'category_icon' => 'fa-bullseye'],
        'buyer_tenant_match'    => ['category_label' => 'Match',           'category_icon' => 'fa-chart-simple'],
        'compatibility_signals' => ['category_label' => 'Compatibility',   'category_icon' => 'fa-scale-balanced'],
        'missing_data'          => ['category_label' => 'Missing Info',    'category_icon' => 'fa-circle-question'],
        'marketing_angles'      => ['category_label' => 'Marketing',       'category_icon' => 'fa-lightbulb'],
        'educational'           => ['category_label' => 'Education',       'category_icon' => 'fa-book-open'],
    ];

    /**
     * Approved category priority order per spec Section 6.5.
     * listing_facts sits first so data-aware chips surface before generic ones.
     */
    private const CATEGORY_ORDER = [
        'listing_facts',
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
     *   label                  — short display label (shown on chip; truncated in UI if > 80 chars)
     *   question               — full question text (populated into textarea on chip click)
     *   question_type          — approved category from spec Section 3
     *   required_context_path  — (listing_facts only) dot-separated path resolved against the
     *                            $context array. Format: "listing.<field>" or "faq_answers.<key>".
     *                            For "listing.*" paths, the context field name must match the key
     *                            produced by AskAiContextBuilderService::extractFactualFields().
     *                            For "faq_answers.*" paths, the value is expected to be a
     *                            structured FAQ object with a non-empty "answer_text" key.
     */
    private const POOLS = [

        'seller' => [
            // --- listing_facts chips (context-aware) ---
            [
                'label'                 => "What's the address?",
                'question'              => "What's the address of this property?",
                'question_type'         => 'listing_facts',
                'required_context_path' => 'listing.address',
            ],
            [
                'label'                 => 'What is the asking price?',
                'question'              => 'What is the asking price for this property?',
                'question_type'         => 'listing_facts',
                'required_context_path' => 'listing.asking_price',
            ],
            [
                'label'                 => 'How many bedrooms?',
                'question'              => 'How many bedrooms does this property have?',
                'question_type'         => 'listing_facts',
                'required_context_path' => 'listing.bedrooms',
            ],
            [
                'label'                 => 'Are there rental restrictions?',
                'question'              => 'Are there any rental restrictions on this property?',
                'question_type'         => 'listing_facts',
                'required_context_path' => 'listing.rental_restrictions',
            ],
            [
                'label'                 => 'What are the lease terms?',
                'question'              => 'What lease terms has the seller specified for this property?',
                'question_type'         => 'listing_facts',
                'required_context_path' => 'listing.lease_terms',
            ],
            [
                'label'                 => 'How old is the roof?',
                'question'              => 'How old is the roof and what condition is it in?',
                'question_type'         => 'listing_facts',
                'required_context_path' => 'faq_answers.roof_age_and_condition',
            ],
            [
                'label'                 => 'What are the average utility costs?',
                'question'              => 'What are the average monthly utility costs for this property?',
                'question_type'         => 'listing_facts',
                'required_context_path' => 'faq_answers.average_utility_costs',
            ],
            [
                'label'                 => "What's the heating/cooling system?",
                'question'              => "What type of heating and cooling system does this property have?",
                'question_type'         => 'listing_facts',
                'required_context_path' => 'faq_answers.heating_cooling_system',
            ],
            // --- static chips ---
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
            // --- listing_facts chips (context-aware) ---
            // Field names match AskAiContextBuilderService::extractFactualFields() for buyer:
            //   max_price       → buyer_criteria_auctions.max_price (native column)
            //   financing_type  → resolved from financing_id FK (singular, not plural)
            //   bedrooms        → buyer_criteria_auctions.bedrooms (native column)
            [
                'label'                 => "What's the max budget?",
                'question'              => "What is the maximum budget stated in this buyer listing?",
                'question_type'         => 'listing_facts',
                'required_context_path' => 'listing.max_price',
            ],
            [
                'label'                 => 'What financing is accepted?',
                'question'              => 'What type of financing has this buyer indicated they will use?',
                'question_type'         => 'listing_facts',
                'required_context_path' => 'listing.financing_type',
            ],
            [
                'label'                 => 'How many bedrooms?',
                'question'              => 'How many bedrooms is this buyer looking for?',
                'question_type'         => 'listing_facts',
                'required_context_path' => 'listing.bedrooms',
            ],
            // --- static chips ---
            [
                'label'         => "What are the strongest criteria I've stated in this listing?",
                'question'      => "What are the strongest criteria I've stated in this listing?",
                'question_type' => 'buyer_tenant_match',
            ],
            [
                'label'         => 'How complete is my buyer criteria listing?',
                'question'      => 'How complete is my buyer criteria listing?',
                'question_type' => 'missing_data',
            ],
            [
                'label'         => "What should I add to help agents better understand what I'm looking for?",
                'question'      => "What should I add to help agents better understand what I'm looking for?",
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
            // --- listing_facts chips (context-aware) ---
            // Field names match AskAiContextBuilderService::extractFactualFields() for landlord:
            //   rent_amount     → info('maximum_budget') aliased as rent_amount in context
            //   bedrooms        → info('bedrooms')
            //   pet_policy      → info('pet_policy')
            //   available_date  → info('available_date')
            //   utilities       → info('utilities')
            //   smoking_policy  → info('smoking_policy') — NOT smoking_allowed
            [
                'label'                 => 'What is the asking rent?',
                'question'              => 'What is the asking rent for this property?',
                'question_type'         => 'listing_facts',
                'required_context_path' => 'listing.rent_amount',
            ],
            [
                'label'                 => 'How many bedrooms?',
                'question'              => 'How many bedrooms does this rental property have?',
                'question_type'         => 'listing_facts',
                'required_context_path' => 'listing.bedrooms',
            ],
            [
                'label'                 => "What's the pet policy?",
                'question'              => "What is the pet policy for this rental property?",
                'question_type'         => 'listing_facts',
                'required_context_path' => 'listing.pet_policy',
            ],
            [
                'label'                 => 'When is it available?',
                'question'              => 'When is this rental property available?',
                'question_type'         => 'listing_facts',
                'required_context_path' => 'listing.available_date',
            ],
            [
                'label'                 => 'What utilities are included?',
                'question'              => 'What utilities are included in the rent for this property?',
                'question_type'         => 'listing_facts',
                'required_context_path' => 'listing.utilities',
            ],
            [
                'label'                 => 'Is smoking allowed?',
                'question'              => 'What is the smoking policy for this rental property?',
                'question_type'         => 'listing_facts',
                'required_context_path' => 'listing.smoking_policy',
            ],
            [
                'label'                 => 'Is there in-unit laundry?',
                'question'              => 'Is there in-unit laundry at this rental property?',
                'question_type'         => 'listing_facts',
                'required_context_path' => 'faq_answers.laundry_situation',
            ],
            [
                'label'                 => "What's the heating/cooling system?",
                'question'              => "What type of heating and cooling system does this rental have?",
                'question_type'         => 'listing_facts',
                'required_context_path' => 'faq_answers.heating_cooling_system',
            ],
            // --- static chips ---
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
            // --- listing_facts chips (context-aware) ---
            // Field names match AskAiContextBuilderService::extractFactualFields() for tenant:
            //   max_rent        → info('maximum_budget') aliased as max_rent in context
            //   appliances      → decodeJsonField(info('appliances')) aliased as appliances
            //   pet_information → info('pet_information') — NOT pets_allowed
            [
                'label'                 => "What's the max rent?",
                'question'              => "What is the maximum rent this tenant is willing to pay?",
                'question_type'         => 'listing_facts',
                'required_context_path' => 'listing.max_rent',
            ],
            [
                'label'                 => 'What appliances are required?',
                'question'              => 'What appliances has this tenant listed as required?',
                'question_type'         => 'listing_facts',
                'required_context_path' => 'listing.appliances',
            ],
            [
                'label'                 => 'Does the tenant have pets?',
                'question'              => 'Does this tenant have pets that need to be accommodated?',
                'question_type'         => 'listing_facts',
                'required_context_path' => 'listing.pet_information',
            ],
            [
                'label'                 => 'Is there in-unit laundry?',
                'question'              => 'Has this tenant specified a need for in-unit laundry?',
                'question_type'         => 'listing_facts',
                'required_context_path' => 'faq_answers.laundry_situation',
            ],
            // --- static chips ---
            [
                'label'         => "What are the strongest lease requirements I've stated in this listing?",
                'question'      => "What are the strongest lease requirements I've stated in this listing?",
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
     * Keys that are internal to the pool definition and must not be surfaced to callers.
     */
    private const INTERNAL_KEYS = ['required_context_path'];

    /**
     * Return up to 5 approved suggested questions for the given listing type.
     *
     * When $context is empty (the default), the full static pool is used — no chips are
     * suppressed and behaviour is identical to the pre-Phase-2 static implementation.
     *
     * When $context is non-empty, `listing_facts` chips are filtered: a chip is included
     * only when its `required_context_path` resolves to a present, non-null value in
     * $context['listing'] (for "listing.*" paths) or the FAQ object at
     * $context['faq_answers'][$key] contains a non-empty "answer_text" string
     * (for "faq_answers.*" paths). All other question types are never filtered.
     *
     * Questions are ordered by category priority per spec Section 6.5, then capped at 5.
     * Unrecognised listing types return an empty array.
     *
     * @param  string  $listingType  One of: seller, buyer, landlord, tenant
     * @param  array   $context      Optional context array produced by AskAiContextBuilderService;
     *                               keys: 'listing' (array of field => value pairs),
     *                               'faq_answers' (array of key => {answer_text, ...} objects)
     * @return array<int, array{label: string, question: string, question_type: string, category_label: string, category_icon: string}>
     */
    public function forListing(string $listingType, array $context = []): array
    {
        $pool = self::POOLS[$listingType] ?? null;

        if ($pool === null) {
            return [];
        }

        if (!empty($context)) {
            $pool = $this->filterByContext($pool, $context);
        }

        $ordered = $this->sortByCategory($pool);

        $sliced = array_slice($ordered, 0, self::MAX_SUGGESTIONS);

        return array_map(function (array $item): array {
            $meta = self::CATEGORY_META[$item['question_type']] ?? [
                'category_label' => 'General',
                'category_icon'  => 'fa-comment-dots',
            ];
            $merged = array_merge($item, $meta);
            foreach (self::INTERNAL_KEYS as $key) {
                unset($merged[$key]);
            }
            return $merged;
        }, $sliced);
    }

    /**
     * Filter the pool by context: suppress `listing_facts` chips whose
     * required_context_path does not resolve to a usable value.
     *
     * For "listing.*" paths: the context field must be present and non-null.
     * For "faq_answers.*" paths: the FAQ object must be present and have a
     *   non-empty "answer_text" string (FAQ values in context are structured
     *   objects, not plain strings).
     *
     * Non-listing_facts chips are always kept regardless of context content.
     *
     * @param  array  $pool
     * @param  array  $context
     * @return array
     */
    private function filterByContext(array $pool, array $context): array
    {
        $listing    = $context['listing']    ?? [];
        $faqAnswers = $context['faq_answers'] ?? [];

        $filtered = array_filter($pool, function (array $chip) use ($listing, $faqAnswers): bool {
            if ($chip['question_type'] !== 'listing_facts') {
                return true;
            }

            $path = $chip['required_context_path'] ?? null;

            if ($path === null) {
                return false;
            }

            if (str_starts_with($path, 'listing.')) {
                $field = substr($path, strlen('listing.'));
                return array_key_exists($field, $listing) && $listing[$field] !== null;
            }

            if (str_starts_with($path, 'faq_answers.')) {
                $key   = substr($path, strlen('faq_answers.'));
                $entry = $faqAnswers[$key] ?? null;
                if (!is_array($entry)) {
                    return false;
                }
                $answerText = $entry['answer_text'] ?? null;
                return $answerText !== null && $answerText !== '';
            }

            return false;
        });

        return array_values($filtered);
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
