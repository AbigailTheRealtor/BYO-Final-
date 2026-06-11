<?php

namespace App\Services\AskAi;

/**
 * AskAiSuggestedQuestionsService — Registry-Driven Suggested Questions Engine (Phase 4 rev 2)
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: Returns a curated, compliance-reviewed list of up to 5 suggested questions
 * for the Ask AI panel on listing view pages, scoped by listing type.
 *
 * Questions are drawn from AskAiFieldQuestionRegistryService::suggestedQuestionRegistry()
 * and ordered per category priority (spec Section 6.5):
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
 * The `source_path` values in suggestedQuestionRegistry() must match the field names
 * produced by AskAiContextBuilderService::extractFactualFields() exactly.
 * The authoritative mapping per role is:
 *
 *   seller:   asking_price, bedrooms, bathrooms, address, rental_restrictions,
 *             lease_terms, pets_allowed, ...
 *   buyer:    max_price, financing_type (singular, not financing_types), bedrooms, ...
 *   landlord: rent_amount, bedrooms, pet_policy, available_date, utilities,
 *             smoking_policy (NOT smoking_allowed), ...
 *   tenant:   max_rent, appliances (NOT required_appliances),
 *             pet_information (NOT pets_allowed), bedrooms, ...
 *
 * faq_answers values are structured objects: { answer_text, question_label, ... }
 * The filter checks that answer_text is a non-empty string.
 * ==================================================================================
 */
class AskAiSuggestedQuestionsService
{
    /**
     * Canonical map of question_type → category_label and category_icon.
     * Kept for backward-compatible chip label/icon rendering via CATEGORY_META.
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
     * @deprecated The hardcoded POOLS constant is superseded by
     *             AskAiFieldQuestionRegistryService::suggestedQuestionRegistry().
     *             This constant is retained for reference only and MUST NOT be
     *             referenced by forListing() or any other active code path.
     */
    private const POOLS = [];

    /**
     * Return up to 5 approved suggested questions for the given listing type.
     *
     * Questions are drawn from AskAiFieldQuestionRegistryService::suggestedQuestionRegistry()
     * and filtered by:
     *   1. Role  — only entries whose `roles` array includes $listingType.
     *   2. Auth  — entries with `public_allowed = false` are hidden when $isAuthenticated is false.
     *   3. Data  — when $context is non-empty, entries with `requires_data = true` are suppressed
     *              unless their `source_path` resolves to a present, non-null value in $context.
     *
     * When $context is empty (the default), steps 1 and 2 apply but step 3 is skipped so
     * all non-auth-gated chips for the role are returned — behaviour is identical to the
     * pre-Phase-2 static implementation. Passing [] explicitly is identical to omitting
     * $context entirely.
     *
     * Questions are ordered by category priority per spec Section 6.5, then capped at 5.
     * Unrecognised listing types return an empty array.
     *
     * @param  string  $listingType     One of: seller, buyer, landlord, tenant
     * @param  array   $context         Optional context array produced by AskAiContextBuilderService;
     *                                  keys: 'listing' (array of field => value pairs),
     *                                  'faq_answers' (array of key => {answer_text, ...} objects)
     * @param  bool    $isAuthenticated Whether the current viewer is authenticated (logged in).
     *                                  Defaults to true. Set to false to hide owner-only chips.
     * @return array<int, array{label: string, question: string, question_type: string, category: string, category_label: string, category_icon: string}>
     */
    public function forListing(string $listingType, array $context = [], bool $isAuthenticated = true): array
    {
        $registry = AskAiFieldQuestionRegistryService::suggestedQuestionRegistry();

        // Pre-compute approved canonical key sets for the phantom-key guard (step 1b).
        //
        // Any entry whose canonical_key is no longer present in its primary registry
        // is silently excluded — phantom/removed keys must never produce chips.
        //
        //   listing.* keys  → validated against listingFieldRegistry() (47 entries).
        //                     Every listing.* key used in suggestedQuestionRegistry()
        //                     must also appear in listingFieldRegistry(); if a key is
        //                     ever removed from that registry it is automatically
        //                     suppressed here without a code change.
        //   faq_answers.* keys → validated against registry() (168 FAQ entries).
        //   Any other prefix   → treated as phantom and excluded.
        $validListingKeys = array_flip(array_keys(AskAiFieldQuestionRegistryService::listingFieldRegistry()));
        $validFaqKeys     = array_flip(array_keys(AskAiFieldQuestionRegistryService::registry()));

        // 1. Filter by role, then by canonical key validity
        $pool = array_values(array_filter(
            $registry,
            function (array $entry) use ($listingType, $validListingKeys, $validFaqKeys): bool {
                if (!in_array($listingType, $entry['roles'], true)) {
                    return false;
                }
                $ck = $entry['canonical_key'] ?? null;
                if ($ck === null) {
                    return true; // static chip — no canonical key to validate
                }
                if (str_starts_with($ck, 'listing.')) {
                    return isset($validListingKeys[$ck]); // phantom listing key guard
                }
                if (str_starts_with($ck, 'faq_answers.')) {
                    return isset($validFaqKeys[$ck]); // phantom FAQ key guard
                }
                return false; // unknown prefix — treat as phantom
            }
        ));

        if (empty($pool)) {
            return [];
        }

        // 2. Apply public_allowed guard for unauthenticated viewers
        if (!$isAuthenticated) {
            $pool = array_values(array_filter(
                $pool,
                fn(array $entry): bool => (bool) $entry['public_allowed']
            ));
        }

        // 3. Apply requires_data guard when context is provided
        if (!empty($context)) {
            $pool = $this->filterByContext($pool, $context);
        }

        // 4. Sort by canonical category priority
        $ordered = $this->sortByCategory($pool);

        // 5. Cap at MAX_SUGGESTIONS
        $sliced = array_slice($ordered, 0, self::MAX_SUGGESTIONS);

        // 6. Build public chip shape (explicit keys only — no registry internals surfaced)
        return array_map(function (array $entry): array {
            $meta = self::CATEGORY_META[$entry['question_type']] ?? [
                'category_label' => 'General',
                'category_icon'  => 'fa-comment-dots',
            ];
            return [
                'label'          => $entry['label'],
                'question'       => $entry['primary_question'],
                'question_type'  => $entry['question_type'],
                'category'       => $entry['category'],
                'category_label' => $meta['category_label'],
                'category_icon'  => $meta['category_icon'],
            ];
        }, $sliced);
    }

