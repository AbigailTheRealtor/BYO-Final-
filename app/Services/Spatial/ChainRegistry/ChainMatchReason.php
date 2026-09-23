<?php

namespace App\Services\Spatial\ChainRegistry;

/**
 * Every reason the matcher can give for NOT producing a membership, plus the diagnostic codes
 * it records without changing a membership. A closed list, so the PR 3 extraction census can
 * tally by code and a new code is a reviewed change. Design §12.
 */
final class ChainMatchReason
{
    // ── Row-level refusals: the whole row produces nothing ──────────────────────────────────
    /** R0: `category_key` is not one of the 16 canonical v2 keys. */
    public const CATEGORY_NOT_IMPORTED = 'category_not_imported';
    /** R0: operating status other than `open` / NULL. */
    public const STATUS_EXCLUDED = 'status_excluded';
    /** R0: a brand Wikidata value is present but is not a QID. */
    public const MALFORMED_WIKIDATA = 'malformed_wikidata';
    /** R0: the brand QID field and a QID-shaped brand name name different entities. */
    public const CONFLICTING_WIKIDATA = 'conflicting_wikidata';
    /** R1: the row carries a global service-exclusion QID (Western Union, an ATM operator, …). */
    public const EXCLUSION_WIKIDATA = 'exclusion_wikidata';
    /** R2: name or brand matches a global exclusion pattern. */
    public const EXCLUSION_PATTERN = 'exclusion_pattern';
    /** R2: name or brand asserts closure. */
    public const CLOSED_NAME = 'closed_name';
    /** R3: no chain has any identity evidence on this row. */
    public const NO_CHAIN = 'no_chain';
    /** Every candidate was dropped; see the per-candidate reasons. */
    public const ALL_CANDIDATES_REJECTED = 'all_candidates_rejected';
    /** R8: more than one candidate survived and they are not a declared, evidenced co-brand. */
    public const AMBIGUOUS = 'ambiguous';

    // ── Per-candidate drops: one chain loses its candidacy, others may survive ───────────────
    /** R4: chain-local exclusion QID or name pattern. */
    public const CHAIN_EXCLUSION = 'chain_exclusion';
    /** R5: a foreign STORE/CHAIN identity (brand name or QID) on the row. Never a fuel brand. */
    public const BRAND_CONFLICT = 'brand_conflict';
    /** R6: category listed in the chain's (or the global) excluded categories. */
    public const CATEGORY_EXCLUDED = 'category_excluded';
    /** R6: category not in the chain's allowed categories. */
    public const CATEGORY_NOT_ALLOWED = 'category_not_allowed';
    /** R7: no format resolves, more than one does, or department identity met a non-department format. */
    public const UNSUPPORTED_FORMAT = 'unsupported_format';
    // v2: the category (or a source-category rescue) demands own-QID or name-alias identity.
    public const STRONG_IDENTITY_REQUIRED = 'strong_identity_required';
    // v2: a brand alias alone, at a chain whose brand field is measured to be misattributed.
    public const BRAND_ALIAS_UNCORROBORATED = 'brand_alias_uncorroborated';

    // ── Diagnostics: recorded, never change a membership ────────────────────────────────────
    /** A fuel brand the chain declares as expected co-located fuel (Mobil on 7-Eleven). */
    public const FUEL_BRAND_EXPECTED = 'fuel_brand_expected';
    /** A fuel brand the chain does NOT declare (Arco on a Shell row). Evidence for validation only. */
    public const FUEL_BRAND_CONFLICT = 'fuel_brand_conflict';

    /** @return list<string> */
    public static function rowRefusals(): array
    {
        return [
            self::CATEGORY_NOT_IMPORTED, self::STATUS_EXCLUDED, self::MALFORMED_WIKIDATA,
            self::CONFLICTING_WIKIDATA, self::EXCLUSION_WIKIDATA, self::EXCLUSION_PATTERN, self::CLOSED_NAME,
            self::NO_CHAIN, self::ALL_CANDIDATES_REJECTED, self::AMBIGUOUS,
        ];
    }

    /** @return list<string> */
    public static function candidateDrops(): array
    {
        return [
            self::CHAIN_EXCLUSION, self::BRAND_CONFLICT, self::CATEGORY_EXCLUDED,
            self::CATEGORY_NOT_ALLOWED, self::UNSUPPORTED_FORMAT,
            self::STRONG_IDENTITY_REQUIRED, self::BRAND_ALIAS_UNCORROBORATED,
        ];
    }

    /** @return list<string> */
    public static function diagnostics(): array
    {
        return [self::FUEL_BRAND_EXPECTED, self::FUEL_BRAND_CONFLICT];
    }
}
