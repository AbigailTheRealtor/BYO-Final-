<?php

namespace App\Services\LocationDna\Criteria\Search;

use App\Models\LocationPlace;
use App\Services\LocationDna\Criteria\GeographyOption;
use App\Services\LocationDna\Criteria\Rules\GeographyTier;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * M1 — {@see GeographySearchRepository} over Location DNA's canonical place layer.
 *
 * NAMED FOR WHAT IT READS, NOT FOR WHICH SOURCE BINDS IT
 * ------------------------------------------------------
 * `location_places` is the primary source: it holds every settlement the cascade can offer — all
 * 32,188 rows carrying a `census_place_geoid`, which is every row in `census_places`, plus 7,094
 * supplemental sub-places the published corpus cannot contain. The upper tiers (state, county) and
 * the ZIP roster are read from the `census_*` tables because the canonical layer does not hold
 * them; those are supporting reads, not the subject of the class.
 *
 * The container binds it when `criteria_location_dna.geography_source = census`, which is a
 * statement about identifier lineage rather than about which table is queried.
 *
 * WHY NOT `census_places` DIRECTLY (the first cut, and why it was wrong)
 * ----------------------------------------------------------------------
 * `census_places.name` stores names AS PUBLISHED — "St. Petersburg" — so a normalised term cannot
 * match it, and the first version of this class expanded every term into three candidate spellings
 * to compensate. `location_places.name_key` is stored as the MATCH SURFACE, already in the form
 * {@see \App\Services\LocationDna\Places\PlaceNameKey} produces, and is what
 * {@see \App\Services\LocationDna\Places\LocationPlaceResolver} already compares against. Matching
 * the same surface the rest of the layer matches makes the spelling expansion unnecessary and stops
 * search from drifting away from resolution — two components answering "which place is this?" from
 * two different columns is precisely how they come to disagree.
 *
 * It also collapses two query paths into one: cities and neighbourhoods are rows of the same table
 * distinguished by `type`, so they are found together and split on the way out.
 *
 * WHICH TABLE ANSWERS WHICH TIER
 * ------------------------------
 *   state        → `census_states`     — the canonical layer holds no states.
 *   county       → `census_counties`   — the canonical layer holds no counties; it REFERENCES them
 *                                        by GEOID through `location_place_counties`.
 *   city         → `location_places` (PLACE_TYPES)      ┐ one query, split by `type`
 *   neighbourhood→ `location_places` (SUB_PLACE_TYPES)  ┘
 *   ZIP          → `census_zctas`      — see {@see self::searchZips()}. The one tier that
 *                                        deliberately does NOT read the canonical layer, for a
 *                                        measured reason rather than an assumed one.
 *
 * IDENTIFIERS COME FROM THE CENSUS, WHICH IS THE WHOLE CONTRACT
 * --------------------------------------------------------------
 * A city option's id is the seven-digit `census_place_geoid`, NOT `location_places.id` — because
 * that is what {@see \App\Services\LocationDna\Criteria\CensusCriteriaGeographyRepository::citiesInCounties()}
 * issues, and a match that cannot be handed to the cascade is worthless. A canonical-layer row with
 * no `census_place_geoid` therefore cannot be offered as a city. A neighbourhood keeps its
 * surrogate `location_places.id`, matching the neighbourhood tier.
 *
 * READ-ONLY. No write path, no model persistence, no contract-layer reference.
 */
final class LocationPlaceSearchRepository implements GeographySearchRepository
{
    /** Digits in a state GEOID (STATEFP). */
    private const STATE_WIDTH = 2;

    /** Digits in a county GEOID (STATEFP + COUNTYFP). */
    private const COUNTY_WIDTH = 5;

    /**
     * How many candidate rows one tier may contribute before the ranker sees them.
     *
     * Not the caller's limit. The ranker merges every tier and orders across all of them, so each
     * must offer more than the final list can hold or a strong hit in the last tier could be cut
     * before it was ever compared. Generous, because the ceiling is a few hundred rows in PHP.
     */
    private const PER_TIER_FETCH = 50;

    private GeographyMatchRanker $ranker;

