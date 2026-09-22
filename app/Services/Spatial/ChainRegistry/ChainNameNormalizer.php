<?php

namespace App\Services\Spatial\ChainRegistry;

/**
 * `chain-name-norm-v1` — the one text normalisation the chain registry and the chain matcher
 * share. Aliases are STORED already normalised and a row's `names.primary` / `brand.names.primary`
 * are normalised the same way at match time, so a comparison is always between two outputs of
 * this function. Design: docs/spatial/overture-chain-registry-design.md §4.1.
 *
 *   1. Unicode NFKC, then lower-case.
 *   2. Strip ONE trailing store number of the measured shape `#<digits>` ("PUBLIX #1029"), while
 *      the `#` is still visible.
 *   3. Delete apostrophes (’ ‘ '): "Wendy's" → "wendys", "Trader Joe's" → "trader joes".
 *   4. `&` → " and ".
 *   5. Every other non-letter, non-digit character → a space: "7-Eleven" → "7 eleven".
 *   6. Collapse whitespace and trim. Nothing left → null.
 *
 * Nothing else, deliberately: no stemming, no stop-word removal, no transliteration beyond NFKC,
 * no edit distance. `7eleven` and `7 eleven` stay DIFFERENT strings — which is why the registry
 * lists both as aliases rather than this function guessing they are the same.
 *
 * {@see \App\Services\Spatial\NameNormalizer} is a different rule, for authority linking. The two
 * are separate contracts and must not be merged: changing one would silently re-key the other's
 * matches.
 *
 * NFKC needs PHP's `intl` extension. Without it this throws rather than skipping step 1: a
 * normaliser that quietly behaves differently on another host is a matcher that quietly matches
 * differently there.
 */
final class ChainNameNormalizer
{
    public const VERSION = 'chain-name-norm-v1';

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! class_exists(\Normalizer::class)) {
            throw new \RuntimeException(
                self::VERSION . ' requires the intl extension (Unicode NFKC); refusing to normalise without it.'
            );
        }

        $s = \Normalizer::normalize($value, \Normalizer::FORM_KC);
        if (! is_string($s)) {
            // Invalid UTF-8: there is no defined normal form, so there is no name.
            return null;
        }

        $s = mb_strtolower($s, 'UTF-8');
        $s = (string) preg_replace('/\s*#\s*\d+\s*$/u', '', $s);
        $s = str_replace(["\u{2019}", "\u{2018}", "'"], '', $s);
        $s = str_replace('&', ' and ', $s);
        $s = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);
        $s = trim((string) preg_replace('/\s+/u', ' ', $s));

        return $s === '' ? null : $s;
    }

    /**
     * True when a normalised value is shaped like a Wikidata QID ("q672170"). The v1 place
     * normaliser writes the brand QID into `brand` when `brand.names` is absent, so a QID-shaped
     * brand NAME is not a brand name and must never be compared with an alias.
     */
    public static function isQidShaped(?string $normalized): bool
    {
        return $normalized !== null && preg_match('/^q\d+$/', $normalized) === 1;
    }

    /**
     * A Wikidata identifier in canonical form ("Q672170"), null when absent, or FALSE when a value
     * is present but malformed. The caller must treat FALSE as a refusal, never as "no QID".
     *
     * @return string|null|false
     */
    public static function wikidata(?string $value)
    {
        if ($value === null) {
            return null;
        }

        $v = strtoupper(trim($value));
        if ($v === '') {
            return null;
        }

        return preg_match('/^Q[1-9]\d*$/', $v) === 1 ? $v : false;
    }
}
