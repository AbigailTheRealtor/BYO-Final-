<?php

namespace App\Services\LocationDna\Criteria;

use App\Models\UsCity;
use App\Models\UsCounty;
use App\Models\UsState;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1a — {@see CriteriaGeographyRepository} over the existing `us_*` reference tables.
 *
 * NO NEW INFRASTRUCTURE, AND THAT IS THE POINT
 * --------------------------------------------
 * The Phase 1 plan assumed the cascade would need the PostGIS corpus, which is not provisioned.
 * It does not: `us_states` (56), `us_counties` (3,067), `us_cities` (25,830) and `us_zip_codes`
 * (34,741) already carry every tier the approved hierarchy requires. PostGIS is needed for map
 * GEOMETRY in a later phase, not for selection. So this ships against real data today.
 *
 * READ-ONLY. No insert, no update, no delete, no saveMeta. Enforced by the inertness guard.
 *
 * TWO DATA REALITIES THIS CLASS ABSORBS
 * -------------------------------------
 * Both were measured, not assumed, and both would silently corrupt the cascade if ignored.
 *
 * 1. ZIP → COUNTY IS A NAME JOIN, AND THE NAMES DISAGREE.
 *    `us_zip_codes` has no county FK. It carries `county` as a bare name ("Suffolk") plus
 *    `state_abbrev`, while `us_counties.name` carries the full form ("Autauga County"). Joining
 *    them requires stripping the class suffix — and the suffix is not always "County": Louisiana
 *    uses "Parish", Alaska uses "Borough" / "Census Area" / "Municipality", and Virginia has
 *    independent "city" entries. {@see self::COUNTY_SUFFIXES} is that list; a county whose suffix
 *    is not recognised still matches on its full name, so an unknown form degrades to "no ZIPs
 *    for this county" rather than to a wrong county's ZIPs.
 *
 * 2. ZIP CODES ARE NOT ZERO-PADDED IN STORAGE.
 *    3,000 of 34,741 rows are stored short — the sample row is `"501"`, which is ZIP 00501.
 *    Emitting those verbatim would produce options that never match a user's typed ZIP and would
 *    corrupt any downstream canonical `zip_codes` value. Every ZIP is padded to five digits on the
 *    way out.
 *
 * NO `ILIKE`. `UsState/UsCounty/UsCity/UsZipCode::search()` all use `ILIKE`, which is a Postgres
 * operator that SQLite rejects outright — it is already the cause of a pre-existing suite failure
 * elsewhere. Nothing here calls those helpers, and every predicate in this class is an exact match
 * or a whereIn, so the class is portable to the test connection.
 */
final class EloquentCriteriaGeographyRepository implements CriteriaGeographyRepository
{
    /**
     * County-class suffixes stripped when joining `us_counties.name` to `us_zip_codes.county`.
     *
     * Ordered longest-first so "Census Area" is removed before "Area" could be, and so a
     * two-word suffix never leaves a fragment behind.
     *
     * @var list<string>
     */
    private const COUNTY_SUFFIXES = [
        ' City and Borough',
        ' Census Area',
        ' Municipality',
        ' Parish',
        ' Borough',
        ' County',
        ' city',
        ' City',
    ];

    /** {@inheritDoc} */
    public function states(): array
    {
        return UsState::query()
            ->orderBy('name')
            ->get(['id', 'name', 'abbreviation', 'fips_code'])
            ->map(fn (UsState $s): GeographyOption => GeographyOption::state(
                (string) $s->id,
                (string) $s->name,
                $this->nullIfBlank($s->fips_code),
                $this->nullIfBlank($s->abbreviation),
            ))
            ->all();
    }

    /** {@inheritDoc} */
    public function countiesInState(string $stateId): array
    {
        if (! $this->isPositiveInteger($stateId)) {
            return [];
        }

        return UsCounty::query()
            ->where('state_id', (int) $stateId)
            ->orderBy('name')
            ->get(['id', 'name', 'fips_code', 'state_id'])
            ->map(fn (UsCounty $c): GeographyOption => GeographyOption::county(
                (string) $c->id,
                (string) $c->name,
                (string) $c->state_id,
                $this->nullIfBlank($c->fips_code),
            ))
            ->all();
    }