    /**
     * @param  bool  $neighborhoodsEnabled whether the supplemental sub-place tier may be searched
     *
     * THE FLAG IS INJECTED, NOT READ HERE — `criteria_location_dna.neighborhood_tier_enabled` gates
     * the neighbourhood TIER of the cascade, and search must agree with it: offering a
     * neighbourhood the cascade has no tier to hold produces a match a user can select and nothing
     * can accept. The decision stays in the binding, beside the identical one made for
     * {@see \App\Services\LocationDna\Criteria\CriteriaNeighborhoodRepository}.
     */
    public function __construct(private readonly bool $neighborhoodsEnabled = false)
    {
        $this->ranker = new GeographyMatchRanker();
    }

    /** {@inheritDoc} */
    public function search(GeographyQuery $query): GeographySearchResult
    {
        if (! $query->isUsable()) {
            return GeographySearchResult::empty();
        }

        $stateScope = $this->resolveStateScope($query);
        $matches    = [];

        if ($query->wantsTier(GeographyTier::State)) {
            $matches = [...$matches, ...$this->searchStates($query)];
        }

        if ($query->wantsTier(GeographyTier::Counties)) {
            $matches = [...$matches, ...$this->searchCounties($query, $stateScope)];
        }

        $matches = [...$matches, ...$this->searchPlaceLayer($query, $stateScope)];

        if ($query->wantsTier(GeographyTier::ZipCodes) && $query->looksLikeZip()) {
            $matches = [...$matches, ...$this->searchZips($query)];
        }

        return $this->ranker->rank($matches, $query);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // THE CANONICAL PLACE LAYER — cities and neighbourhoods, one query
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Cities and neighbourhoods together, split by `type` on the way out.
     *
     * ONE QUERY BECAUSE THEY ARE ONE TABLE. The tiers differ in what they mean, not in where they
     * live, and searching them separately would issue two near-identical statements whose `WHERE`
     * clauses could drift apart. The `type` column is the only thing that distinguishes them.
     *
     * The parent join is a LEFT join with its predicates INSIDE the join rather than in `WHERE`:
     * a city has no parent and must survive, while a neighbourhood whose parent is missing or
     * inactive must not be offered. Moving `parent.active` into `WHERE` would silently drop every
     * city, since a null row fails the comparison.
     *
     * @return list<GeographyMatch>
     */
    private function searchPlaceLayer(GeographyQuery $query, ?string $stateScope): array
    {
        $wantsCities        = $query->wantsTier(GeographyTier::Cities);
        $wantsNeighborhoods = $this->neighborhoodsEnabled && $query->wantsTier(GeographyTier::Neighborhoods);

        if (! $wantsCities && ! $wantsNeighborhoods) {
            return [];
        }

        $types = [
            ...($wantsCities ? LocationPlace::PLACE_TYPES : []),
            ...($wantsNeighborhoods ? LocationPlace::SUB_PLACE_TYPES : []),
        ];

        $term = $query->normalizedTerm();

        $rows = DB::table('location_places as p')
            ->join('census_states as s', 's.geoid', '=', 'p.state_geoid')
            ->leftJoin('location_places as parent', function (JoinClause $join): void {
                $join->on('parent.id', '=', 'p.parent_place_id')
                    ->where('parent.active', true)
                    ->whereIn('parent.type', LocationPlace::PLACE_TYPES);
            })
            ->where('p.active', true)
            ->whereIn('p.type', $types)
            ->when($stateScope !== null, fn (Builder $q): Builder => $q->where('p.state_geoid', $stateScope))
            ->when($query->hasCountyScope(), fn (Builder $q): Builder => $q->whereIn(
                'p.id',
                DB::table('location_place_counties')
                    ->select('location_place_id')
                    ->whereIn('county_geoid', $this->geoidsOfWidth($query->countyIds, self::COUNTY_WIDTH))
            ))
            ->where(fn (Builder $q): Builder => $this->applyNameKeyLike($q, 'p.name_key', $query))
            ->orderByRaw('length(p.name)')
            ->orderBy('p.name')
            ->orderBy('p.id')
            // Two tiers share this statement, so the cap is doubled — otherwise a term matching
            // fifty cities would starve the neighbourhood tier of candidates entirely.
            ->limit(self::PER_TIER_FETCH * 2)
            ->get([
                'p.id as place_id',
                'p.name as name',
                'p.name_key as name_key',
                'p.type as type',
                'p.census_place_geoid as census_place_geoid',
                'parent.census_place_geoid as parent_city_geoid',
                'parent.name as parent_city_name',
                's.usps as usps',
            ]);

        /** @var array<int, array{row: object, type: MatchType}> $cities keyed by location_places.id */
        $cities        = [];
        $neighborhoods = [];

        foreach ($rows as $row) {
            // The MATCH SURFACE is compared, not the display name — they are guaranteed equal by
            // the model, and comparing the stored key avoids re-normalising once per row.
            $matchType = TermMatcher::classifyNormalized($this->text($row->name_key), $term);

            if ($matchType === null) {
                continue;
            }

            if (in_array($row->type, LocationPlace::SUB_PLACE_TYPES, true)) {
                $neighborhoods[] = ['row' => $row, 'type' => $matchType];

                continue;
            }

            $cities[(int) $row->place_id] = ['row' => $row, 'type' => $matchType];
        }

        return [
            ...$this->cityMatches($cities),
            ...$this->neighborhoodMatches($neighborhoods),
        ];
    }

    /**
     * @param  array<int, array{row: object, type: MatchType}>  $cities
     * @return list<GeographyMatch>
     */
    private function cityMatches(array $cities): array
    {
        if ($cities === []) {
            return [];
        }

        $parents = $this->countyParentsOf(array_keys($cities));
        $matches = [];

        foreach ($cities as $placeId => $hit) {
            $geoid = $this->code($hit['row']->census_place_geoid);

            // No census identifier means the cascade has no id to hold — the tier is enumerated
            // from `census_place_counties`, so an option keyed by anything else could never be
            // selected. Supplemental rows reach the user through the neighbourhood tier instead.
            if ($geoid === '') {
                continue;
            }

            $counties = $parents[$placeId] ?? [];

            // A place with no county relationship cannot be reached either: the cascade descends to
            // cities THROUGH counties, so offering it would dead-end on selection.
            if ($counties === []) {
                continue;
            }

            $matches[] = new GeographyMatch(
                GeographyOption::city($geoid, $this->text($hit['row']->name), $counties[0]['geoid']),
                $hit['type'],
                $this->countyBreadcrumb($counties, $this->text($hit['row']->usps)),
                array_column($counties, 'geoid'),
            );
        }

        return $matches;
    }

    /**
     * @param  list<array{row: object, type: MatchType}>  $neighborhoods
     * @return list<GeographyMatch>
     */
    private function neighborhoodMatches(array $neighborhoods): array
    {
        $matches = [];

        foreach ($neighborhoods as $hit) {
            $cityGeoid = $this->code($hit['row']->parent_city_geoid);

            // A neighbourhood is justified by its CITY. No resolvable parent — absent, inactive, or
            // itself unknown to the census — means no tier above it to hang from.
            if ($cityGeoid === '') {
                continue;
            }

            $matches[] = new GeographyMatch(
                GeographyOption::neighborhood(
                    (string) $hit['row']->place_id,
                    $this->text($hit['row']->name),
                    $cityGeoid,
                ),
                $hit['type'],
                trim($this->text($hit['row']->parent_city_name).', '.$this->text($hit['row']->usps), ' ,'),
                [$cityGeoid],
            );
        }

        return $matches;
    }

    /**
     * Every county a canonical place belongs to, keyed by `location_places.id`.
     *
     * ORDERED BY `is_primary` FIRST, which replaces the "lowest GEOID wins" rule the first cut
     * invented. The layer already records which parent is canonical, and the invariant holds
     * exactly: all 39,184 places in the pivot carry precisely one primary, with zero violations.
     * Deriving a canonical parent when the data states one is how two components come to disagree
     * about the same place.
     *
     * @param  list<int>  $placeIds
     * @return array<int, list<array{geoid: string, name: string, usps: string}>>
     */
    private function countyParentsOf(array $placeIds): array
    {
        $rows = DB::table('location_place_counties as lpc')
            ->join('census_counties as c', 'c.geoid', '=', 'lpc.county_geoid')
            ->join('census_states as s', 's.geoid', '=', 'c.state_geoid')
            ->whereIn('lpc.location_place_id', $placeIds)
            ->orderBy('lpc.location_place_id')
            ->orderByDesc('lpc.is_primary')
            ->orderBy('lpc.county_geoid')
            ->get([
                'lpc.location_place_id as place_id',
                'c.geoid as county_geoid',
                'c.name as county_name',
                's.usps as usps',
            ]);

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row->place_id][] = [
                'geoid' => $this->code($row->county_geoid),
                'name'  => $this->text($row->county_name),
                'usps'  => $this->text($row->usps),
            ];
        }

        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // UPPER TIERS — states and counties, which the canonical layer does not hold
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * States, by name or by exact USPS abbreviation.
     *
     * The abbreviation is matched EXACTLY rather than by prefix: two letters prefix-matched would
     * make every two-character term return a fistful of states — "ca" offering California alongside
     * every place beginning "Ca" — and the states would win on tier bonus.
     *
     * @return list<GeographyMatch>
     */
    private function searchStates(GeographyQuery $query): array
    {
        $term = $query->normalizedTerm();
        $raw  = mb_strtolower($query->searchableTerm());

        $rows = DB::table('census_states')
            ->where(fn (Builder $q): Builder => $this->applyPublishedNameLike($q, ['name'], $query))
            ->orWhereRaw('lower(usps) = ?', [$raw])
            ->orderByRaw('length(name)')
            ->orderBy('name')
            ->limit(self::PER_TIER_FETCH)
            ->get(['geoid', 'usps', 'name']);

        $matches = [];

        foreach ($rows as $row) {
            $type = TermMatcher::classify($this->text($row->name), $term);

            // An exact abbreviation matches even when the NAME does not — "FL" never resembles
            // "Florida".
            if ($type === null) {
                if (mb_strtolower($this->text($row->usps)) !== $raw) {
                    continue;
                }
                $type = MatchType::Exact;
            }

            $matches[] = new GeographyMatch(
                GeographyOption::state(
                    $this->code($row->geoid),
                    $this->text($row->name),
                    $this->code($row->geoid),
                    $this->nullIfBlank($row->usps),
                ),
                $type,
            );
        }

        return $matches;
    }

