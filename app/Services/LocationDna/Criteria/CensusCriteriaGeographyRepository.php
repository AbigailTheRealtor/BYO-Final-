<?php

namespace App\Services\LocationDna\Criteria;

use Illuminate\Support\Facades\DB;

/**
 * Phase 1d-2 — {@see CriteriaGeographyRepository} over the authoritative `census_*` corpus.
 *
 * THE SAME QUESTIONS, ASKED OF PUBLISHED DATA
 * -------------------------------------------
 * This is a second implementation of the Phase 1a interface, not a replacement for the first.
 * {@see EloquentCriteriaGeographyRepository} still ships, still serves every surface, and is
 * untouched by this phase. Which one answers is a configuration decision — see
 * `criteria_location_dna.geography_source` — and the default remains `eloquent`.
 *
 * WHAT CHANGES, AND WHAT DELIBERATELY DOES NOT
 * --------------------------------------------
 * Identifiers change shape: a state is `01`, a county `01001`, a place `0100100`. They are GEOIDs
 * rather than surrogate primary keys, and they are strings whose leading zeros are load-bearing.
 * Nothing stored depends on that, because a selection is persisted as LABELS —
 * {@see Projection\GeographyLabelProjector} converts ids to labels before anything is written and
 * {@see Projection\GeographySelectionHydrator} converts them back on read. Repository ids live for
 * one request. That is what makes swapping the source safe.
 *
 * ZIP IDENTIFIERS DO NOT CHANGE AT ALL. Both implementations key a ZIP option by the ZIP itself,
 * so the ZIP tier emits identical ids either way. Only its membership differs.
 *
 * THREE PIECES OF MACHINERY THAT SIMPLY DISAPPEAR
 * -----------------------------------------------
 * The other implementation carries them because the reference tables force it to. Here the data is
 * published, so there is nothing to infer:
 *
 *   1. NO NAME JOIN. `us_zip_codes` has no county key, so ZIPs are matched to counties by comparing
 *      a bare county name against a stripped class suffix — and that join is why 280 counties
 *      resolve no ZIPs at all. `census_zcta_counties` publishes the relationship, so no name is
 *      compared and no suffix list is needed.
 *   2. NO ZIP PADDING. 3,000 reference rows store a ZIP short — `501` for 00501 — and must be
 *      padded on the way out. `census_zctas.zcta5` is a fixed-width char(5) that was validated at
 *      import, so what is stored is already canonical.
 *   3. NO SINGLE-COUNTY ASSUMPTION FOR PLACES. `us_cities.county_id` is one foreign key, so a city
 *      can only belong to one county. `census_place_counties` is a real many-to-many, so a place
 *      that straddles a county line is emitted once per county — the same shape ZIPs already have.
 *
 * IDENTIFIERS ARE VALIDATED BY WIDTH, NOT COERCED
 * -----------------------------------------------
 * The two implementations both use numeric strings, which makes an id from the wrong one look
 * plausible rather than wrong. The other repository coerces with `(int)`, so a county GEOID
 * `01001` handed to it silently becomes id `1001` — a real, different county. This one refuses
 * instead: an id that is not exactly the right number of digits yields an empty list, the same
 * answer the interface already specifies for an unknown id. A four-digit surrogate key cannot be
 * mistaken for a five-digit county GEOID, and a value that lost its leading zero is a different
 * width from one that kept it.
 *
 * READ-ONLY, like everything in this namespace, and portable to the SQLite test connection: every
 * predicate is an exact match or a whereIn, and no reference model's `search()` helper is called.
 */
final class CensusCriteriaGeographyRepository implements CriteriaGeographyRepository
{
    /** Digits in a state GEOID (STATEFP). */
    private const STATE_WIDTH = 2;

    /** Digits in a county GEOID (STATEFP + COUNTYFP). */
    private const COUNTY_WIDTH = 5;

    /** {@inheritDoc} */
    public function states(): array
    {
        return DB::table('census_states')
            ->orderBy('name')
            ->get(['geoid', 'name'])
            ->map(fn (object $row): GeographyOption => GeographyOption::state(
                $this->code($row->geoid),
                $this->text($row->name),
                $this->code($row->geoid),
            ))
            ->all();
    }

