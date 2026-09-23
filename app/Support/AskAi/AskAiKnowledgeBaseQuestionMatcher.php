<?php

namespace App\Support\AskAi;

use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\AskAi\AskAiFaqConfigService;

/**
 * AskAiKnowledgeBaseQuestionMatcher — the deterministic floor for Knowledge Base questions on
 * the free-text Ask AI path: a question that IS a Knowledge Base question's own label always
 * reaches that key.
 *
 * WHY THIS EXISTS
 * ---------------
 * With the language-model path disabled, a Knowledge Base answer is reachable only if the
 * question routes deterministically to its key. Measured before this class existed, asking
 * the owner's OWN question word for word returned the owner's stored answer for 16 of 148
 * Seller/Landlord/Tenant keys. The rest were "question type not supported", "try again
 * shortly", a false "has not been provided" for an answer that was stored, or — for
 * neighborhood_character — a DIFFERENT key's answer, because the keyword map is first-match
 * substring routing and an earlier phrase stole the question.
 *
 * EXACT, NOT FUZZY
 * ----------------
 * A label matches after normalisation only (case, punctuation and whitespace). No token
 * overlap, no similarity score: a near-miss routed to the wrong key is a wrong answer
 * delivered with confidence, which is worse than the unsupported response it replaces.
 * Paraphrases keep going through the existing keyword maps.
 *
 * GATED BY PROPERTY TYPE, AMBIGUITY FAILS CLOSED
 * ----------------------------------------------
 * Labels repeat across property types — "What location features are nearby?" is three keys
 * (income, commercial, land). The candidate set is the keys configured for THIS listing's
 * property type (AskAiFaqConfigService::gatedQuestions()); if a label still names more than
 * one key the match is refused rather than guessed.
 *
 * It routes; it never answers and never reads the answer. What the viewer may see is still
 * decided downstream — the context builder's config-intersection admission, viewer-scope
 * redaction of faq_answers for non-owners, and the missing-data guard.
 */
final class AskAiKnowledgeBaseQuestionMatcher
{
    /**
     * The FAQ key a question names on this listing, or null.
     */
    public static function forListing(string $listingType, int $listingId, string $question): ?string
    {
        $role = AskAiContextBuilderService::canonicalListingType($listingType);
        if ($role === null) {
            return null;
        }

        try {
            $propertyType = app(AskAiContextBuilderService::class)->listingPropertyType($listingType, $listingId);
        } catch (\Throwable) {
            $propertyType = null;
        }

        return self::match($role, $question, $propertyType);
    }

    /**
     * Pure form: the FAQ key whose configured label equals the question, for this role and
     * property type. An unresolved property type offers the universal questions only — the
     * same set AskAiFaqConfigService gives an untyped listing.
     */
    public static function match(string $role, string $question, ?string $propertyType): ?string
    {
        $needle = self::normalize($question);
        if ($needle === '') {
            return null;
        }

        $candidates = [];
        foreach (AskAiFaqConfigService::gatedQuestions($role, (string) $propertyType) as $entries) {
            foreach ((array) $entries as $key => $entry) {
                $label = is_array($entry) ? ($entry['label'] ?? null) : null;
                if (is_string($label) && self::normalize($label) === $needle) {
                    $candidates[(string) $key] = true;
                }
            }
        }

        return count($candidates) === 1 ? (string) array_key_first($candidates) : null;
    }

    public static function normalize(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '';

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }
}