    /**
     * Counties, matched on the published name AND on the bare basename.
     *
     * `census_counties` publishes both — "Autauga County" and "Autauga" — and a user types either.
     * Matching only `name` would rank "Autauga" as a Word match against "Autauga County" instead of
     * the Exact match it plainly is.
     *
     * @return list<GeographyMatch>
     */
    private function searchCounties(GeographyQuery $query, ?string $stateScope): array
    {
        $term = $query->normalizedTerm();

        $rows = DB::table('census_counties as c')
            ->join('census_states as s', 's.geoid', '=', 'c.state_geoid')
            ->when($stateScope !== null, fn (Builder $q): Builder => $q->where('c.state_geoid', $stateScope))
            ->where(fn (Builder $q): Builder => $this->applyPublishedNameLike($q, ['c.name', 'c.basename'], $query))
            ->orderByRaw('length(c.name)')
            ->orderBy('c.name')
            ->limit(self::PER_TIER_FETCH)
            ->get([
                'c.geoid as geoid',
                'c.name as name',
                'c.basename as basename',
                'c.state_geoid as state_geoid',
                's.name as state_name',
            ]);

        $matches = [];

        foreach ($rows as $row) {
            $type = $this->bestOf(
                TermMatcher::classify($this->text($row->name), $term),
                TermMatcher::classify($this->text($row->basename), $term),
            );

            if ($type === null) {
                continue;
            }

            $matches[] = new GeographyMatch(
                GeographyOption::county(
                    $this->code($row->geoid),
                    $this->text($row->name),
                    $this->code($row->state_geoid),
                    $this->code($row->geoid),
                ),
                $type,
                $this->text($row->state_name),
                [$this->code($row->state_geoid)],
            );
        }

        return $matches;
    }

