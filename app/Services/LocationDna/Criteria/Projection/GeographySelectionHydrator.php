<?php

namespace App\Services\LocationDna\Criteria\Projection;

use App\Services\LocationDna\Criteria\CriteriaGeographyRepository;
use App\Services\LocationDna\Criteria\GeographyOption;
use App\Services\LocationDna\Criteria\Rules\GeographySelection;

/**
 * Phase 1c — reads a stored geography blob back into an id-carried selection.
 *
 * THE INVERSE OF {@see GeographyLabelProjector}, AND THE RISKY DIRECTION
 * ---------------------------------------------------------------------
 * Projection is total: every id the corpus enumerated has a name. Hydration is not. It is handed
 * free text written by an editor that never consulted the reference tables, on records going back
 * years, and a share of it will not match anything.
 *
 * The tempting behaviour is to drop what cannot be matched — it produces a clean selection and it
 * is one line shorter. It is also the single most destructive thing this phase could do: a user
 * opens an old listing, edits an unrelated field, saves, and loses counties they never touched.
 * No error, no message, no way to notice until a match stops happening. So the rule is the
 * opposite one, and this class is built around it rather than guarded against:
 *
 *     an unmatched label is PRESERVED VERBATIM, never dropped and never guessed at.
 *
 * NEVER THROWS
 * ------------
 * Stored blobs are untrusted input — hand-edited meta, values from three schema versions ago,
 * wrong-typed JSON. A hydrator that threw would make an old record impossible to open at all,
 * which is a worse failure than any it could report. Everything unusable becomes preserved history
 * or is skipped.
 *
 * MATCHING IS TOLERANT IN ONE DIRECTION ONLY
 * ------------------------------------------
 * It accepts more shapes than it emits — with or without the state suffix, any case, any padding,
 * a county with or without its class word, an unpadded ZIP. It never invents a match: tolerance is
 * about recognising what the corpus already contains, not about finding the nearest thing.
 *
 * FOUR RUNGS, TRIED IN ORDER (Phase 1d-3)
 * ---------------------------------------
 * The tolerance above was built for the reference tables. Published Census geography spells the
 * same places differently — `Bayamón Municipio` where a stored label says `Bayamon County`,
 * `DeKalb County` where it says `De Kalb County` — so two rungs were added BELOW the existing ones
 * rather than folded into them:
 *
 *   1. EXACT, and the class-suffix tolerance that has always accompanied it. Unchanged, and still
 *      answers first, so nothing that matched before matches differently now.
 *   2. DETERMINISTIC COMPATIBILITY NORMALISATION — {@see GeographyNameCompatibility}. Accents fold,
 *      punctuation and spacing stop mattering, `saint` and `st` converge, and one geography-class
 *      word is removed at the county tier. Ambiguity resolves to NOTHING, never to the first hit.
 *   3. EXPLICIT ALIASES — {@see GeographyNameAliases}. Only for names no rule can derive, each one
 *      written down individually.
 *   4. PRESERVE, exactly as before.
 *
 * The order is the safety argument. Each rung is strictly more permissive than the one above it and
 * is only consulted when the one above found nothing, so widening the bottom cannot change what the
 * top already answered. `key()` in particular is untouched — it is the comparison form of rung 1,
 * and every stored record and existing suite depends on precisely what it does today.
 *
 * ZIPS GAIN NOTHING FROM ANY OF THIS, ON PURPOSE
 * ----------------------------------------------
 * A ZIP is digits; there is no accent, no class word and no spelling to reconcile. The only
 * "compatibility" available at that tier would be mapping a PO-box ZIP onto the ZCTA that
 * geographically surrounds it, and that is a different claim about the world, not a different
 * spelling of the same one. So the ZIP matcher below is byte-for-byte what Phase 1c shipped: the
 * Census ZCTA is authoritative where it matches, and a legacy ZIP with no counterpart is preserved.
 *
 * READ-ONLY, like everything in this namespace.
 */
