<?php

namespace App\Services\AskAi;

/**
 * AskAiComplianceGuardrailService — Ask AI Output Compliance Guardrail (Phase A)
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: Pure, deterministic output-sanitization layer for Ask AI.
 *
 * Implements the approved guardrail requirements from
 * docs/ask-ai-kb-replacement-spec.md:
 *   - C-A  Output sanitization at answer-generation time. Source text (KB free-text,
 *          property descriptions, or model output) may contain steering, demographic,
 *          discriminatory, unsupported, offensive, legal-conclusion, financial-advice,
 *          or negotiation-advice content. This layer sanitizes or declines ONLY the
 *          offending portion while preserving the legitimate remainder.
 *   - C-I  Superlative / comparative restriction (best, safest, perfect, guaranteed,
 *          ideal, highest quality, unbeatable, rare, better than ...) unless the term
 *          is a direct quote of disclosed listing information.
 *   - C-C  No negotiating-position / leverage analysis.
 *   - C-J  Persistent educational disclaimer string for the response payload + UI.
 *
 * The layer operates at SENTENCE granularity: a sentence that matches a hard-prohibited
 * category is dropped; superlatives in otherwise-acceptable sentences are neutralized in
 * place. Text inside double quotes is treated as a direct quote of disclosed information
 * and is exempt from superlative neutralization (C-I carve-out), though hard-prohibited
 * categories (protected class, steering, advice) are removed even when quoted.
 *
 * This service MUST NEVER:
 *   - Call any external LLM, language model, or external HTTP service.
 *   - Execute any database read or write (query, save, update, create, delete).
 *   - Infer, estimate, or invent values for missing data.
 *   - Generate any AI answer text or call OpenAI.
 *   - Create routes, controllers, Blade views, or database migrations.
 *   - Rank, sort, or recommend any listing, offer, buyer, or agent.
 *   - Reference or infer protected class characteristics.
 * ==================================================================================
 */
class AskAiComplianceGuardrailService
{
    /**
     * Persistent educational disclaimer (C-J). Carried on every Ask AI response payload
     * and rendered in the Ask AI UI. Mirrors the static blade disclaimer so the response
     * itself is self-describing for non-web channels (sms, messenger, whatsapp, crm).
     */
    public const EDUCATIONAL_DISCLAIMER =
        'Ask AI provides educational and informational summaries based on listing data and '
        . 'platform content only. It is not legal, financial, tax, lending, appraisal, '
        . 'inspection, or professional advice. Always verify with a licensed professional '
        . 'before making any real estate decision.';

    /**
     * Fallback returned when sanitization removes the entire answer (every sentence was
     * non-compliant). Keeps the response safe and neutral rather than emitting prohibited
     * content.
     */
    public const WITHHELD_FALLBACK =
        'Based on the information provided, a compliant answer to that question is not '
        . 'available. Ask AI can share objective details disclosed in the listing, but it '
        . 'cannot provide advice or characterize people, neighborhoods, or outcomes.';