    /**
     * ZIPs, from the ZCTA roster and collapsed across their counties.
     *
     * THIS TIER DELIBERATELY DOES NOT READ `location_place_zips`, AND THE REASON IS MEASURED.
     * The two rosters disagree substantially: 6,798 ZIPs in `location_place_zips` are absent from
     * `census_zctas` (USPS delivery ZIPs that are not ZCTAs — PO boxes and point ZIPs), and 5,848
     * ZCTAs are absent from `location_place_zips`. The cascade's ZIP tier is enumerated from
     * `census_zcta_counties`, so a ZIP offered from the other roster is one the cascade can neither
     * enumerate nor validate — the user would select it and nothing downstream could hold it.
     *
     * Identifier parity with the tier being seeded outranks source uniformity. When
     * `location_place_zips` becomes the cascade's ZIP source too, this moves with it; until then,
     * moving it alone would break the one contract that makes search useful.
     *
     * @return list<GeographyMatch>
     */
    private function searchZips(GeographyQuery $query): array
    {
        $digits = $query->searchableTerm();

        $rows = DB::table('census_zctas')
            ->where('zcta5', 'like', $this->escapeLike($digits).'%')
            ->when($query->hasCountyScope(), fn (Builder $q): Builder => $q->whereIn(
                'census_zctas.zcta5',
                DB::table('census_zcta_counties')
                    ->select('zcta5')
                    ->whereIn('county_geoid', $this->geoidsOfWidth($query->countyIds, self::COUNTY_WIDTH))
            ))
            ->orderBy('zcta5')
            ->limit(self::PER_TIER_FETCH)
            ->get(['zcta5']);

        if ($rows->isEmpty()) {
            return [];
        }

        $zips    = $rows->map(fn (object $r): string => $this->code($r->zcta5))->all();
        $parents = $this->countyParentsOfZips($zips);
        $matches = [];

        foreach ($zips as $zip) {
            $counties = $parents[$zip] ?? [];

            if ($counties === []) {
                continue;
            }

            $matches[] = new GeographyMatch(
                GeographyOption::zip($zip, $counties[0]['geoid']),
                $zip === $digits ? MatchType::Exact : MatchType::Prefix,
                $this->countyBreadcrumb($counties, $counties[0]['usps']),
                array_column($counties, 'geoid'),
            );
        }

        return $matches;
    }