final class GeographySelectionHydrator
{
    /**
     * County-class words stripped when comparing a stored label to a corpus name.
     *
     * Ordered longest-first so a two-word class is removed whole rather than leaving a fragment.
     * This mirrors the repository's own list — the two solve the same naming problem from opposite
     * ends, and a class not named here simply fails to match loosely and is preserved, which is the
     * safe direction to fail in.
     *
     * @var list<string>
     */
    private const COUNTY_CLASSES = [
        ' city and borough',
        ' census area',
        ' municipality',
        ' parish',
        ' borough',
        ' county',
        ' city',
    ];

    private readonly GeographyNameCompatibility $compatibility;

    private readonly GeographyNameAliases $aliases;

    /**
     * The constructor signature is deliberately unchanged.
     *
     * Two call sites build this class by hand — the cascade trait and the unit suite — and both
     * pass the repository alone. The compatibility rungs are behaviour of the hydrator rather than
     * a collaborator a caller chooses between, so they are constructed here instead of being added
     * to the signature: no wiring moves, no container binding is needed, and there is no way to
     * assemble a hydrator that is missing them.
     */
    public function __construct(
        private readonly CriteriaGeographyRepository $geography,
    ) {
        $this->compatibility = new GeographyNameCompatibility();
        $this->aliases       = new GeographyNameAliases($this->compatibility);
    }

