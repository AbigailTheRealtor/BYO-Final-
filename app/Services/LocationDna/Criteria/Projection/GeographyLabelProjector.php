<?php

namespace App\Services\LocationDna\Criteria\Projection;

use App\Services\LocationDna\Criteria\GeographyOption;
use App\Services\LocationDna\Criteria\Rules\GeographySelection;
use App\Services\LocationDna\Criteria\Rules\GeographyTier;

/**
 * Phase 1c — turns an id-carried selection back into the label strings the stored blob holds.
 *
 * THE IMPEDANCE MISMATCH THIS CLASS EXISTS TO ABSORB
 * --------------------------------------------------
 * The Phase 1a/1b foundation carries a selection as reference-table IDs, because a selection
 * carried by a display name is what made the legacy criteria data unsafe to convert in the first
 * place. Everything downstream of the editor — the canonical document, the legacy mirrors, the
 * Stellar loaders, the match engine, the external-listing filter builders — reads LABELS.
 *
 * Worse, the stored labels are not the corpus's own names. Historic records say
 * `Pinellas County, FL`; `us_counties.name` says `Pinellas County`. The suffix was added by the
 * editor being replaced, and thousands of stored rows carry it.
 *
 * So this class is the single place where the two vocabularies meet, and the format it emits is
 * load-bearing rather than cosmetic. Emitting the corpus form would leave every historical record
 * unmatched by every consumer, with no error raised anywhere — a silent, total regression that
 * would look like a matching-quality problem months later.
 *
 * PURE, AND HANDED ITS OPTIONS RATHER THAN LOOKING THEM UP
 * --------------------------------------------------------
 * The caller has already enumerated the options to render its dropdowns. Re-enumerating here would
 * mean a second full pass over the cities of a multi-county selection on every save, for nothing.
 * Taking the lists as arguments also makes the class testable with no database at all, which is
 * what lets the byte-format be asserted as behaviour rather than as a fixture.
 *
 * READ-ONLY, like everything in this namespace. It builds an array; it does not persist it. The
 * caller hands the result to the canonical writer, which is the only thing that writes.
 */
final class GeographyLabelProjector
{
    /**
     * Project a selection to the four canonical geography keys.
     *
     * All four keys are ALWAYS present. Presence is what the write path reads as an instruction:
     * a key present with an empty value states "cleared", an absent key states nothing at all. The
     * cascade is a complete editor for all four tiers, so it states all four every time — the same
     * thing the editor it replaces does.
     *
     * PHASE 1d-5 — NEIGHBOURHOODS ARE PROJECTED INTO `cities`, AND THERE IS NO FIFTH KEY.
     *
     * This is the one place the decision is actually executed, so the reasoning belongs here.
     * Six consumers already read the `cities` key — `LocationMatchAuctionExtractor`, three Stellar
     * criteria loaders, `BoundaryLookupService` and `CriteriaListingResolver` — and not one of them
     * would read a `neighborhoods` key. Emitting one would make every selected neighbourhood
     * invisible to matching, with no error raised anywhere and no symptom until match quality was
     * noticed to have dropped months later.
     *
     * Folding them in costs nothing, because the label format is already identical: a neighbourhood
     * emits `Clearwater Beach, FL`, exactly the shape a city emits and exactly what historic records
     * ALREADY carry for it — such a label is sitting in the `cities` array of stored blobs today,
     * preserved-but-unmatched. So this tier does not introduce a new stored value; it lets the
     * corpus recognise one that was always there.
     *
     * ORDER IS SELECTED CITIES, THEN NEIGHBOURHOODS, THEN HISTORY. Fixed rather than incidental, so
     * that a workflow with no neighbourhood selected emits byte-identical output to one compiled
     * before this parameter existed — which is what makes the slice safe to merge while the tier is
     * off.
     *
     * @param  string|null            $stateName            display name of the selected state
     * @param  string|null            $stateAbbreviation    two-letter abbreviation, or null when absent
     * @param  list<GeographyOption>  $countyOptions        counties currently enumerable
     * @param  list<GeographyOption>  $cityOptions          cities currently enumerable
     * @param  list<GeographyOption>  $neighborhoodOptions  neighbourhoods currently enumerable; empty
     *                                                      by default, so every existing six-argument
     *                                                      call keeps its exact behaviour
     * @return array{state: string, counties: list<string>, cities: list<string>, zip_codes: list<string>}
     */
    public function project(
        GeographySelection $selection,
        ?string $stateName,
        ?string $stateAbbreviation,
        array $countyOptions,
        array $cityOptions,
        PreservedGeographyLabels $preserved,
        array $neighborhoodOptions = [],
    ): array {
        return [
            'state'     => $this->projectState($selection, $stateName, $preserved),
            'counties'  => $this->merge(
                $this->label($selection->idsFor(GeographyTier::Counties), $countyOptions, GeographyOption::KIND_COUNTY, $stateAbbreviation),
                $preserved->counties,
            ),
            // Cities and neighbourhoods share one key by design — see the note above. `merge()`
            // deduplicates by label, so a neighbourhood that happens to share a city's name
            // collapses to one entry rather than producing a duplicate the user would see twice.
            'cities'    => $this->merge(
                $this->label($selection->idsFor(GeographyTier::Cities), $cityOptions, GeographyOption::KIND_CITY, $stateAbbreviation),
                $this->label($selection->idsFor(GeographyTier::Neighborhoods), $neighborhoodOptions, GeographyOption::KIND_NEIGHBORHOOD, $stateAbbreviation),
                $preserved->cities,
            ),
            // ZIP codes are already canonical five-digit strings and carry no state suffix —
            // adding one would corrupt every ZIP comparison downstream.
            'zip_codes' => $this->merge($selection->idsFor(GeographyTier::ZipCodes), $preserved->zipCodes),
        ];
    }