    /** {@inheritDoc} */
    public function citiesInCounties(array $countyIds): array
    {
        $ids = $this->positiveIntegers($countyIds);

        if ($ids === []) {
            return [];
        }

        return UsCity::query()
            ->whereIn('county_id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'county_id'])
            ->map(fn (UsCity $c): GeographyOption => GeographyOption::city(
                (string) $c->id,
                (string) $c->name,
                (string) $c->county_id,
            ))
            ->all();
    }

    /**
     * {@inheritDoc}
     *
     * Resolves each county to (bare name, state abbreviation), matches `us_zip_codes` on that
     * pair, and maps every hit back to the county id that produced it. A ZIP matching two selected
     * counties is emitted twice, once per parent — that is the many-to-many the interface
     * documents, not a duplicate.
     */
    public function zipsInCounties(array $countyIds): array
    {
        $ids = $this->positiveIntegers($countyIds);

        if ($ids === []) {
            return [];
        }

        $counties = UsCounty::query()
            ->with('state:id,abbreviation')
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'state_id']);

        // (bare county name, state abbrev) => county id, for mapping matches back to their parent.
        $index      = [];
        $bareNames  = [];
        $stateAbbrs = [];

        foreach ($counties as $county) {
            $abbrev = (string) ($county->state->abbreviation ?? '');

            if ($abbrev === '') {
                continue;
            }

            $bare = $this->stripCountySuffix((string) $county->name);

            $index[$this->key($bare, $abbrev)] = (string) $county->id;
            $bareNames[]                       = $bare;
            $stateAbbrs[]                      = $abbrev;
        }

        if ($index === []) {
            return [];
        }

        $rows = DB::table('us_zip_codes')
            ->whereIn('county', array_values(array_unique($bareNames)))
            ->whereIn('state_abbrev', array_values(array_unique($stateAbbrs)))
            ->orderBy('zip_code')
            ->get(['zip_code', 'county', 'state_abbrev']);

        $options = [];

        foreach ($rows as $row) {
            $key = $this->key((string) $row->county, (string) $row->state_abbrev);

            // The two whereIn clauses are independent, so a cross-product row can appear —
            // county "Suffolk" from one state paired with another state's abbreviation. Only
            // pairs that were actually asked for survive this lookup.
            if (! isset($index[$key])) {
                continue;
            }

            $options[] = GeographyOption::zip(
                $this->padZip((string) $row->zip_code),
                $index[$key],
            );
        }

        return $options;
    }

    /** Remove the county-class suffix so the name matches `us_zip_codes.county`. */
    private function stripCountySuffix(string $name): string
    {
        foreach (self::COUNTY_SUFFIXES as $suffix) {
            if (str_ends_with($name, $suffix)) {
                return trim(substr($name, 0, -strlen($suffix)));
            }
        }

        return trim($name);
    }

    /** ZIPs are stored unpadded; canonical form is five digits. */
    private function padZip(string $zip): string
    {
        return str_pad(trim($zip), 5, '0', STR_PAD_LEFT);
    }

    private function key(string $countyName, string $stateAbbrev): string
    {
        return strtolower($countyName).'|'.strtoupper($stateAbbrev);
    }

    private function nullIfBlank(mixed $value): ?string
    {
        $value = $value === null ? '' : trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function isPositiveInteger(string $value): bool
    {
        return ctype_digit($value) && (int) $value > 0;
    }

    /**
     * @param  list<string>  $values
     * @return list<int>
     */
    private function positiveIntegers(array $values): array
    {
        $ids = [];

        foreach ($values as $value) {
            $value = (string) $value;

            if ($this->isPositiveInteger($value)) {
                $ids[] = (int) $value;
            }
        }

        return array_values(array_unique($ids));
    }
}
