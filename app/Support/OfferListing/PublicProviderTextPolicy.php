<?php

namespace App\Support\OfferListing;

/**
 * PublicProviderTextPolicy — the Fair Housing gate an owner-authored answer passes
 * before a PUBLIC listing page restates it.
 *
 * WHY A SECOND POLICY AT ALL. Phase 3's LandlordProviderTextPolicy governs three named
 * landlord meta fields, and its scope is deliberately declared rather than inferred: it
 * takes a field key, only landlord provider fields are named, and pointing it at
 * anything else does nothing. That is a safety property worth keeping, so Batch 4 does
 * not widen it to cover 38 knowledge-base keys across two roles. This class is the
 * surface-scoped counterpart.
 *
 * IT SHARES THE VOCABULARY, IT DOES NOT COPY IT. The patterns come from
 * {@see LandlordProviderTextPolicy::categoryDefinitions()} — one definition of what an
 * exclusion looks like, read by both. A second hand-maintained pattern list would
 * eventually refuse a sentence the other publishes, and those rules match an exclusion
 * STRUCTURE rather than a word ("wheelchair accessible" is kept, "no wheelchair users"
 * is refused), which is precise work that must not be duplicated.
 *
 * ROLE-NEUTRAL, AND STRICTER THAN THE LANDLORD SURFACE. A seller writing "no children"
 * is exactly the statement a landlord writing it would be refused for, so nothing here
 * keys on role. Every category applies, INCLUDING the ones the landlord policy scopes
 * as opt-in: opt-in exists there because a pet-policy rule only has meaning on a field
 * that IS a pet policy, whereas here the question is simply "may the public read this",
 * and the conservative answer to an assistance-animal sentence appearing anywhere on a
 * public page is no.
 *
 * AUTHORSHIP STILL MATTERS. This judges PROVIDER text — what a seller or landlord wrote
 * about their own listing. It is never pointed at a buyer's or tenant's words: an
 * owner's "no emotional support animals" is an exclusion, while a tenant's "I have an
 * emotional support animal" is a lawful first-person disclosure that is private anyway.
 * The only caller is the public property-question service, and it admits seller and
 * landlord knowledge-base answers only.
 *
 * NOTHING IS REWRITTEN. decide() returns a verdict, never a cleaned string. A blocked
 * answer means the question is not published at all.
 *
 * CONTAINER-OPTIONAL by inheritance: the underlying policy reads the config file
 * directly when no container is bound. That is the Phase 2 lesson — a bare config()
 * call on this path once emptied an entire Ask AI context.
 */
class PublicProviderTextPolicy
{
    public const ALLOWED = 'allowed';
    public const BLOCKED = 'blocked';

    /**
     * May this owner-authored text be published verbatim on a public page?
     *
     * @return array{verdict:string, allowed:bool, category:?string}
     *         The matched text is deliberately NOT returned: a caller logging or
     *         rendering it would republish the phrase this policy just refused.
     */
    public static function decide(mixed $text): array
    {
        $allowed = ['verdict' => self::ALLOWED, 'allowed' => true, 'category' => null];

        if (!is_string($text)) {
            return $allowed;
        }

        $normalised = self::normalise($text);

        if ($normalised === '') {
            return $allowed;
        }

        foreach (LandlordProviderTextPolicy::categoryDefinitions() as $category => $definition) {
            $patterns = is_array($definition) ? ($definition['patterns'] ?? []) : [];

            if (!is_array($patterns)) {
                continue;
            }

            foreach ($patterns as $pattern) {
                if (!is_string($pattern) || $pattern === '') {
                    continue;
                }

                // A malformed pattern must not take down a public listing page:
                // preg_match returns false on error and we move on to the next rule.
                if (@preg_match($pattern, $normalised) === 1) {
                    return [
                        'verdict'  => self::BLOCKED,
                        'allowed'  => false,
                        'category' => (string) $category,
                    ];
                }
            }
        }

        return $allowed;
    }

    /** Convenience predicate. */
    public static function isPublishable(mixed $text): bool
    {
        return self::decide($text)['allowed'];
    }

    /**
     * The same normalisation the landlord policy matches against, so a curly apostrophe,
     * a non-breaking space or a wrapped line cannot carry a phrase past a rule on one
     * surface that it would hit on the other.
     */
    private static function normalise(string $text): string
    {
        $text = str_replace(
            ["\xE2\x80\x99", "\xE2\x80\x98", "\xE2\x80\x93", "\xE2\x80\x94", "\xC2\xA0"],
            ["'", "'", '-', '-', ' '],
            $text
        );

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
