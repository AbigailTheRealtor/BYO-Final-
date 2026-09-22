<?php

namespace App\Support\AskAi;

use App\Services\AskAi\AskAiPublicPropertyQuestionService;

/**
 * AskAiPublicQuestionMatcher — the free-text path's deterministic match against a listing's
 * available public questions, using EXACTLY the rules the card's typed box uses in the browser
 * (AskAiPublicPropertyQuestionService::normalizeQuery()), so the two surfaces cannot disagree.
 *
 * Tiers, in order, each exact after normalisation — no stemming, no similarity, no guessing:
 *   1. the displayed question itself;
 *   2. an alias the question ships — its approved aliases, and its label variants
 *      ("<label>", "what is/are [the] <label>"), which the card already de-duplicated so a
 *      variant two questions could claim belongs to neither.
 * A tier that matches more than one question is AMBIGUOUS and refused; a tier that matches
 * none falls to the next. No match at all is 'none' — the caller refuses deterministically.
 *
 * Pure: no container, no I/O.
 */
final class AskAiPublicQuestionMatcher
{
    /**
     * @param  list<array{id: string, question: string, answer: string, aliases?: list<string>}>  $questions
     * @return array{status: 'matched'|'ambiguous'|'none', question?: array}
     */
    public static function match(string $asked, array $questions): array
    {
        $needle = AskAiPublicPropertyQuestionService::normalizeQuery($asked);
        if ($needle === '' || $questions === []) {
            return ['status' => 'none'];
        }

        $tiers = [
            static fn (array $q): bool => AskAiPublicPropertyQuestionService::normalizeQuery((string) $q['question']) === $needle,
            static fn (array $q): bool => in_array($needle, (array) ($q['aliases'] ?? []), true),
        ];

        foreach ($tiers as $tier) {
            $hits = [];
            foreach ($questions as $q) {
                if ($tier($q)) {
                    $hits[(string) $q['id']] = $q;
                }
            }
            if (count($hits) === 1) {
                return ['status' => 'matched', 'question' => array_values($hits)[0]];
            }
            if (count($hits) > 1) {
                return ['status' => 'ambiguous'];
            }
        }

        return ['status' => 'none'];
    }
}