    /**
     * The state label.
     *
     * A preserved state fills in ONLY when nothing is selected. It is the label of a state the
     * corpus could not resolve, so the moment the user picks a real one it stops being history and
     * starts being a value they have replaced.
     */
    private function projectState(
        GeographySelection $selection,
        ?string $stateName,
        PreservedGeographyLabels $preserved,
    ): string {
        if ($selection->hasState() && $stateName !== null && trim($stateName) !== '') {
            return trim($stateName);
        }

        return $preserved->state ?? '';
    }

    /**
     * Label every selected id from the enumerated options, in selection order.
     *
     * An id with no matching option is SKIPPED rather than turned into a placeholder. The resolver
     * guarantees a resolved selection contains only justified ids, so reaching this branch means
     * the caller handed over something unresolved — and inventing a label for it would write a
     * value no consumer could match. Skipping is visible in the result; a guess would not be.
     *
     * @param  list<string>           $ids
     * @param  list<GeographyOption>  $options
     * @return list<string>
     */
    private function label(array $ids, array $options, string $kind, ?string $stateAbbreviation): array
    {
        $names = [];

        foreach ($options as $option) {
            if ($option->is($kind)) {
                $names[$option->id] = $option->name;
            }
        }

        $labels = [];

        foreach ($ids as $id) {
            if (! isset($names[$id])) {
                continue;
            }

            $labels[] = $this->suffix($names[$id], $stateAbbreviation);
        }

        return $labels;
    }

    /**
     * `{name}, {ST}` — the format the stored data has always carried.
     *
     * With no abbreviation available the bare name is emitted rather than a dangling comma: a
     * trailing `, ` would be a new third format, matched by nothing and read back by the hydrator
     * as a name with an empty suffix.
     */
    private function suffix(string $name, ?string $stateAbbreviation): string
    {
        $abbreviation = trim((string) $stateAbbreviation);

        return $abbreviation === '' ? $name : $name.', '.strtoupper($abbreviation);
    }

    /**
     * Concatenate label lists in the order given, each label once.
     *
     * Order is fixed rather than incidental so that saving twice without touching anything
     * produces the same bytes both times. A set that reordered itself would make every no-op save
     * look like a change to anything comparing documents.
     *
     * VARIADIC since Phase 1d-5, because the `cities` key now draws from three lists rather than
     * two. The two-argument calls beside it are unchanged in meaning and in output.
     *
     * @param  list<string>  ...$lists  selected labels first, preserved history last
     * @return list<string>
     */
    private function merge(array ...$lists): array
    {
        $out  = [];
        $seen = [];

        foreach (array_merge(...$lists) as $label) {
            $label = (string) $label;

            if ($label === '' || isset($seen[$label])) {
                continue;
            }

            $seen[$label] = true;
            $out[]        = $label;
        }

        return $out;
    }
}
