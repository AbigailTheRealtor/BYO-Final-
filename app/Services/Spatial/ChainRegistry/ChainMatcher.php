<?php

namespace App\Services\Spatial\ChainRegistry;

/**
 * Decides which chains one corpus-v2 place row belongs to. Pure and deterministic: one row in,
 * one {@see ChainMatchResult} out, no database, no network, no coordinates, no fuzzy matching.
 * Design: docs/spatial/overture-chain-registry-design.md §12. Precedence version:
 * {@see ChainRegistry::MATCH_PRECEDENCE_VERSION}.
 *
 *   R0  row validity: category ∈ the 16 canonical keys; status open/NULL; QID well-formed; a
 *       QID-shaped brand NAME is read as the brand QID and must agree with the QID field
 *   R1  global service-exclusion QID                      → reject the row
 *   R2  global exclusion / closed-name pattern (name or brand) → reject the row
 *   R3  identity candidates per chain, strongest first: own QID → brand alias (exact) →
 *       name alias (exact / word-prefix) → declared co-brand compound name (whole name) →
 *       department QID
 *   R4  chain-local exclusion QID or pattern              → drop that candidate
 *   R5  foreign STORE/CHAIN identity on the row           → drop that candidate
 *   R6  category excluded / not allowed                   → drop that candidate
 *   R7  format and role resolution                        → drop that candidate if none
 *   R8  more than one survivor must be a declared co-brand evidenced on THIS row, else ambiguous
 *
 * THREE KINDS OF FOREIGN IDENTITY, AND THEY ARE NOT THE SAME THING
 *   * a global SERVICE exclusion QID (Western Union, an ATM operator) rejects the row at R1;
 *   * a foreign STORE/CHAIN identity (another brand's QID or brand name) drops the candidate
 *     at R5;
 *   * a FUEL brand (Mobil, Arco — `global.fuel_brands`) is DIAGNOSTIC ONLY. It never creates a
 *     candidate, never counts as foreign at R5, and never removes a membership. It is recorded
 *     as `fuel_brand_expected` or `fuel_brand_conflict` for validation evidence. Fuel identity
 *     is not store-chain identity.
 *
 * No positive evidence can override an exclusion: R1/R2 run before identity is even collected,
 * and R4–R6 each drop a candidate outright whatever its match method was.
 */
final class ChainMatcher
{
    public function __construct(private readonly ChainRegistry $registry)
    {
    }

    public static function withConfiguredRegistry(): self
    {
        return new self(ChainRegistry::load());
    }

    public function registry(): ChainRegistry
    {
        return $this->registry;
    }

