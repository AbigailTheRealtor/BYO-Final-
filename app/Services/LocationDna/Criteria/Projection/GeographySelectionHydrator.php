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

    public function __construct(
        private readonly CriteriaGeographyRepository $geography,
    ) {
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

        $exact = [];
        $loose = [];

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
        }

        return $this->partition($labels, function (string $label) use ($exact, $loose): ?string {
            $wanted = $this->key($this->stripStateSuffix($label));

            return $exact[$wanted]
                ?? $loose[$wanted]
                ?? $exact[$this->stripCountyClass($wanted)]
                ?? null;
        });
    }

    /**
     * @param  list<string>  $countyIds
     * @param  list<string>  $labels
     * @return array{0: list<string>, 1: list<string>}
     */
    private function matchCities(array $countyIds, array $labels): array
    {
        $index = [];

        if ($countyIds !== []) {
            foreach ($this->geography->citiesInCounties($countyIds) as $option) {
                if ($option->is(GeographyOption::KIND_CITY)) {
                    $index[$this->key($option->name)] ??= $option->id;
                }
            }
        }

        return $this->partition(
            $labels,
            fn (string $label): ?string => $index[$this->key($this->stripStateSuffix($label))] ?? null,
        );
    }

    /**
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