    /**
     * Filter the pool by context: suppress `requires_data = true` chips whose
     * `source_path` does not resolve to a usable, non-empty value in $context.
     *
     * For "listing.*" paths: the context field must be present and carry a value
     *   that is not null, not an empty string, not a whitespace-only string, and
     *   not an empty array. Falsy-but-present values (0, false) are accepted because
     *   they represent intentional data (e.g. 0 bedrooms, false for a boolean flag).
     *
     * For "faq_answers.*" paths: the FAQ object must be present and have a
     *   non-empty "answer_text" string (FAQ values in context are structured
     *   objects, not plain strings).
     *
     * Chips with `requires_data = false` are always kept regardless of context.
     *
     * @param  array  $pool
     * @param  array  $context
     * @return array
     */
    private function filterByContext(array $pool, array $context): array
    {
        $listing    = $context['listing']    ?? [];
        $faqAnswers = $context['faq_answers'] ?? [];

        $filtered = array_filter($pool, function (array $entry) use ($listing, $faqAnswers): bool {
            if (!($entry['requires_data'] ?? false)) {
                return true;
            }

            $path = $entry['source_path'] ?? null;

            if ($path === null) {
                return false;
            }

            if (str_starts_with($path, 'listing.')) {
                $field = substr($path, strlen('listing.'));
                if (!array_key_exists($field, $listing)) {
                    return false;
                }
                return !$this->isEffectivelyEmpty($listing[$field]);
            }

            if (str_starts_with($path, 'faq_answers.')) {
                $key        = substr($path, strlen('faq_answers.'));
                $faqEntry   = $faqAnswers[$key] ?? null;
                if (!is_array($faqEntry)) {
                    return false;
                }
                $answerText = $faqEntry['answer_text'] ?? null;
                return $answerText !== null && $answerText !== '';
            }

            return false;
        });

        return array_values($filtered);
    }

    /**
     * Return true when a listing context field value should be treated as missing
     * data (i.e. the chip should be suppressed despite the key being present).
     *
     * Suppressed cases: null, empty string, whitespace-only string, empty array.
     * Accepted falsy values: 0, 0.0, false, '0' (intentional data).
     *
     * @param  mixed  $value
     * @return bool
     */
    private function isEffectivelyEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value) && trim($value) === '') {
            return true;
        }
        if (is_array($value) && empty($value)) {
            return true;
        }
        return false;
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