    public function match(ChainMatchInput $input): ChainMatchResult
    {
        // ── R0 ───────────────────────────────────────────────────────────────────────────────
        $category = strtolower(trim((string) $input->categoryKey));
        if (! $this->registry->isCanonicalCategory($category)) {
            return ChainMatchResult::rejected(ChainMatchReason::CATEGORY_NOT_IMPORTED);
        }

        $status = strtolower(trim((string) $input->operatingStatus));
        if ($status !== '' && $status !== 'open') {
            return ChainMatchResult::rejected(ChainMatchReason::STATUS_EXCLUDED);
        }

        $qid = ChainNameNormalizer::wikidata($input->brandWikidata);
        if ($qid === false) {
            return ChainMatchResult::rejected(ChainMatchReason::MALFORMED_WIKIDATA);
        }

        $name = ChainNameNormalizer::normalize($input->name);
        $brand = ChainNameNormalizer::normalize($input->brandName);

        // The v1 place normaliser writes the brand QID into `brand` when `brand.names` is absent.
        // Such a value is not a brand NAME — but it IS a brand QID, and discarding it would let a
        // Western Union counter named "PUBLIX #1029" slip past R1. So it becomes the row's QID;
        // if the row also carries a different QID, the two brand fields contradict each other
        // and the row is refused rather than one of them being picked — unless exactly one of
        // them is a fuel brand: fuel identity is context, never a reason to refuse (decision 5),
        // so the store identity is kept and the fuel brand is recorded as a diagnostic.
        $fuelFromOtherField = null;
        if (ChainNameNormalizer::isQidShaped($brand)) {
            $fromBrand = ChainNameNormalizer::wikidata($brand);
            $brand = null;
            if ($fromBrand === false) {
                return ChainMatchResult::rejected(ChainMatchReason::MALFORMED_WIKIDATA);
            }
            if ($qid === null) {
                $qid = $fromBrand;
            } elseif ($fromBrand !== $qid) {
                $fuelInBrand = $this->registry->fuelBrandForWikidata($fromBrand);
                $fuelInQid = $this->registry->fuelBrandForWikidata($qid);
                if ($fuelInBrand !== null && $fuelInQid === null) {
                    $fuelFromOtherField = $fuelInBrand;
                } elseif ($fuelInQid !== null && $fuelInBrand === null) {
                    $fuelFromOtherField = $fuelInQid;
                    $qid = $fromBrand;
                } else {
                    return ChainMatchResult::rejected(ChainMatchReason::CONFLICTING_WIKIDATA);
                }
            }
        }

        // ── R1 ───────────────────────────────────────────────────────────────────────────────
        if ($qid !== null && $this->registry->isGlobalExclusionWikidata($qid)) {
            return ChainMatchResult::rejected(ChainMatchReason::EXCLUSION_WIKIDATA);
        }

        // ── R2 ───────────────────────────────────────────────────────────────────────────────
        if (self::anyPatternMatches($this->registry->exclusionNamePatterns(), $name, $brand)) {
            return ChainMatchResult::rejected(ChainMatchReason::EXCLUSION_PATTERN);
        }
        if (self::anyPatternMatches($this->registry->closedNamePatterns(), $name, $brand)) {
            return ChainMatchResult::rejected(ChainMatchReason::CLOSED_NAME);
        }

        // Fuel brands on the row: context only, from here on.
        $fuelByQid = $qid !== null ? $this->registry->fuelBrandForWikidata($qid) : null;
        $fuelByName = $brand !== null ? $this->registry->fuelBrandForName($brand) : $fuelFromOtherField;

        // ── R3 ───────────────────────────────────────────────────────────────────────────────
        $candidates = [];
        foreach ($this->registry->chains() as $key => $chain) {
            $candidate = $this->identify($chain, $qid, $name, $brand, $fuelByQid, $fuelByName);
            if ($candidate !== null) {
                $candidates[$key] = $candidate;
            }
        }

        if ($candidates === []) {
            return ChainMatchResult::noMatch(ChainMatchReason::NO_CHAIN, [], []);
        }

        // ── R4–R7, per candidate ─────────────────────────────────────────────────────────────
        $drops = [];
        $diagnostics = [];
        $survivors = [];

        foreach ($candidates as $key => $candidate) {
            $chain = $this->registry->chain($key);

            foreach (array_unique(array_filter([$fuelByQid, $fuelByName])) as $fuelKey) {
                $diagnostics[] = [
                    'brand_key' => $key,
                    'code' => in_array($fuelKey, $chain->fuelBrands, true)
                        ? ChainMatchReason::FUEL_BRAND_EXPECTED
                        : ChainMatchReason::FUEL_BRAND_CONFLICT,
                    'fuel_brand' => $fuelKey,
                ];
            }

            $drop = $this->candidateDrop($chain, $candidate, $qid, $name, $brand, $category, $fuelByQid, $fuelByName);
            if ($drop !== null) {
                $drops[$key] = $drop;
                continue;
            }

            $format = $this->resolveFormat($chain, $category, $candidate['remainder']);
            if ($format === null || ($candidate['department_identity'] && $format->role !== ChainRole::DEPARTMENT)) {
                $drops[$key] = ChainMatchReason::UNSUPPORTED_FORMAT;
                continue;
            }

            $survivors[$key] = $candidate + ['format' => $format];
        }

        if ($survivors === []) {
            return ChainMatchResult::noMatch(ChainMatchReason::ALL_CANDIDATES_REJECTED, $drops, $diagnostics);
        }

        // ── R8 ───────────────────────────────────────────────────────────────────────────────
        $keys = array_keys($survivors);
        if (count($keys) > 1) {
            foreach ($keys as $i => $a) {
                foreach (array_slice($keys, $i + 1) as $b) {
                    if (! $this->coBrandEvidenced($a, $survivors[$a], $b, $survivors[$b])) {
                        return ChainMatchResult::ambiguous($keys, $drops, $diagnostics);
                    }
                }
            }
        }

        $memberships = [];
        foreach ($survivors as $key => $s) {
            $memberships[] = new ChainMembership(
                $key,
                $s['format']->role,
                $s['format']->key,
                $s['method'],
                array_values(array_diff($keys, [$key])),
            );
        }

        return ChainMatchResult::matched($memberships, $drops, $diagnostics);
    }

