<?php

namespace App\Services\Spatial\ChainRegistry;

/**
 * Decides which chains one corpus-v2 place row belongs to. Pure and deterministic: one row in,
 * one {@see ChainMatchResult} out, no database, no network, no coordinates, no fuzzy matching.
 * Design: docs/spatial/overture-chain-registry-design.md §12. Precedence version:
 * {@see ChainRegistry::MATCH_PRECEDENCE_VERSION}.
 *
 *   R0  row validity: category ∈ the 16 canonical keys — or, with no canonical key, a raw source
 *       token some chain declares a rescue for (v2), which restricts R3 to those chains; status
 *       open/NULL; QID well-formed; a QID-shaped brand NAME is read as the brand QID and must
 *       agree with the QID field
 *   R1  global service-exclusion QID                      → reject the row
 *   R2  global exclusion / closed-name pattern (name or brand) → reject the row
 *   R3  identity candidates per chain, strongest first: own QID → brand alias (exact) →
 *       name alias (exact / word-prefix) → declared co-brand compound name (whole name) →
 *       department QID
 *   R4  chain-local exclusion QID or pattern, and a rescue's own sub-entity patterns → drop
 *   R5  foreign STORE/CHAIN identity on the row           → drop that candidate, unless the
 *       foreign identity is a declared HOST chain of a format whose host categories include the
 *       row's category and the row names this chain (v2: CVS inside Target) — that format is
 *       then forced
 *   R6  category excluded / not allowed                   → drop that candidate
 *   R6b the category or rescue demands STRONG identity (own QID or name alias) (v2) → drop
 *   R7  format and role resolution                        → drop that candidate if none
 *   R7b a brand alias alone, at a chain that requires corroboration, with no own/department
 *       QID, no name alias and no name-pattern format (v2) → drop that candidate
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
 * and R4–R7b each drop a candidate outright whatever its match method was. A rescued row passes
 * every step a canonical row does, with the rescue's target category standing in for its own.
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
        $rescueSource = null;
        if (! $this->registry->isCanonicalCategory($category)) {
            // Only a row with NO canonical key may be rescued, and only by a chain that names its
            // raw token. A malformed canonical key is never reinterpreted as a source token.
            $source = strtolower(trim((string) $input->sourceCategory));
            if ($category !== '' || $source === '' || $this->registry->rescueChainsFor($source) === []) {
                return ChainMatchResult::rejected(ChainMatchReason::CATEGORY_NOT_IMPORTED);
            }
            $rescueSource = $source;
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
            if ($rescueSource !== null && $chain->rescueFor($rescueSource) === null) {
                continue;
            }
            $candidate = $this->identify($chain, $qid, $name, $brand, $fuelByQid, $fuelByName);
            if ($candidate !== null) {
                $candidates[$key] = $candidate;
            }
        }

        if ($candidates === []) {
            // A rescue token no rescuing chain identified is simply a row outside the import list.
            return $rescueSource !== null
                ? ChainMatchResult::rejected(ChainMatchReason::CATEGORY_NOT_IMPORTED)
                : ChainMatchResult::noMatch(ChainMatchReason::NO_CHAIN, [], []);
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

            $rescue = $rescueSource !== null ? $chain->rescueFor($rescueSource) : null;
            $candidateCategory = $rescue !== null ? $rescue['as_category'] : $category;

            $hostFormat = null;
            $drop = $this->candidateDrop($chain, $candidate, $qid, $name, $brand, $candidateCategory, $fuelByQid, $fuelByName, $rescue, $hostFormat);
            if ($drop !== null) {
                $drops[$key] = $drop;
                continue;
            }

            $format = $hostFormat ?? $this->resolveFormat($chain, $candidateCategory, $candidate['remainder']);
            if ($format === null || ($candidate['department_identity'] && $format->role !== ChainRole::DEPARTMENT)) {
                $drops[$key] = ChainMatchReason::UNSUPPORTED_FORMAT;
                continue;
            }

            // R7b — a brand alias alone is not identity where the brand field is measured to be
            // misattributed. The place's own name, an own or department QID, or a format the
            // name itself selected must agree.
            if ($chain->brandAliasRequiresCorroboration
                && $candidate['method'] === ChainMembership::METHOD_BRAND_ALIAS
                && ! $candidate['name_identity']
                && ! $candidate['department_identity']
                && $candidate['compound_partners'] === []
                && $format->isDefault()
            ) {
                $drops[$key] = ChainMatchReason::BRAND_ALIAS_UNCORROBORATED;
                continue;
            }

            $survivors[$key] = $candidate + ['format' => $format, 'rescued_from' => $rescue !== null ? $rescueSource : null];
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
                $s['rescued_from'],
            );
        }

        return ChainMatchResult::matched($memberships, $drops, $diagnostics);
    }

    /**
     * R3 for one chain. Null when the row carries no identity evidence for it.
     *
     * @return array{method: string, own_identity: bool, remainder: string, department_identity: bool, name_identity: bool, brand_identity: bool, compound_partners: list<string>}|null
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
            'own_identity' => $own,
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
     * R4–R6b for one candidate. Null when the candidate survives them. When the R5 host exception
     * applies, `$hostFormat` receives the format it requires.
     *
     * @param array{as_category: string, exclusion_name_patterns: array<string, string>}|null $rescue
     * @param array{method: string, own_identity: bool, remainder: string, department_identity: bool, name_identity: bool, brand_identity: bool, compound_partners: list<string>} $candidate
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
        ?array $rescue,
        ?ChainFormat &$hostFormat,
    ): ?string {
        // R4 — chain-local exclusions, then the rescue's own sub-entity exclusions.
        if ($qid !== null && in_array($qid, $chain->exclusionWikidataIds, true)) {
            return ChainMatchReason::CHAIN_EXCLUSION;
        }
        if (self::anyPatternMatches($chain->exclusionNamePatterns, $name, $brand)) {
            return ChainMatchReason::CHAIN_EXCLUSION;
        }
        if ($rescue !== null && self::anyPatternMatches($rescue['exclusion_name_patterns'], $name, $brand)) {
            return ChainMatchReason::CHAIN_EXCLUSION;
        }

        // R5 — foreign STORE/CHAIN identity. A fuel brand is never foreign here. The one
        // exception is a declared host store: only with this chain in the place's own NAME, and
        // only in a category the format hosts in (CVS Pharmacy with brand "Target").
        $partners = array_keys($chain->coBrands);
        $host = $candidate['name_identity'] ? $this->hostFormat($chain, $category, $qid, $brand) : null;
        if ($qid !== null && $fuelByQid === null
            && ! $chain->hasOwnWikidata($qid)
            && ! in_array($qid, $chain->departmentWikidataIds, true)
            && ! $this->anyPartner($partners, static fn (ChainDefinition $p): bool => $p->hasOwnWikidata($qid))
            && ! ($host !== null && $host['by_qid'])
        ) {
            return ChainMatchReason::BRAND_CONFLICT;
        }
        if ($brand !== null && $fuelByName === null
            && ! $chain->hasBrandAlias($brand)
            && ! $this->anyPartner($partners, static fn (ChainDefinition $p): bool => $p->hasBrandAlias($brand))
            && ! ($host !== null && $host['by_brand'])
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

        // R6b — strong identity: the chain's own QID, or the chain named in the place's own name.
        // A rescue always demands it; that is not configurable.
        if (($rescue !== null || $chain->requiresStrongIdentity($category))
            && ! $candidate['own_identity'] && ! $candidate['name_identity']
        ) {
            return ChainMatchReason::STRONG_IDENTITY_REQUIRED;
        }

        // A host exception is used only if the row's foreign identity actually needed it.
        if ($host !== null && ($host['by_qid'] || $host['by_brand'])) {
            $hostFormat = $host['format'];
        }

        return null;
    }

    /**
     * The format whose declared host chain explains the row's foreign QID and/or brand, if any.
     * `by_qid` / `by_brand` say which foreign field the host accounts for — each is excused only
     * when the host owns it, so a host brand beside some third chain's QID still conflicts.
     *
     * @return array{format: ChainFormat, by_qid: bool, by_brand: bool}|null
     */
    private function hostFormat(ChainDefinition $chain, string $category, ?string $qid, ?string $brand): ?array
    {
        foreach ($chain->formats as $format) {
            if (! $format->hostsIn($category)) {
                continue;
            }
            foreach ($format->hostChains as $hostKey) {
                $hostChain = $this->registry->chain($hostKey);
                $byQid = $qid !== null && $hostChain->hasOwnWikidata($qid);
                $byBrand = $brand !== null && $hostChain->hasBrandAlias($brand);
                if ($byQid || $byBrand) {
                    return ['format' => $format, 'by_qid' => $byQid, 'by_brand' => $byBrand];
                }
            }
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