    /**
     * Hard-prohibited sentence patterns (case-insensitive, no delimiters/flags).
     * A sentence matching ANY of these is removed in full. Grouped by category so the
     * caller can report which categories fired. Patterns are intentionally specific to
     * avoid stripping legitimate factual statements.
     *
     * @var array<string, string[]>
     */
    private const DROP_PATTERNS = [
        // Protected-class references (Fair Housing).
        'protected_class' => [
            '\b(race|racial|color|creed|religio(n|us)|national origin|ethnic(ity|ities)?)\b',
            '\b(familial status|marital status|disab(led|ility|ilities)|handicap(ped)?)\b',
            '\b(christian|catholic|jewish|muslim|hindu|buddhist)\b',
        ],

        // Demographic / "who lives here" profiling and audience-by-people steering.
        // Suitability must be framed around property features/uses, never people (C-D).
        'demographic' => [
            '\bfor\s+(the\s+)?(families|family|kids|children|retirees|seniors|the elderly|singles|couples|young professionals|professionals|students|empty[- ]?nesters|millennials|newlyweds)\b',
            '\bfamily[- ]?friendly\b',
            '\b(who|what kind of people|what type of (people|person))\s+(typically\s+)?(lives?|would live|reside)\b',
            '\b(mostly|mainly|a mix of)\s+(families|retirees|professionals|seniors|students|young)\b',
            '\b(families|retirees|young professionals|professionals|seniors|students)\s+(usually|typically|tend to|love|prefer|live here)\b',
            '\bgreat place to raise (a family|kids|children)\b',
        ],

        // Area-quality / neighborhood steering (not objective POI/commute/district facts).
        'steering' => [
            '\b(safe|safest|unsafe|dangerous|sketchy|bad|good|nice|nicer|nicest|desirable|undesirable|up[- ]and[- ]coming)\s+(neighborhood|area|part of town|side of town|community|location)\b',
            '\b(good|bad|right|wrong)\s+part of (town|the city)\b',
            '\b(low|high)[- ]crime\b',
            '\b(good|great|top|best|excellent|highly[- ]?rated|top[- ]?rated|high[- ]?performing|a[- ]rated)\s+schools?\b',
        ],

        // Transaction action recommendations + negotiation/leverage (C-C).
        'advice' => [
            '\b(you|buyers?|sellers?|tenants?|landlords?)\s+should\s+(offer|pay|bid|reject|accept|approve|deny|counter|negotiate|lower|raise|increase|decrease|wait|walk away|submit)\b',
            '\bi\s+(would|\'d)\s+(recommend|advise|suggest|offer|reject|accept|counter|negotiate)\b',
            '\b(we|i)\s+recommend\s+(offer|paying|pay|rejecting|reject|accepting|accept|approving|approve|denying|deny|countering|negotiating|that you)\b',
            '\b(my|our)\s+(recommendation|advice)\s+(is|would be)\b',
            '\b(a\s+)?fair\s+(offer|price)\s+(would|might|could)\s+be\b',
            '\b(you|the buyer|the seller)\s+(could|might be able to|may be able to)\s+(get|negotiate)\s+(it|this|the price)\b',
            '\b(approve|deny|reject|accept)\s+(this|the)\s+(applicant|tenant|buyer|offer)\b',
            '\b(strong|weak|poor|good)\s+(negotiating|bargaining)\s+(position|leverage|power)\b',
            '\b(has|have|gives?|gaining)\s+(the\s+)?(upper hand|leverage|negotiating advantage)\b',
        ],

        // Legal conclusions stated as fact (vs. restating disclosed zoning/terms).
        'legal_conclusion' => [
            '\b(this|that|the lease|the contract|the clause)\s+(is|would be|may be)\s+(illegal|unenforceable|legally binding|a breach|void|in violation of)\b',
            '\byou\s+are\s+(legally\s+)?(entitled|obligated|required by law)\b',
        ],

        // Financial / investment / lending / tax advice (vs. restating disclosed figures).
        'financial_advice' => [
            '\b(this|that|it)\s+(is|would be|looks like)\s+(a\s+)?(great|strong|excellent|smart|sound|good|wise|solid)\s+(investment|deal|buy|return|opportunity)\b',
            '\b(you|the buyer|the investor)\s+(should|could|will|can)\s+expect\s+(a\s+)?(return|roi|profit|appreciation)\b',
            '\byou\s+(should|could)\s+(finance|refinance|borrow|leverage|deduct|write off)\b',
            '\b(good|strong|attractive|excellent)\s+(cap rate|return on investment|roi)\b',
        ],
    ];

    /**
     * Superlative / comparative tokens neutralized (removed) when they appear OUTSIDE a
     * direct quote (C-I). Stored as a regex alternation fragment. Removal — rather than
     * synonym substitution — avoids introducing any new unsupported claim.
     */
    private const SUPERLATIVE_TOKENS = [
        'best', 'safest', 'perfect(ly)?', 'guaranteed', 'ideal(ly)?', 'finest', 'premier',
        'unbeatable', 'unmatched', 'world[- ]?class', 'top[- ]?of[- ]the[- ]?line',
        'highest[- ]?quality', 'highest quality', 'top[- ]?rated', 'one[- ]of[- ]a[- ]kind',
        'second[- ]to[- ]none', 'rare', 'once[- ]in[- ]a[- ]lifetime',
    ];