    /**
     * R3 for one chain. Null when the row carries no identity evidence for it.
     *
     * @return array{method: string, remainder: string, department_identity: bool, name_identity: bool, brand_identity: bool, compound_partners: list<string>}|null
     */
    private function identify(ChainDefinition $chain, ?string $qid, ?string $name, ?string $brand, ?string $fuelByQid, ?string $fuelByName): ?array
    {
        $own = $qid !== null && $chain->hasOwnWikidata($qid);
        $brandAlias = $brand !== null && $chain->hasBrandAlias($brand);
        $department = $qid !== null && in_array($qid, $chain->departmentWikidataIds, true);

        // Longest matching name alias, so the remainder does not depend on alias order.
        $aliasLength = null;
        if ($name !== null) {
            foreach ($chain->aliases as $alias) {
                $hit = $alias['match'] === ChainRegistry::MATCH_EXACT
                    ? $name === $alias['value']
                    : self::wordPrefixed($name, $alias['value']);
                if ($hit && ($aliasLength === null || strlen($alias['value']) > $aliasLength)) {
                    $aliasLength = strlen($alias['value']);
                }
            }
        }

        $compoundLength = null;
        $compoundPartners = [];
        if ($name !== null) {
            foreach ($chain->coBrands as $partner => $compounds) {
                foreach ($compounds as $compound) {
                    // Whole-name equality only: "7-Eleven Speedway Blvd" is an address, not a co-brand.
                    if ($name === $compound) {
                        $compoundPartners[] = $partner;
                        $compoundLength = max($compoundLength ?? 0, strlen($compound));
                    }
                }
            }
        }
        $compoundPartners = array_values(array_unique($compoundPartners));
        sort($compoundPartners, SORT_STRING);

        if (! $own && ! $brandAlias && $aliasLength === null && $compoundPartners === [] && ! $department) {
            return null;
        }

        if ($own) {
            $method = ChainMembership::METHOD_OWN_WIKIDATA;
        } elseif ($brandAlias) {
            $method = ChainMembership::METHOD_BRAND_ALIAS;
        } elseif ($aliasLength !== null) {
            $method = ChainMembership::METHOD_NAME_ALIAS;
        } elseif ($compoundPartners !== []) {
            $method = ChainMembership::METHOD_CO_BRAND_COMPOUND;
        } else {
            $method = ChainMembership::METHOD_DEPARTMENT_WIKIDATA;
        }

        // The text a format pattern reads: what follows the longest matched name prefix, or the
        // whole name when identity came from elsewhere (a RaceTrac row named "Polo").
        $consumed = max($aliasLength ?? 0, $compoundLength ?? 0);
        $remainder = $name === null ? '' : trim(substr($name, $consumed));

        return [
            'method' => $method,
            'remainder' => $remainder,
            'department_identity' => $department,
            'name_identity' => $aliasLength !== null,
            // Brand identity that the row's OTHER brand field does not contradict. Only this may
            // serve as co-brand evidence: a QID naming one partner beside a brand name naming the
            // other is a contradiction, not two brands.
            'brand_identity' => ($own || $brandAlias)
                && ($qid === null || $own || $fuelByQid !== null)
                && ($brand === null || $brandAlias || $fuelByName !== null),
            'compound_partners' => $compoundPartners,
        ];
    }