    /**
     * @param  list<string>  $zips
     * @return array<string, list<array{geoid: string, name: string, usps: string}>>
     */
    private function countyParentsOfZips(array $zips): array
    {
        $rows = DB::table('census_zcta_counties as zc')
            ->join('census_counties as c', 'c.geoid', '=', 'zc.county_geoid')
            ->join('census_states as s', 's.geoid', '=', 'c.state_geoid')
            ->whereIn('zc.zcta5', $zips)
            ->orderBy('zc.zcta5')
            ->orderBy('zc.county_geoid')
            ->get([
                'zc.zcta5 as zcta5',
                'c.geoid as county_geoid',
                'c.name as county_name',
                's.usps as usps',
            ]);

        $out = [];

        foreach ($rows as $row) {
            $out[$this->code($row->zcta5)][] = [
                'geoid' => $this->code($row->county_geoid),
                'name'  => $this->text($row->county_name),
                'usps'  => $this->text($row->usps),
            ];
        }

        return $out;
    }

    /**
     * "Pinellas County, FL", or "Hillsborough County +1, FL" when a place straddles a line.
     *
     * Naming only the first parent would be a quiet lie about a place genuinely in two counties;
     * naming all of them makes the line unreadable once there are four. The count is the
     * compromise, and it tells the user there is more to know.
     *
     * @param  list<array{geoid: string, name: string, usps: string}>  $counties
     */
    private function countyBreadcrumb(array $counties, string $usps): string
    {
        $label = $counties[0]['name'];
        $extra = count($counties) - 1;

        if ($extra > 0) {
            $label .= ' +'.$extra;
        }

        $usps = $usps !== '' ? $usps : $counties[0]['usps'];

        return trim($label.', '.$usps, ' ,');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // TERM → SQL
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Restrict to rows whose MATCH SURFACE could match.
     *
     * One term, one form, no expansion — `name_key` is stored in the same normalised form the term
     * is reduced to, so they meet without any spelling gymnastics. This is the entire benefit of
     * reading the canonical layer rather than `census_places.name`.
     */
    private function applyNameKeyLike(Builder $builder, string $column, GeographyQuery $query): Builder
    {
        $escaped = $this->escapeLike($query->normalizedTerm());

        return $builder
            ->whereRaw('lower('.$column.') like ? escape \'\\\'', [$escaped.'%'])
            ->orWhereRaw('lower('.$column.') like ? escape \'\\\'', ['% '.$escaped.'%'])
            ->orWhereRaw('lower('.$column.') like ? escape \'\\\'', ['%-'.$escaped.'%']);
    }

    /**
     * Restrict to rows whose PUBLISHED name could match — the census tiers that have no match
     * surface of their own.
     *
     * `census_states.name` and `census_counties.name` are stored as published, so the normalised
     * term alone can miss them: "St. Louis County" does not begin with the normalised "st louis".
     * Both forms are therefore offered — what the user typed, and its normalised reduction — which
     * is TWO patterns per column rather than the three-way saint expansion the first cut used. The
     * 30 counties published with an abbreviated "St."/"Ste." are the whole reason this exists.
     *
     * A superset filter: {@see TermMatcher} normalises both sides and makes the real decision, so
     * admitting a few extra rows costs nothing but a discarded comparison.
     *
     * @param  list<string>  $columns
     */
    private function applyPublishedNameLike(Builder $builder, array $columns, GeographyQuery $query): Builder
    {
        $forms = array_unique([
            mb_strtolower($query->searchableTerm()),
            $query->normalizedTerm(),
        ]);

        return $builder->where(function (Builder $q) use ($forms, $columns): void {
            foreach ($forms as $form) {
                if ($form === '') {
                    continue;
                }

                $escaped = $this->escapeLike($form);

                foreach ($columns as $column) {
                    $q->orWhereRaw('lower('.$column.') like ? escape \'\\\'', [$escaped.'%']);
                    $q->orWhereRaw('lower('.$column.') like ? escape \'\\\'', ['% '.$escaped.'%']);
                    $q->orWhereRaw('lower('.$column.') like ? escape \'\\\'', ['%-'.$escaped.'%']);
                }
            }
        });
    }

    /**
     * Neutralise LIKE metacharacters in user input.
     *
     * Without this a term of `%` matches every row in the corpus and an `_` silently matches any
     * character. Both are things a user can type by accident, and both turn a search into a scan.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SCOPE & IDENTIFIERS
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The state GEOID this search is confined to, or null for nationwide.
     *
     * An explicit `stateId` wins over a `, FL` typed into the box: the caller's established scope is
     * a fact, where the suffix is an inference from text. An abbreviation naming no state resolves
     * to null and the search runs NATIONWIDE rather than returning nothing — "Springfield, XX"
     * should behave like "Springfield", not like silence.
     */
    private function resolveStateScope(GeographyQuery $query): ?string
    {
        if ($query->stateId !== null) {
            $explicit = $this->geoidOfWidth($query->stateId, self::STATE_WIDTH);

            if ($explicit !== null) {
                return $explicit;
            }
        }

        $hint = $query->stateAbbreviationHint();

        if ($hint === null) {
            return null;
        }

        $row = DB::table('census_states')
            ->whereRaw('lower(usps) = ?', [mb_strtolower($hint)])
            ->first(['geoid']);

        return $row === null ? null : $this->code($row->geoid);
    }

    /** The stronger of two classifications, or null when neither matched. */
    private function bestOf(?MatchType $a, ?MatchType $b): ?MatchType
    {
        if ($a === null) {
            return $b;
        }

        if ($b === null) {
            return $a;
        }

        return $a->weight() >= $b->weight() ? $a : $b;
    }

    private function geoidOfWidth(string $value, int $width): ?string
    {
        $value = trim($value);

        return preg_match('/^\d{'.$width.'}$/', $value) === 1 ? $value : null;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
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

        // strval, because PHP turns a numeric array key into an integer and a GEOID is a numeric
        // string whose leading zeros are load-bearing.
        return array_map('strval', array_keys($out));
    }

    /**
     * A fixed-width column's value as a string.
     *
     * Trimmed because these are `char(n)` columns and Postgres pads a bpchar to its declared width
     * on the way out. Every stored value is already the right width, so this trims nothing today;
     * it stops a later widening from appending spaces to an identifier compared by equality.
     */
    private function code(mixed $value): string
    {
        return trim((string) $value);
    }

    private function text(mixed $value): string
    {
        return trim((string) $value);
    }

    private function nullIfBlank(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
