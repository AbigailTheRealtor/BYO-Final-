<?php

namespace App\Support\AskAi;

use App\Services\AskAi\AskAiFieldQuestionRegistryService;
use App\Services\AskAi\AskAiSuggestedQuestionsService;

/**
 * The owner question picker's selection contract: what an owner may SELECT, and how a
 * selection is resolved on the server. It exists so an owner's question reaches the
 * runner as a registry entry, never as display text for the server to re-interpret.
 *
 * WHAT IS OFFERED. The owner's suggestions (AskAiSuggestedQuestionsService, unchanged)
 * narrowed to the entries that carry a canonical field key — a listing.* or
 * faq_answers.* fact the deterministic runner can look up. Keyless suggestions ("What
 * marketing angles could work…", "How does this home compare…") were written for the
 * retired model path: with AskAiOpenAiAdapterService::LLM_ANSWERING_APPROVED false they
 * can only ever be refused, so offering them would be offering a question with no answer.
 *
 * HOW A SUGGESTION IS MATCHED TO ITS ENTRY. By exact equality on (role, primary
 * question): a suggestion's `question` IS its registry entry's `primary_question`, and
 * the suggestion shape deliberately withholds registry internals, so equality is the
 * join. No normalisation, no similarity, no fallback — a suggestion that does not match
 * exactly one keyed entry is not offered.
 *
 * HOW A SELECTION IS RESOLVED. resolve() accepts only a key that is the canonical key of
 * exactly one registry entry for that role, and returns that entry's own question. An
 * unknown, malformed or ambiguous key resolves to null and the caller refuses — there is
 * nothing to re-interpret, so nothing can drift toward a model or a guess.
 */
final class AskAiOwnerQuestionSelection
{
    /**
     * The selectable owner questions for this listing, in the service's order.
     *
     * @return list<array{key: string, question: string}>
     */
    public static function forOwner(string $role, array $chipContext): array
    {
        $byQuestion = [];
        foreach (self::keyedEntries($role) as $entry) {
            $byQuestion[$entry['primary_question']][] = $entry['canonical_key'];
        }

        $out = [];
        foreach (app(AskAiSuggestedQuestionsService::class)->forListing($role, $chipContext, true) as $chip) {
            $question = is_array($chip) ? ($chip['question'] ?? null) : null;
            $keys     = is_string($question) ? ($byQuestion[$question] ?? []) : [];
            if (count($keys) === 1) {
                $out[] = ['key' => $keys[0], 'question' => $question];
            }
        }

        return $out;
    }

    /**
     * The registry entry a selected key names for this role, or null.
     *
     * @return array{key: string, question: string}|null
     */
    public static function resolve(string $role, mixed $key): ?array
    {
        if (! is_string($key) || $key === '') {
            return null;
        }

        $matches = array_values(array_filter(
            self::keyedEntries($role),
            static fn (array $entry): bool => $entry['canonical_key'] === $key
        ));

        return count($matches) === 1
            ? ['key' => $matches[0]['canonical_key'], 'question' => $matches[0]['primary_question']]
            : null;
    }

    /** @return list<array> registry entries for the role that name a canonical fact */
    private static function keyedEntries(string $role): array
    {
        return array_values(array_filter(
            AskAiFieldQuestionRegistryService::suggestedQuestionRegistry(),
            static fn (array $entry): bool => in_array($role, $entry['roles'] ?? [], true)
                && is_string($entry['canonical_key'] ?? null)
                && is_string($entry['primary_question'] ?? null)
        ));
    }
}