    /**
     * R4–R6 for one candidate. Null when the candidate survives them.
     *
     * @param array{method: string, remainder: string, department_identity: bool, name_identity: bool, brand_identity: bool, compound_partners: list<string>} $candidate
     */
    private function candidateDrop(
        ChainDefinition $chain,
        array $candidate,
        ?string $qid,
        ?string $name,
        ?string $brand,
        string $category,
        ?string $fuelByQid,
        ?string $fuelByName,
    ): ?string {
        // R4 — chain-local exclusions.
        if ($qid !== null && in_array($qid, $chain->exclusionWikidataIds, true)) {
            return ChainMatchReason::CHAIN_EXCLUSION;
        }
        if (self::anyPatternMatches($chain->exclusionNamePatterns, $name, $brand)) {
            return ChainMatchReason::CHAIN_EXCLUSION;
        }

        // R5 — foreign STORE/CHAIN identity. A fuel brand is never foreign here.
        $partners = array_keys($chain->coBrands);
        if ($qid !== null && $fuelByQid === null
            && ! $chain->hasOwnWikidata($qid)
            && ! in_array($qid, $chain->departmentWikidataIds, true)
            && ! $this->anyPartner($partners, static fn (ChainDefinition $p): bool => $p->hasOwnWikidata($qid))
        ) {
            return ChainMatchReason::BRAND_CONFLICT;
        }
        if ($brand !== null && $fuelByName === null
            && ! $chain->hasBrandAlias($brand)
            && ! $this->anyPartner($partners, static fn (ChainDefinition $p): bool => $p->hasBrandAlias($brand))
        ) {
            return ChainMatchReason::BRAND_CONFLICT;
        }

        // R6 — categories.
        if (in_array($category, $this->registry->globalExcludedCategories(), true)
            || in_array($category, $chain->excludedCategories, true)
        ) {
            return ChainMatchReason::CATEGORY_EXCLUDED;
        }
        if (! isset($chain->allowedCategories[$category])) {
            return ChainMatchReason::CATEGORY_NOT_ALLOWED;
        }

        return null;
    }

    /**
     * R7. Only formats listing the row's category are eligible. Exactly one pattern hit wins;
     * two are refused rather than ordered; with none, the category's single default applies.
     */
    private function resolveFormat(ChainDefinition $chain, string $category, string $remainder): ?ChainFormat
    {
        $hits = [];
        $default = null;
        foreach ($chain->formats as $format) {
            if (! $format->appliesTo($category)) {
                continue;
            }
            if ($format->isDefault()) {
                $default = $format;
                continue;
            }
            foreach ($format->namePatterns as $pattern) {
                if (preg_match($pattern, $remainder) === 1) {
                    $hits[] = $format;
                    break;
                }
            }
        }

        if (count($hits) > 1) {
            return null;
        }

        return $hits[0] ?? $default;
    }

    /**
     * R8 for one surviving pair: the pair must be declared, AND this row must evidence both —
     * a declared compound name, or one chain's name alias with the other's brand identity.
     * Never proximity; there are no coordinates here to infer it from.
     *
     * @param array{name_identity: bool, brand_identity: bool, compound_partners: list<string>} $a
     * @param array{name_identity: bool, brand_identity: bool, compound_partners: list<string>} $b
     */
    private function coBrandEvidenced(string $aKey, array $a, string $bKey, array $b): bool
    {
        if (! $this->registry->chain($aKey)->isCoBrandPartner($bKey)) {
            return false;
        }

        if (in_array($bKey, $a['compound_partners'], true) && in_array($aKey, $b['compound_partners'], true)) {
            return true;
        }

        return ($a['name_identity'] && $b['brand_identity']) || ($b['name_identity'] && $a['brand_identity']);
    }

    /**
     * @param list<string> $partnerKeys
     * @param callable(ChainDefinition): bool $test
     */
    private function anyPartner(array $partnerKeys, callable $test): bool
    {
        foreach ($partnerKeys as $partner) {
            if ($test($this->registry->chain($partner))) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, string> $patterns */
    private static function anyPatternMatches(array $patterns, ?string ...$subjects): bool
    {
        foreach ($patterns as $pattern) {
            foreach ($subjects as $subject) {
                if ($subject !== null && preg_match($pattern, $subject) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function wordPrefixed(string $subject, string $prefix): bool
    {
        return $subject === $prefix || str_starts_with($subject, $prefix . ' ');
    }
}
