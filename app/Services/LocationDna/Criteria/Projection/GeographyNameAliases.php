<?php

namespace App\Services\LocationDna\Criteria\Projection;

/**
 * Phase 1d-3 — the explicit alias rung, for names no rule can derive.
 *
 * WHERE NORMALISATION RUNS OUT
 * ----------------------------
 * {@see GeographyNameCompatibility} closes the gap between two spellings of one name: an accent, a
 * class word, a space, an abbreviation. Every one of those is a transformation you can write down
 * and apply in both directions, which is what makes it safe to apply to stored data in bulk.
 *
 * Some stored labels are not a spelling of the corpus name at all. `North Hollywood` and
 * `Sherman Oaks` are districts of Los Angeles; the Census publishes the place, not the
 * neighbourhood, so the corpus contains `Los Angeles` and nothing that reduces to either of them.
 * `Clearwater Beach` is part of Clearwater in the same way. No normalisation rule reaches these,
 * and any rule that did would reach a hundred wrong things on the way.
 *
 * SO THEY ARE WRITTEN DOWN, ONE AT A TIME
 * ---------------------------------------
 * That is the entire design. An alias is a human decision recorded in source, reviewable in a diff,
 * and true of exactly the one name it names. There is no inference here — no substring match, no
 * "contains", no neighbourhood gazetteer consulted at runtime, nothing that could grow an entry
 * nobody approved. Adding a name to this table is a code change, which is the point: the moment
 * aliases can appear without review, a stored label starts resolving to a place the user never
 * chose.
 *
 * THE TABLE IS DELIBERATELY SHORT, AND CITY-ONLY
 * ----------------------------------------------
 * It holds the three names this phase was asked to carry and nothing speculative. It is city-tier
 * only because the county tier does not need it: every county-name divergence found so far —
 * `Bayamón`/`Bayamon`, `LaSalle`/`La Salle`, `Adjuntas County`/`Adjuntas Municipio` — is derivable,
 * and a derivable name belongs in the normaliser where one rule covers a thousand cases. If a
 * county ever turns up that genuinely is not derivable, it gets its own table here; inventing that
 * table before there is an entry for it would be inventing the decision too.
 *
 * IT CANNOT OVERRIDE THE CORPUS
 * -----------------------------
 * The alias rung runs LAST — after the exact match and after normalisation — so a name that really
 * is in the corpus is answered by the corpus. An alias only ever speaks for a label that would
 * otherwise have been preserved unmatched, and its target is then resolved through the same
 * compatibility index as everything else. If the target is absent from the enumerated counties, or
 * is itself ambiguous there, the alias resolves to nothing and the stored label is preserved
 * verbatim — the alias is a redirection, never a promise that the destination exists.
 *
 * READ-ONLY, like everything in this namespace.
 */
final class GeographyNameAliases
{
    /**
     * Stored city label ⇒ the corpus name it stands for.
     *
     * Written in display form rather than in normalised form so the table reads as the decision it
     * is. Both sides are reduced through {@see GeographyNameCompatibility} before either is used,
     * so an entry keeps working if the corpus changes how it punctuates the target.
     *
     * @var array<string, string>
     */
    private const CITIES = [
        'North Hollywood'  => 'Los Angeles',
        'Sherman Oaks'     => 'Los Angeles',
        'Clearwater Beach' => 'Clearwater',
    ];

    /**
     * The table above, both sides reduced to compatibility keys. Built once per instance.
     *
     * @var array<string, string>|null
     */
    private ?array $cities = null;

    public function __construct(
        private readonly GeographyNameCompatibility $compatibility,
    ) {
    }

    /**
     * The compatibility key of the corpus city this stored label stands for, or null.
     *
     * Takes a key rather than a raw label so the caller cannot normalise one side with a different
     * rule than the index it is about to search — the alias target is only useful if it is looked up
     * in exactly the form the corpus was registered under.
     */
    public function city(string $compatibilityKey): ?string
    {
        return $this->cities()[$compatibilityKey] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private function cities(): array
    {
        if ($this->cities !== null) {
            return $this->cities;
        }

        $out = [];

        foreach (self::CITIES as $stored => $corpus) {
            $from = $this->compatibility->placeKey($stored);
            $to   = $this->compatibility->placeKey($corpus);

            // A self-referential entry is a no-op the normaliser already handles, and keeping it
            // would suggest the alias is doing work it is not.
            if ($from !== '' && $to !== '' && $from !== $to) {
                $out[$from] = $to;
            }
        }

        return $this->cities = $out;
    }
}
