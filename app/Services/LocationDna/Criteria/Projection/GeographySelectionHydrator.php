<?php

namespace App\Services\LocationDna\Criteria\Projection;

use App\Services\LocationDna\Criteria\CriteriaGeographyRepository;
use App\Services\LocationDna\Criteria\CriteriaNeighborhoodRepository;
use App\Services\LocationDna\Criteria\GeographyOption;
use App\Services\LocationDna\Criteria\NullCriteriaNeighborhoodRepository;
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
 * a county with or without its class word, a `Saint` the corpus spells `St.`, an unpadded ZIP. It
 * never invents a match: tolerance is about recognising what the corpus already contains, not about
 * finding the nearest thing.
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

    /**
     * Phase 1d-5 — `$neighborhoods` is OPTIONAL and defaults to the null object, so every existing
     * call site (including the Livewire trait, untouched by this slice) hydrates exactly as before:
     * a label the city tier cannot match stays preserved, which is today's behaviour precisely.
     */
    public function __construct(
        private readonly CriteriaGeographyRepository $geography,
        private readonly CriteriaNeighborhoodRepository $neighborhoods = new NullCriteriaNeighborhoodRepository(),
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
        [$cityIds, $leftoverCityLabels] = $this->matchCities($countyIds, $cities);
        [$zipCodes, $unmatchedZips]     = $this->matchZips($countyIds, $zips);

        // ── Phase 1d-5 — a SECOND PASS over what the city tier could not match ───────────
        //
        // Neighbourhoods are stored in the `cities` array, because that is where they have always
        // been: `Clearwater Beach, FL` sits in the cities list of records written years ago, and
        // until now nothing could recognise it. So the leftovers of city matching are exactly the
        // right candidates, and no stored blob has to change for this to work.
        [$neighborhoodIds, $unmatchedCities] = $this->matchNeighborhoods($cityIds, $leftoverCityLabels);

        return new HydratedGeography(
            GeographySelection::of($stateId, $countyIds, $cityIds, $zipCodes, $neighborhoodIds),
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
     * Phase 1d-5 — match the city tier's leftovers against the neighbourhoods of the MATCHED cities.
     *
     * ⚠ THE PARENT CITY MUST ALREADY BE SELECTED, AND THIS IS A DATA-SAFETY RULE RATHER THAN A
     * STRICTNESS PREFERENCE.
     *
     * `HasGeographyCascade::loadGeographyCascade()` hydrates and then, on the very next line, calls
     * `refreshGeographyCascade()` — which runs {@see GeographySelectionResolver}. The resolver
     * justifies a neighbourhood by its SELECTED CITY. So a neighbourhood promoted here whose parent
     * city is not in the selection would be cleared microseconds later — and a CLEARED selection is
     * not preserved, unlike an unmatched label. The label would vanish from the blob on the next
     * save, silently, which is the exact failure this whole namespace is built to prevent.
     *
     * Enumerating from `$cityIds` makes hydration and resolution agree by construction: everything
     * this promotes is, by definition, justified by a city that survived.
     *
     * THE CONSEQUENCE, STATED PLAINLY: a stored `Clearwater Beach, FL` with no `Clearwater, FL`
     * beside it stays PRESERVED rather than becoming a selection. Nothing is lost and nothing is
     * recognised — identical to the behaviour before this tier existed. Selecting the parent city
     * is what makes the neighbourhood resolvable, which is the same rule the editor enforces going
     * forward.
     *
     * @param  list<string>  $cityIds  cities that matched, and therefore justify a neighbourhood
     * @param  list<string>  $labels   the city tier's unmatched labels
     * @return array{0: list<string>, 1: list<string>} matched neighbourhood ids, still-unmatched labels
     */
    private function matchNeighborhoods(array $cityIds, array $labels): array
    {
        // No candidate labels, or no city to hang one from: nothing to do, and no query to issue.
        // The second guard is what keeps the tier free for the callers that never use it.
        if ($labels === [] || $cityIds === []) {
            return [[], $labels];
        }

        $index = [];

        foreach ($this->neighborhoods->neighborhoodsInCities($cityIds) as $option) {
            if ($option->is(GeographyOption::KIND_NEIGHBORHOOD)) {
                $index[$this->key($option->name)] ??= $option->id;
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

    /** Comparison form: lowercased, with internal whitespace collapsed and `Saint` folded to `St`. */
    private function key(string $value): string
    {
        $keyed = trim((string) preg_replace('/\s+/', ' ', mb_strtolower(trim($value))));

        return $this->foldSaintPrefix($keyed);
    }

    /**
     * Fold a leading `Saint` / `St.` / `St` to one comparison form.
     *
     * WHY THIS EXISTS
     * ---------------
     * The two corpora spell the same places differently and neither is wrong. `us_cities` holds 159
     * names beginning `Saint ` and 13 beginning `St. `; `census_places` holds 210 beginning `St. `
     * and NOT ONE beginning `Saint `. So a label stored years ago as `Saint Petersburg, FL` — a real
     * value in this database — matched the reference tables and matches nothing in the Census
     * corpus. It would be preserved rather than dropped, which is safe but wrong: the place is
     * there, spelled the other way.
     *
     * COMPARISON ONLY. This never touches what is stored or displayed. Both the corpus name and the
     * stored label pass through {@see self::key()}, so the fold cancels out: the id that matches is
     * the corpus's own, and {@see GeographyLabelProjector} labels it from the corpus name. A stored
     * `Saint Petersburg, FL` therefore resolves to the St. Petersburg option and is re-emitted in
     * the Census spelling ON THE NEXT SAVE — by the user's action, never by a migration.
     *
     * THE PREFIX ONLY, AND ONLY AS A WHOLE WORD
     * -----------------------------------------
     * `\s+` after the token is what keeps `Sainte Genevieve` and `Stevensville` out: neither has a
     * word boundary where this needs one. Folding anywhere else in the name would equate
     * `Mount Saint Francis` with a `Mount St Francis` that may be a different place, which is the
     * class of guess {@see GeographySelectionHydrator} exists to refuse.
     *
     * `Ste.` / `Sainte` IS DELIBERATELY NOT FOLDED. It is a distinct French feminine form, the
     * corpora do not disagree about it the way they disagree about `Saint`, and folding it would
     * risk equating `Ste. Genevieve` with a `St. Genevieve` that is a different place.
     *
     * VERIFIED NON-LOSSY AGAINST THE LIVE CORPUS. Folding introduces ZERO new collisions: no county
     * within a state, and no place within a county, collapses onto another under this rule. Where a
     * genuine ambiguity already exists the county matcher's `$loose` index still resolves it to null
     * and matches nothing, which this does not change.
     */
    private function foldSaintPrefix(string $keyed): string
    {
        return (string) preg_replace('/^(?:saint|st\.|st)\s+/', 'st ', $keyed);
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
