<?php

namespace App\Services\SmartTags\Derivation;

use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagSource;
use App\Support\SmartTags\SmartTagState;
use App\Support\SmartTags\SmartTagTaxonomy;
use InvalidArgumentException;

/**
 * Deterministic phrase matching over listing prose → canonical Smart Tags.
 *
 * One parser for both description sources: Bridge `mls_remarks` (test-only; not
 * approved for production) and native `native_listing_description`.
 *
 * CONTROLLED, NOT GENERATIVE. It can only emit keys that already have rules in
 * config/smart_tag_sources.php. No AI, no network, no model: patterns in, keys out.
 *
 * CONSERVATIVE BY CONSTRUCTION
 *   • Never emits "absent". Prose that does not mention a feature is unknown, and
 *     prose that says "no pool" is also recorded as nothing — confirmed absence
 *     belongs to structured data.
 *   • Negation in the preceding window ("no", "without", "not") or following it
 *     ("not included") discards the match.
 *   • Rule qualifiers suppress lookalikes: "community pool" is not a private pool,
 *     "loading dock" is not a boat dock, "spa-like bath" is not a spa.
 *   • Positive condition rules are suppressed after a need ("needs a full
 *     renovation" is not "fully renovated").
 *   • Location-sensitive rules are suppressed near proximity language
 *     ("minutes to the marina") — proximity is Location DNA.
 *   • Context-specific rules: "turnkey" is turnkey_home only in residential.sale
 *     and turnkey_business only in business.sale.
 *   • Cue windows stop at commas, so one list item's "no" does not reach the next.
 */
class ListingDescriptionTagParser
{
    private const WORD = '[\p{L}\p{N}_]';

    public static function compile(string $pattern): string
    {
        return '~(?<!' . self::WORD . ')(?:' . $pattern . ')(?!' . self::WORD . ')~iu';
    }

    /**
     * @return TagEvidence[] keyed by tag
     */
    public function parse(?string $text, SmartTagContext $context, SmartTagSource $source): array
    {
        if (! $source->isDescription()) {
            throw new InvalidArgumentException("{$source->value} is not a description source.");
        }

        $normalized = DescriptionTextNormalizer::normalize($text);
        if ($normalized === '') {
            return [];
        }

        $clauses = preg_split('~[.!?;\n]+|\s+but\s+|\s+however\s+|\s+although\s+~u', $normalized) ?: [$normalized];
        $window = max(1, (int) SmartTagSourceRules::descriptionSetting('window_words', 5));
        $default = (int) SmartTagSourceRules::descriptionSetting('default_confidence', 65);

        $negationBefore = self::termRegex((array) SmartTagSourceRules::descriptionSetting('negation_before', []));
        $negationAfter = self::termRegex((array) SmartTagSourceRules::descriptionSetting('negation_after', []));
        $needs = self::termRegex((array) SmartTagSourceRules::descriptionSetting('needs_cues', []));
        $locationBefore = self::termRegex((array) SmartTagSourceRules::descriptionSetting('location_cues_before', []));
        $locationAfter = self::termRegex((array) SmartTagSourceRules::descriptionSetting('location_cues_after', []));

        $out = [];

        foreach (SmartTagSourceRules::descriptionRules() as $tagKey => $rules) {
            $definition = SmartTagTaxonomy::get((string) $tagKey);
            if ($definition === null || ! $definition->isActive() || ! $definition->appliesTo($context)) {
                continue;
            }

            foreach ((array) $rules as $index => $rule) {
                if (isset($rule['contexts']) && ! in_array($context->value, (array) $rule['contexts'], true)) {
                    continue;
                }

                $suppressBefore = self::termRegex((array) ($rule['suppress_before'] ?? []));
                $suppressAfter = self::termRegex((array) ($rule['suppress_after'] ?? []));

                foreach ($clauses as $clause) {
                    if ($clause === '' || isset($out[$tagKey])) {
                        continue;
                    }

                    foreach ((array) ($rule['patterns'] ?? []) as $pattern) {
                        if (! preg_match_all(self::compile((string) $pattern), $clause, $matches, PREG_OFFSET_CAPTURE)) {
                            continue;
                        }

                        foreach ($matches[0] as [$matched, $offset]) {
                            $before = self::lastWords(self::afterLastComma(substr($clause, 0, $offset)), $window);
                            $after = self::firstWords(self::beforeFirstComma(substr($clause, $offset + strlen($matched))), $window);

                            if (self::hit($negationBefore, $before) || self::hit($negationAfter, $after)
                                || self::endsWithNegatingPrefix(substr($clause, 0, $offset))) {
                                continue;
                            }
                            if (self::hit($suppressBefore, $before) || self::hit($suppressAfter, $after)) {
                                continue;
                            }
                            if (($rule['positive_condition'] ?? false) === true && self::hit($needs, $before)) {
                                continue;
                            }
                            if (($rule['location_sensitive'] ?? false) === true
                                && (self::hit($locationBefore, $before) || self::hit($locationAfter, $after))) {
                                continue;
                            }

                            $out[$tagKey] = new TagEvidence(
                                tagKey: (string) $tagKey,
                                state: SmartTagState::Present,
                                source: $source,
                                context: $context,
                                confidence: (int) ($rule['confidence'] ?? $default),
                                sourceField: $source === SmartTagSource::MlsRemarks ? 'PublicRemarks' : 'listing_description',
                                // The rule that fired — never the listing's own words.
                                ruleId: "description.{$tagKey}.{$index}",
                            );
                            continue 4;
                        }
                    }
                }
            }
        }

        ksort($out);

        return $out;
    }

    /** @param string[] $terms */
    private static function termRegex(array $terms): ?string
    {
        $terms = array_values(array_filter(array_map(static fn ($t) => trim(mb_strtolower((string) $t)), $terms)));
        if ($terms === []) {
            return null;
        }

        $parts = array_map(static fn (string $t) => preg_replace('/\s+/', '\s+', preg_quote($t, '~')), $terms);

        return '~(?<!' . self::WORD . ')(?:' . implode('|', $parts) . ')(?!' . self::WORD . ')~iu';
    }

    private static function hit(?string $regex, string $text): bool
    {
        return $regex !== null && $text !== '' && preg_match($regex, $text) === 1;
    }

    private static function afterLastComma(string $text): string
    {
        $pos = strrpos($text, ',');

        return $pos === false ? $text : substr($text, $pos + 1);
    }

    private static function beforeFirstComma(string $text): string
    {
        $pos = strpos($text, ',');

        return $pos === false ? $text : substr($text, 0, $pos);
    }

    private static function lastWords(string $text, int $count): string
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_slice($words, -$count));
    }

    private static function firstWords(string $text, int $count): string
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_slice($words, 0, $count));
    }

    /** "non-waterfront", "un-updated" style prefixes attached to the match. */
    private static function endsWithNegatingPrefix(string $before): bool
    {
        return preg_match('~(?<!' . self::WORD . ')non[\s-]?$~iu', $before) === 1;
    }
}