    /** {@inheritDoc} */
    public function countiesInState(string $stateId): array
    {
        $state = $this->geoidOfWidth($stateId, self::STATE_WIDTH);

        if ($state === null) {
            return [];
        }

        return DB::table('census_counties')
            ->where('state_geoid', $state)
            ->orderBy('name')
            ->get(['geoid', 'name', 'state_geoid'])
            ->map(fn (object $row): GeographyOption => GeographyOption::county(
                $this->code($row->geoid),
                $this->text($row->name),
                $this->code($row->state_geoid),
                $this->code($row->geoid),
            ))
            ->all();
    }

    /**
     * {@inheritDoc}
     *
     * One option per (place, county) pair. A place spanning two selected counties is emitted twice,
     * once under each — which is what the interface documents for ZIPs and is now equally true of
     * places. Consumers already key on the option id alone, so the repeated id collapses where it
     * should and the association survives the loss of one parent where it should.
     *
     * The county ordering is secondary and exists only to make the multi-parent case deterministic:
     * without it two runs could interleave the parents of a straddling place differently, and a
     * caller comparing enumerations would see a change that is not one.
     */
    public function citiesInCounties(array $countyIds): array
    {
        $counties = $this->geoidsOfWidth($countyIds, self::COUNTY_WIDTH);

        if ($counties === []) {
            return [];
        }

        return DB::table('census_place_counties')
            ->join('census_places', 'census_places.geoid', '=', 'census_place_counties.place_geoid')
            ->whereIn('census_place_counties.county_geoid', $counties)
            ->orderBy('census_places.name')
            ->orderBy('census_place_counties.county_geoid')
            ->get([
                'census_places.geoid as place_geoid',
                'census_places.name as place_name',
                'census_place_counties.county_geoid as county_geoid',
            ])
            ->map(fn (object $row): GeographyOption => GeographyOption::city(
                $this->code($row->place_geoid),
                $this->text($row->place_name),
                $this->code($row->county_geoid),
            ))
            ->all();
    }

    /**
     * {@inheritDoc}
     *
     * ASSOCIATION, and now published rather than inferred. The join to `census_zctas` is not
     * decoration: it restricts the result to the ZCTA roster, so a relationship row whose ZCTA
     * were ever missing would drop out here instead of becoming an option that names nothing.
     */
    public function zipsInCounties(array $countyIds): array
    {
        $counties = $this->geoidsOfWidth($countyIds, self::COUNTY_WIDTH);

        if ($counties === []) {
            return [];
        }

        return DB::table('census_zcta_counties')
            ->join('census_zctas', 'census_zctas.zcta5', '=', 'census_zcta_counties.zcta5')
            ->whereIn('census_zcta_counties.county_geoid', $counties)
            ->orderBy('census_zcta_counties.zcta5')
            ->orderBy('census_zcta_counties.county_geoid')
            ->get([
                'census_zcta_counties.zcta5 as zcta5',
                'census_zcta_counties.county_geoid as county_geoid',
            ])
            ->map(fn (object $row): GeographyOption => GeographyOption::zip(
                $this->code($row->zcta5),
                $this->code($row->county_geoid),
            ))
            ->all();
    }

    // ═════════════════════════════════════════════════════════════════════════
    // IDENTIFIERS
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * A GEOID of exactly this many digits, or null.
     *
     * Null rather than an exception: the interface already specifies an empty list for an unknown
     * id, and a caller handing over the wrong id shape is the same class of caller error. Refusing
     * to widen or narrow the value is the point — padding it here would recreate the coercion this
     * class exists to avoid.
     */
    private function geoidOfWidth(string $value, int $width): ?string
    {
        $value = trim($value);

        return preg_match('/^\d{'.$width.'}$/', $value) === 1 ? $value : null;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>  the well-formed ones, deduplicated, order preserved
     */
    private function geoidsOfWidth(array $values, int $width): array
    {
        $out = [];

        foreach ($values as $value) {
            $geoid = $this->geoidOfWidth((string) $value, $width);

            if ($geoid !== null) {
                $out[$geoid] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * A fixed-width column's value as a string.
     *
     * Trimmed because these columns are `char(n)`, and Postgres pads a bpchar to its declared width
     * on the way out. Every stored value is already exactly n characters, so this trims nothing
     * today; it stops a later widening of a column from silently appending spaces to an identifier.
     */
    private function code(mixed $value): string
    {
        return trim((string) $value);
    }

    private function text(mixed $value): string
    {
        return trim((string) $value);
    }
}
