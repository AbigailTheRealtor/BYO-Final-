<?php

namespace App\Services\LocationDna\Criteria;

use App\Models\LocationPlace;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1d-5 — {@see CriteriaNeighborhoodRepository} over the supplemental `location_places` layer.
 *
 * THE JOIN THAT MAKES THE TWO CORPORA MEET
 * ----------------------------------------
 * A city option issued by {@see CensusCriteriaGeographyRepository} carries the seven-digit place
 * GEOID as its id — `1212875` for Clearwater. `location_places.census_place_geoid` stores exactly
 * that value for every census-sourced row. So a neighbourhood's city is reached by joining a
 * neighbourhood row to its `parent_place_id` and reading the parent's GEOID, and the value that
 * comes back is the same string the cascade already holds in its city tier.
 *
 * That equivalence is the ONLY thing coupling this class to the geography source, and it holds for
 * `census` alone. Under `eloquent` a city option's id is a `us_cities` surrogate key, which would
 * address a different place entirely or none — so this implementation is never bound under that
 * source. See the binding in `AppServiceProvider` and {@see NullCriteriaNeighborhoodRepository}.
 *
 * ONLY PARENTED ROWS ARE ENUMERABLE, AND THAT EXCLUDES MOST OF THE LAYER
 * ----------------------------------------------------------------------
 * `location_places` holds ~7,000 supplemental communities lifted from the USPS corpus, and they
 * have NO parent: the USPS data states that a name exists and roughly where, never that it sits
 * inside a particular municipality. They are deliberately not offered here. A tier whose members
 * are justified by a selected city cannot contain rows that name no city — the cascade would have
 * nothing to clear them against, and the resolver would drop them on the next pass anyway.
 *
 * Curated neighbourhoods DO carry a parent, because a human asserted it. That is the difference
 * between the two, and it is why the curated config exists.
 *
 * IDENTIFIERS ARE VALIDATED BY WIDTH, NOT COERCED — the same refusal
 * {@see CensusCriteriaGeographyRepository} makes. A city id that is not exactly seven digits yields
 * an empty list rather than being padded or cast into one that happens to exist.
 *
 * READ-ONLY. One query, no writes, and nothing in `location_places` is modified.
 */
final class CensusCriteriaNeighborhoodRepository implements CriteriaNeighborhoodRepository
{
    /** Digits in a place GEOID (STATEFP + PLACEFP), which is what a census city option's id is. */
    private const CITY_WIDTH = 7;

    /** {@inheritDoc} */
    public function neighborhoodsInCities(array $cityIds): array
    {
        $cities = $this->geoidsOfWidth($cityIds, self::CITY_WIDTH);

        if ($cities === []) {
            return [];
        }

        return DB::table('location_places as n')
            ->join('location_places as parent', 'parent.id', '=', 'n.parent_place_id')
            ->whereIn('n.type', LocationPlace::SUB_PLACE_TYPES)
            ->where('n.active', true)
            // The parent must still be a real place AND still be active: a neighbourhood whose city
            // has been deactivated is not selectable, because the tier above it is not.
            ->whereIn('parent.type', LocationPlace::PLACE_TYPES)
            ->where('parent.active', true)
            ->whereIn('parent.census_place_geoid', $cities)
            ->orderBy('n.name')
            ->orderBy('n.id')
            ->get([
                'n.id as neighborhood_id',
                'n.name as neighborhood_name',
                'parent.census_place_geoid as city_geoid',
            ])
            ->map(fn (object $row): GeographyOption => GeographyOption::neighborhood(
                (string) $row->neighborhood_id,
                $this->text($row->neighborhood_name),
                $this->code($row->city_geoid),
            ))
            ->all();
    }

    /**
     * @param  list<string>  $values
     * @return list<string>  the well-formed ones, deduplicated, order preserved
     */
    private function geoidsOfWidth(array $values, int $width): array
    {
        $out = [];

        foreach ($values as $value) {
            $value = trim((string) $value);

            if (preg_match('/^\d{'.$width.'}$/', $value) === 1) {
                $out[$value] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * A fixed-width column's value as a string.
     *
     * Trimmed because `census_place_geoid` is `char(7)` and Postgres pads a bpchar to its declared
     * width on the way out. Every stored value is already exactly seven characters, so this trims
     * nothing today; it stops a later widening from silently appending spaces to an identifier that
     * the cascade compares by equality.
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