    /**
     * Hydrate the four canonical geography keys of a stored blob.
     *
     * @param  array<string, mixed>  $blob  the decoded `location_dna_preferences` document
     */
    public function fromLabels(array $blob): HydratedGeography
    {
        $stateLabel = $this->text($blob['state'] ?? null);
        $counties   = $this->labels($blob['counties'] ?? null);
        $cities     = $this->labels($blob['cities'] ?? null);
        $zips       = $this->labels($blob['zip_codes'] ?? null);

        $stateId = $stateLabel === null ? null : $this->matchState($stateLabel);

        // ── No usable state ⇒ nothing below it can be enumerated, let alone matched. ─────
        //
        // Everything is preserved rather than dropped. This is the branch that decides what
        // happens to a record whose state was renamed or mistyped, and emptying it would take the
        // whole location with it.
        if ($stateId === null) {
            return new HydratedGeography(
                GeographySelection::empty(),
                new PreservedGeographyLabels($stateLabel, $counties, $cities, $zips),
            );
        }

        [$countyIds, $unmatchedCounties] = $this->matchCounties($stateId, $counties);

        // Cities and ZIPs are justified by the counties that MATCHED. A city under a preserved
        // county has nothing to hang from, so it is preserved too rather than promoted into a
        // selection the resolver would immediately clear.
        [$cityIds, $unmatchedCities] = $this->matchCities($countyIds, $cities);
        [$zipCodes, $unmatchedZips]  = $this->matchZips($countyIds, $zips);

        return new HydratedGeography(
            GeographySelection::of($stateId, $countyIds, $cityIds, $zipCodes),
            new PreservedGeographyLabels(null, $unmatchedCounties, $unmatchedCities, $unmatchedZips),
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // TIER MATCHING
    // ═════════════════════════════════════════════════════════════════════════

    private function matchState(string $label): ?string
    {
        $wanted = $this->key($this->stripStateSuffix($label));

        foreach ($this->geography->states() as $option) {
            if ($option->is(GeographyOption::KIND_STATE) && $this->key($option->name) === $wanted) {
                return $option->id;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $labels
     * @return array{0: list<string>, 1: list<string>} matched ids, preserved labels
     */
    private function matchCounties(string $stateId, array $labels): array
    {
        $options = $this->geography->countiesInState($stateId);

        $exact  = [];
        $loose  = [];
        $compat = [];

        foreach ($options as $option) {
            if (! $option->is(GeographyOption::KIND_COUNTY)) {
                continue;
            }

            $name = $this->key($option->name);

            $exact[$name] ??= $option->id;

            // The corpus spells the class word; a stored label may not. Indexing the stripped form
            // too is what lets `Pinellas, FL` find `Pinellas County`. Registered only when it does
            // not collide, so an ambiguous short form matches nothing rather than the wrong thing.
            $bare = $this->stripCountyClass($name);

            if ($bare !== $name && $bare !== '') {
                $loose[$bare] = array_key_exists($bare, $loose) ? null : $option->id;
            }

            // Rung 2's index, built alongside rather than instead of the two above. It reduces the
            // corpus name the same way the stored label will be reduced, which is what lets
            // `Adjuntas County` reach `Adjuntas Municipio` — the class word is gone from BOTH sides
            // before either is compared, so neither spelling is privileged.
            $this->compatibility->register($compat, $this->compatibility->countyKey($name), $option->id);
        }

        return $this->partition($labels, function (string $label) use ($exact, $loose, $compat): ?string {
            $wanted = $this->key($this->stripStateSuffix($label));

            // ── Rung 1 · exact, and the class-suffix tolerance. Unchanged. ──────
            $matched = $exact[$wanted]
                ?? $loose[$wanted]
                ?? $exact[$this->stripCountyClass($wanted)]
                ?? null;

            if ($matched !== null) {
                return $matched;
            }

            // ── Rung 2 · deterministic compatibility normalisation. ─────────────
            //
            // No rung 3 here: no county name has yet been found that a rule cannot derive, and an
            // alias table with nothing in it would only invite one. See GeographyNameAliases.
            return $this->compatibility->lookup($compat, $this->compatibility->countyKey($wanted));
        });
    }

    /**
     * @param  list<string>  $countyIds
     * @param  list<string>  $labels
     * @return array{0: list<string>, 1: list<string>}
     */
    private function matchCities(array $countyIds, array $labels): array
    {
        $index  = [];
        $compat = [];

        if ($countyIds !== []) {
            foreach ($this->geography->citiesInCounties($countyIds) as $option) {
                if ($option->is(GeographyOption::KIND_CITY)) {
                    $name = $this->key($option->name);

                    $index[$name] ??= $option->id;

                    // A place spanning two selected counties is enumerated once per parent, so this
                    // sees one id twice. `register()` treats that as the same option rather than as
                    // a collision — see the rule it documents.
                    $this->compatibility->register($compat, $this->compatibility->placeKey($name), $option->id);
                }
            }
        }

        return $this->partition($labels, function (string $label) use ($index, $compat): ?string {
            $wanted = $this->key($this->stripStateSuffix($label));

            // ── Rung 1 · exact. Unchanged. ──────────────────────────────────────
            if (isset($index[$wanted])) {
                return $index[$wanted];
            }

            // ── Rung 2 · deterministic compatibility normalisation. ─────────────
            $normalized = $this->compatibility->placeKey($wanted);
            $matched    = $this->compatibility->lookup($compat, $normalized);

            if ($matched !== null) {
                return $matched;
            }

            // ── Rung 3 · explicit alias, resolved through the SAME index. ───────
            //
            // The alias supplies a corpus name, not an id. It is then looked up like any other
            // name, so an alias whose target is not among the enumerated counties — or is ambiguous
            // there — resolves to nothing and the label is preserved. An alias redirects; it does
            // not assert that the destination exists.
            $alias = $this->aliases->city($normalized);

            return $alias === null ? null : $this->compatibility->lookup($compat, $alias);
        });
    }

    /**
     * DELIBERATELY UNTOUCHED BY THE COMPATIBILITY RUNGS (Phase 1d-3).
     *
     * A ZIP carries no spelling to reconcile, so the only thing a compatibility layer could offer
     * here is mapping a PO-box ZIP onto the ZCTA that surrounds it. That is not a normalisation —
     * a PO-box ZIP has no area, and the ZCTA around it is a DIFFERENT geography that happens to
     * contain the building. Treating them as the same value would silently rewrite a stored ZIP
     * into one the user never entered, and would do it to records nobody is looking at.
     *
     * So the rule stays exactly as Phase 1c left it: the ZCTA is authoritative where the stored ZIP
     * matches one, and a ZIP with no counterpart in the corpus is preserved verbatim. Nothing is
     * migrated, converted or dropped.
     *
     * @param  list<string>  $countyIds
     * @param  list<string>  $labels
     * @return array{0: list<string>, 1: list<string>}
     */
    private function matchZips(array $countyIds, array $labels): array
    {
        $index = [];

        if ($countyIds !== []) {
            foreach ($this->geography->zipsInCounties($countyIds) as $option) {
                if ($option->is(GeographyOption::KIND_ZIP)) {
                    // A ZIP associated with several counties appears once per parent. The selection
                    // carries ZIP CODES, so collapsing to the code here is the association rule,
                    // not a deduplication convenience.
                    $index[$option->id] = $option->id;
                }
            }
        }

        return $this->partition($labels, function (string $label) use ($index): ?string {
            $canonical = $this->canonicalZip($label);

            return $canonical === null ? null : ($index[$canonical] ?? null);
        });
    }

    /**
     * Split labels into what a matcher resolved and what it did not.
     *
     * Duplicates collapse on the matched side — the same place twice is one selection, not a
     * violation for the validator to report — while an unmatched label is preserved once.
     *
     * @param  list<string>                   $labels
     * @param  callable(string): (string|null) $match
     * @return array{0: list<string>, 1: list<string>}
     */
    private function partition(array $labels, callable $match): array
    {
        $matched   = [];
        $preserved = [];
        $seenId    = [];
        $seenLabel = [];

        foreach ($labels as $label) {
            $id = $match($label);

            if ($id === null) {
                if (! isset($seenLabel[$label])) {
                    $seenLabel[$label] = true;
                    $preserved[]       = $label;
                }

                continue;
            }

            if (! isset($seenId[$id])) {
                $seenId[$id] = true;
                $matched[]   = $id;
            }
        }

        return [$matched, $preserved];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // NORMALISATION
    // ═════════════════════════════════════════════════════════════════════════

    /** Remove a trailing `, ST` — the suffix the previous editor added and the corpus lacks. */
    private function stripStateSuffix(string $label): string
    {
        return (string) preg_replace('/\s*,\s*[A-Za-z]{2}\s*$/', '', trim($label));
    }

    /** Remove a county-class word from an already-keyed (lowercased) name. */
    private function stripCountyClass(string $keyed): string
    {
        foreach (self::COUNTY_CLASSES as $class) {
            if (str_ends_with($keyed, $class)) {
                return trim(substr($keyed, 0, -strlen($class)));
            }
        }

        return $keyed;
    }

    /** Comparison form: lowercased, with internal whitespace collapsed. */
    private function key(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtolower(trim($value))));
    }

    /**
     * Canonical five-digit ZIP, or null when the value is not a ZIP at all.
     *
     * Short all-digit values are padded, because 3,000 reference rows are stored that way and a
     * blob may carry the same. A value that is not digits is NOT repaired into one — it is left
     * unmatched so it is preserved verbatim rather than turned into a different, valid-looking ZIP.
     */
    private function canonicalZip(string $label): ?string
    {
        $trimmed = trim($label);

        if ($trimmed === '' || strlen($trimmed) > 5 || ! ctype_digit($trimmed)) {
            return null;
        }

        return str_pad($trimmed, 5, '0', STR_PAD_LEFT);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // UNTRUSTED INPUT
    // ═════════════════════════════════════════════════════════════════════════

    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return trim($value) === '' ? null : trim($value);
    }

    /**
     * A blob list, with blanks removed.
     *
     * A blank is neither a selection nor history worth carrying — it is an artefact of an editor
     * that allowed an empty tag, and preserving it would show the user an empty chip they cannot
     * name.
     *
     * @return list<string>
     */
    private function labels(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];

        foreach ($value as $entry) {
            if (is_string($entry) && trim($entry) !== '') {
                $out[] = $entry;
            }
        }

        return array_values($out);
    }
}
