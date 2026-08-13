<?php

namespace App\Services\LocationDna\Places;

use App\Models\LocationPlace;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1d-3 — answers "what is this place?" across the Census corpus AND the supplemental layer.
 *
 * THE QUESTION THE CASCADE COULD NOT ANSWER
 * -----------------------------------------
 * Before this layer, `Clearwater Beach` was indistinguishable from a typo. The hydrator did the
 * right thing with it — preserved it verbatim rather than dropping it, so nothing was ever lost —
 * but "preserved" is not "understood". Nothing could say that it is a neighbourhood, that it sits
 * inside the City of Clearwater, that it is in Pinellas County, or that it means ZIP 33767.
 *
 * This class says all four. It is the read side of the layer and it is READ-ONLY, like everything
 * in the geography namespace: it resolves, it does not persist, and no caller's save path depends
 * on it having found anything.
 *
 * IT NEVER INVENTS A MATCH
 * ------------------------
 * A name that resolves to nothing returns null, and the caller's existing behaviour — preserve
 * the label verbatim — is correct and unchanged. That is the whole reason this can be added to a
 * working system safely: it can only ever turn an unrecognised value into a recognised one, never
 * the reverse. Adding it cannot break a record that works today.
 *
 * AMBIGUITY IS REPORTED, NOT SILENTLY PICKED
 * ------------------------------------------
 * `resolve()` narrows by state and county when the caller knows them, because the corpus contains
 * 423 county names and 202 same-state place names that recur. When a name still matches more than
 * one row after narrowing, {@see PlaceMatch::$ambiguous} says so and the alternatives are carried
 * on the match. A surface can then ask the user rather than guess — which is what the Phase 1d-1
 * audit flagged as the remaining risk in the label-based storage format.
 */
final class LocationPlaceResolver
{
    /**
     * Resolve a place name to the layer's best answer.
     *
     * @param  string       $name         a bare name or a stored `Name, ST` label
     * @param  string|null  $stateGeoid   narrow to this state when known
     * @param  list<string> $countyGeoids narrow to these counties when known
     */
    public function resolve(string $name, ?string $stateGeoid = null, array $countyGeoids = []): ?PlaceMatch
    {
        $bare = PlaceNameKey::stripStateSuffix($name);
        $key  = PlaceNameKey::of($bare);

        if ($key === '') {
            return null;
        }

        $query = LocationPlace::query()->active()->where('name_key', $key);

        if ($stateGeoid !== null && preg_match('/^\d{2}$/', $stateGeoid) === 1) {
            $query->where('state_geoid', $stateGeoid);
        }

        $countyGeoids = array_values(array_filter(
            $countyGeoids,
            fn ($id): bool => preg_match('/^\d{5}$/', (string) $id) === 1
        ));

        // Through the pivot, so a place straddling a county line is found under EITHER parent.
        // Narrowing on the scalar `county_geoid` would have hidden New York city from a Staten
        // Island search — the place is real, the county is right, and the answer was still nothing.
        if ($countyGeoids !== []) {
            $query->inCounties($countyGeoids);
        }

        // Places before sub-places: when a name is both a municipality and a neighbourhood
        // elsewhere in the state, the municipality is the answer a user almost certainly meant.
        //
        // Then census, then CURATED, then supplemental. Curated outranks supplemental because it
        // is a deliberate human statement about one place, while a supplemental row is a name
        // lifted in bulk from the USPS corpus with no parent and no type beyond "community". If
        // the two ever describe the same place the specific one has to win — otherwise curating a
        // neighbourhood would have no visible effect. Ordering is explicit so this is a decision
        // rather than whatever the database happened to return first.
        $matches = $query
            ->orderByRaw("case when type in ('city','town','village','borough','cdp') then 0 else 1 end")
            ->orderByRaw("case source when 'census' then 0 when 'curated' then 1 else 2 end")
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        if ($matches->isEmpty()) {
            return null;
        }

        $best = $matches->first();

        return new PlaceMatch(
            place:      $best,
            parent:     $best->parent_place_id !== null ? LocationPlace::find($best->parent_place_id) : null,
            county:     $this->county($best->county_geoid),
            counties:   $this->counties($best),
            state:      $this->state($best->state_geoid),
            zips:       $this->zips($best->id),
            ambiguous:  $matches->count() > 1,
            candidates: $matches->all(),
        );
    }

    /**
     * Everything selectable beneath a place — the hierarchy tier this layer adds.
     *
     * @return list<LocationPlace>
     */
    public function subPlacesOf(int $placeId): array
    {
        return LocationPlace::query()
            ->active()
            ->subPlaces()
            ->where('parent_place_id', $placeId)
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * Sub-places for a set of counties, for a cascade tier that has counties but no place yet.
     *
     * @param  list<string>  $countyGeoids
     * @return list<LocationPlace>
     */
    public function subPlacesInCounties(array $countyGeoids): array
    {
        $countyGeoids = array_values(array_filter(
            $countyGeoids,
            fn ($id): bool => preg_match('/^\d{5}$/', (string) $id) === 1
        ));

        if ($countyGeoids === []) {
            return [];
        }

        return LocationPlace::query()
            ->active()
            ->subPlaces()
            ->inCounties($countyGeoids)
            ->orderBy('name')
            ->get()
            ->all();
    }

    /** @return list<string> ZIPs associated with a place, residential (ZCTA) ones first. */
    public function zips(int $placeId): array
    {
        return DB::table('location_place_zips')
            ->where('location_place_id', $placeId)
            ->orderByDesc('is_zcta')
            ->orderBy('zip')
            ->pluck('zip')
            ->map(fn ($z): string => trim((string) $z))
            ->all();
    }

    /**
     * Every county the place belongs to, primary first.
     *
     * A place straddling a county line genuinely IS in both, and a surface that shows only one is
     * telling a user something false about where the place is. Returned in full so the caller can
     * render "Kansas City — Jackson, Clay, Platte and Cass Counties" rather than pick one.
     *
     * @return list<array{geoid: string, name: string, primary: bool}>
     */
    private function counties(LocationPlace $place): array
    {
        return DB::table('location_place_counties as lc')
            ->leftJoin('census_counties as c', 'c.geoid', '=', 'lc.county_geoid')
            ->where('lc.location_place_id', $place->id)
            ->orderByDesc('lc.is_primary')
            ->orderBy('lc.county_geoid')
            ->get(['lc.county_geoid', 'lc.is_primary', 'c.name'])
            ->map(fn (object $row): array => [
                'geoid'   => trim((string) $row->county_geoid),
                // A pivot row whose county is missing from the corpus names nothing, so the GEOID
                // stands in rather than a blank — visible, and traceable to the row that caused it.
                'name'    => trim((string) ($row->name ?? '')) ?: trim((string) $row->county_geoid),
                'primary' => (bool) $row->is_primary,
            ])
            ->all();
    }

    /** @return array{geoid: string, name: string}|null */
    private function county(?string $countyGeoid): ?array
    {
        if ($countyGeoid === null) {
            return null;
        }

        $row = DB::table('census_counties')->where('geoid', $countyGeoid)->first(['geoid', 'name']);

        return $row === null ? null : ['geoid' => trim($row->geoid), 'name' => trim($row->name)];
    }

    /** @return array{geoid: string, name: string, usps: string}|null */
    private function state(string $stateGeoid): ?array
    {
        $row = DB::table('census_states')->where('geoid', $stateGeoid)->first(['geoid', 'name', 'usps']);

        return $row === null
            ? null
            : ['geoid' => trim($row->geoid), 'name' => trim($row->name), 'usps' => trim($row->usps)];
    }
}