    /**
     * Sanitize a generated answer string against the approved guardrail categories.
     *
     * @param  string $answer  The raw, normalised answer text from the model/builder.
     * @return array{
     *     text: string,
     *     modified: bool,
     *     withheld: bool,
     *     removed_sentences: int,
     *     categories: string[]
     * }
     */
    public function sanitizeAnswer(string $answer): array
    {
        $original = $answer;
        $text     = trim($answer);

        if ($text === '') {
            return [
                'text'              => $answer,
                'modified'          => false,
                'withheld'          => false,
                'removed_sentences' => 0,
                'categories'        => [],
            ];
        }

        $sentences = $this->splitSentences($text);
        $kept      = [];
        $removed   = 0;
        $categories = [];

        foreach ($sentences as $sentence) {
            $category = $this->matchDropCategory($sentence);
            if ($category !== null) {
                $removed++;
                $categories[$category] = true;
                continue;
            }

            $neutralized = $this->neutralizeSuperlatives($sentence, $superlativeHit);
            if ($superlativeHit) {
                $categories['superlative'] = true;
            }

            $neutralized = trim($neutralized);
            if ($neutralized !== '') {
                $kept[] = $neutralized;
            }
        }

        $result = trim(implode(' ', $kept));
        $result = $this->tidyWhitespace($result);

        $withheld = false;
        if ($result === '') {
            $result   = self::WITHHELD_FALLBACK;
            $withheld = true;
        }

        return [
            'text'              => $result,
            'modified'          => ($result !== $original),
            'withheld'          => $withheld,
            'removed_sentences' => $removed,
            'categories'        => array_keys($categories),
        ];
    }

    /**
     * Return the persistent educational disclaimer (C-J).
     */
    public function educationalDisclaimer(): string
    {
        return self::EDUCATIONAL_DISCLAIMER;
    }

    /**
     * Append the educational disclaimer to a disclosures array if not already present.
     *
     * @param  array $disclosures
     * @return array
     */
    public function withEducationalDisclaimer(array $disclosures): array
    {
        foreach ($disclosures as $existing) {
            if (is_string($existing) && trim($existing) === self::EDUCATIONAL_DISCLAIMER) {
                return $disclosures;
            }
        }

        $disclosures[] = self::EDUCATIONAL_DISCLAIMER;
        return $disclosures;
    }

    // =========================================================================
    // Internals
    // =========================================================================

    /**
     * Return the first hard-prohibited category a sentence matches, or null.
     */
    private function matchDropCategory(string $sentence): ?string
    {
        foreach (self::DROP_PATTERNS as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match('/' . $pattern . '/i', $sentence) === 1) {
                    return $category;
                }
            }
        }
        return null;
    }

    /**
     * Remove banned superlative tokens that fall outside double-quoted spans.
     * Sets $hit by reference to true when any token was removed.
     */
    private function neutralizeSuperlatives(string $sentence, ?bool &$hit = null): string
    {
        $hit = false;

        // Split on double-quoted spans, keeping the delimiters so quoted (disclosed)
        // text is preserved verbatim (C-I quote carve-out).
        $parts = preg_split('/("[^"]*")/', $sentence, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $sentence;
        }

        $pattern = '/\b(?:' . implode('|', self::SUPERLATIVE_TOKENS) . ')\b/i';

        $out = '';
        foreach ($parts as $part) {
            // Quoted spans (start and end with ") are left untouched.
            if (strlen($part) >= 2 && $part[0] === '"' && substr($part, -1) === '"') {
                $out .= $part;
                continue;
            }

            $replaced = preg_replace($pattern, '', $part);
            if ($replaced !== $part) {
                $hit = true;
            }
            $out .= $replaced;
        }

        return $out;
    }

    /**
     * Split text into sentences while keeping terminal punctuation attached.
     *
     * @return string[]
     */
    private function splitSentences(string $text): array
    {
        $pieces = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($pieces === false) {
            return [$text];
        }
        return $pieces;
    }

    /**
     * Collapse doubled spaces and fix spacing left by removed tokens
     * (e.g. "the  home", "a home" articles, space-before-punctuation).
     */
    private function tidyWhitespace(string $text): string
    {
        $text = preg_replace('/\s{2,}/', ' ', $text);          // collapse runs of spaces
        $text = preg_replace('/\s+([.,!?;:])/', '$1', $text);  // no space before punctuation
        return trim($text);
    }
}
