<?php

namespace App\Services\Location\Coordinates\Adapters;

use App\Services\Location\Coordinates\CoordinatePrecision;
use App\Services\Location\Coordinates\CoordinateProvenance;
use App\Services\Location\Coordinates\CoordinateProviderAdapterInterface;
use App\Services\Location\Coordinates\CoordinateSource;
use App\Services\Location\Coordinates\PropertyAddress;
use App\Services\Location\Coordinates\PropertyCoordinateResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Rung 3 of the ladder: our own imported address-point corpus.
 *
 * Reads `pgsql_spatial.addresses` — published address points loaded ahead of
 * time by an operator-run import. Local by construction: one indexed SELECT
 * against a table we host, no HTTP client, no network. The import reaches the
 * network; a resolution never does.
 *
 * FLAG-INERT, AND WHY THAT IS NOT A FORMALITY
 * -------------------------------------------
 * `address_point_corpus.enabled` defaults false and `corpus_version` defaults
 * null, and either alone keeps this rung silent. The corpus holds zero rows
 * today and no importer exists, so an enabled rung would issue a query that
 * cannot answer on every resolution — costing a round trip to produce
 * `address_point_not_found` every time. Off is the correct state until an
 * import has been loaded and verified, not a placeholder for one.
 *
 * The rung is nonetheless placed on the ladder unconditionally and gates itself
 * per resolution, for the same reason {@see CensusGeocoderAdapter} does: a
 * ladder that omitted a rung when a flag was off would make the flag's effect
 * depend on when the ladder was built.
 *
 * WHICH ROWS IT READS, AND WHY THE VERSION IS PINNED
 * --------------------------------------------------
 * `corpus_version` is read from config, not from the ledger's `active` row. Two
 * corpus versions coexisting is the design — it is what lets a new import be
 * verified before it is trusted — and a rung that followed activation would
 * start serving different coordinates the moment a ledger row flipped, with no
 * deploy and no diff to point at afterwards.
 *
 * IDENTITY: THE CANONICAL LOOKUP LINE, AND NOTHING FUZZIER
 * --------------------------------------------------------
 * Rows are matched on `normalized` = {@see PropertyAddress::coordinateLookupLine()},
 * by equality. That is the same string every other rung and every cache key in
 * this namespace is keyed on, so "123 North Main Street" and "123 N Main St"
 * converge here exactly as they do everywhere else — one normalization, defined
 * in one place. An importer's obligation is to write that same string into
 * `normalized`; matching would otherwise depend on two implementations of a
 * normalizer agreeing forever, which they do not.
 *
 * The `addresses_trgm` GIN index exists for typeahead, and this rung
 * deliberately does not use it. Trigram similarity is how a lookup returns the
 * *nearest* address rather than *this* address, and a near-miss here is another
 * property's coordinate reported as complete success.
 *
 * AMBIGUITY IS ANSWERED WITH NOTHING
 * ----------------------------------
 * When matched rows disagree about where the address is, the rung returns
 * unresolved and lets the next rung try — the same stance
 * {@see BridgeMlsCoordinatesAdapter} takes toward address similarity: an adapter
 * that cannot prove which record it is looking at returns nothing.
 *
 * The visible consequence is condos. A corpus with a row per unit will hold
 * several points under one unit-free lookup line, and unless the caller's unit
 * matches one of them exactly, this rung declines and the address falls through
 * to the geocoder. That is the intended trade: a slower correct answer beats
 * picking one unit's point and calling it the building's.
 *
 * PRECISION COMES FROM THE CORPUS, CAPPED FOR UNIT LOOKUPS
 * --------------------------------------------------------
 * The corpus states its own `precision` per row; it is read back through
 * {@see CoordinateProvenance::precisionFrom()}, so an unrecognised value reads
 * as Unknown — coarse — under the one rule this codebase has for that.
 *
 * See {@see self::precisionForLookup()} for the unit rule and why
 * {@see PropertyCoordinateResult::forBuilding()} is not used to express it.
 */
final class AddressPointCoordinateAdapter implements CoordinateProviderAdapterInterface
{
    public function providerId(): string
    {
        return 'address_point';
    }

    public function source(): CoordinateSource
    {
        return CoordinateSource::AddressPoint;
    }

    public function requiresNetwork(): bool
    {
        return false;
    }

    /**
     * Flag on, a corpus version pinned, and a corpus that is actually there.
     *
     * Every check is local and cheap, and the whole thing is wrapped: an
     * unconfigured or unreachable spatial connection makes this rung
     * unavailable, never an exception inside a listing save.
     */
    public function isAvailable(): bool
    {
        if (! $this->enabled() || $this->corpusVersion() === null) {
            return false;
        }

        try {
            $schema = Schema::connection($this->connection());

            // The corpus table plus the column that scopes a read to one import.
            // Without `corpus_version` the versioning migration has not run, and
            // reading anyway would mean serving rows from an unknown import.
            return $schema->hasTable('addresses')
                && $schema->hasColumn('addresses', 'corpus_version');
        } catch (Throwable) {
            return false;
        }
    }

    public function resolve(PropertyAddress $address): PropertyCoordinateResult
    {
        $normalized = $address->coordinateLookupLine();

        // Defensive: the resolver checks isAvailable() first, but this rung must
        // also be safe to call directly — from a probe, a test, or a future
        // caller that assembles its own ladder.
        if (! $this->enabled()) {
            return PropertyCoordinateResult::unresolved('address_point_corpus_disabled', $normalized);
        }

        $version = $this->corpusVersion();

        if ($version === null) {
            return PropertyCoordinateResult::unresolved('address_point_corpus_not_pinned', $normalized);
        }

        if ($normalized === '') {
            return PropertyCoordinateResult::unresolved('insufficient_address', null);
        }

        $rows = $this->matchingRows($version, $normalized);

        if ($rows === []) {
            return PropertyCoordinateResult::unresolved('address_point_not_found', $normalized);
        }

        // A unit lookup that finds its exact unit in the corpus located the unit,
        // not merely the building — so it keeps the corpus precision. Narrowing
        // to those rows first is also what keeps a per-unit corpus from reading
        // as ambiguous for every condo in it.
        $locatedUnit = false;

        if ($address->hasUnit()) {
            $unitRows = $this->rowsMatchingUnit($rows, $address->propertyIdentityLine());

            if ($unitRows !== []) {
                $rows        = $unitRows;
                $locatedUnit = true;
            }
        }

        $points = $this->distinctValidPoints($rows);

        if ($points === []) {
            return PropertyCoordinateResult::unresolved('address_point_coordinates_invalid', $normalized);
        }

        if (count($points) > 1) {
            // Matched rows disagree about where this address is. See AMBIGUITY
            // in the class docblock.
            return PropertyCoordinateResult::unresolved('address_point_ambiguous', $normalized);
        }

        $point = $points[array_key_first($points)];

        return PropertyCoordinateResult::resolved(
            latitude:  $point['lat'],
            longitude: $point['lng'],
            precision: self::precisionForLookup(
                CoordinateProvenance::precisionFrom($point['precision']),
                lookupDroppedUnit: $address->hasUnit() && ! $locatedUnit,
            ),
            source:    CoordinateSource::AddressPoint,
            provider:  $this->providerId(),
            normalizedAddress: $normalized,
            // The corpus reports no per-row confidence. Null is the honest
            // value; a fabricated 1.0 would read as certainty nobody asserted.
            confidence: null,
            sourceRef: $point['source_ref'],
        );
    }

    /**
     * The precision a corpus row may be recorded at for this lookup.
     *
     * When the caller asked about a unit and we matched only the building's
     * address line, the point locates the building. `Rooftop` is the one tier
     * that would then be a claim about the specific unit's structure, so it is
     * demoted to `Parcel` — the rule {@see PropertyAddress} states.
     *
     * WHY NOT PropertyCoordinateResult::forBuilding()
     * -----------------------------------------------
     * That helper *sets* precision to Parcel rather than capping it, which is
     * right for a geocoder that reports no tier and wrong here: a corpus row
     * that says `street` would be silently promoted from coarse to exact and
     * become eligible for flood-boundary work it has no business driving. A cap
     * may only ever lower a tier, so this does the demotion explicitly and
     * leaves every other tier exactly as the corpus stated it.
     */
    public static function precisionForLookup(
        CoordinatePrecision $corpusPrecision,
        bool $lookupDroppedUnit,
    ): CoordinatePrecision {
        if ($lookupDroppedUnit && $corpusPrecision === CoordinatePrecision::Rooftop) {
            return CoordinatePrecision::Parcel;
        }

        return $corpusPrecision;
    }

    // ── reads ───────────────────────────────────────────────────────────────

    /**
     * Corpus rows whose normalized line equals this address's, within the pinned
     * corpus version. Exact equality — see IDENTITY in the class docblock.
     *
     * @return list<object>
     */
    private function matchingRows(string $version, string $normalized): array
    {
        return DB::connection($this->connection())
            ->table('addresses')
            // geography(Point,4326) — cast to geometry so ST_X/ST_Y return the
            // stored lon/lat rather than a great-circle interpretation.
            ->selectRaw('ST_Y(geom::geometry) as lat, ST_X(geom::geometry) as lng, unit, city, state, postcode, number, street, precision, source_ref')
            ->where('corpus_version', $version)
            ->where('normalized', $normalized)
            ->limit($this->maxMatches())
            ->get()
            ->all();
    }

    /**
     * The rows whose full property identity — unit included — equals the
     * caller's.
     *
     * Compared by rebuilding each row's identity line through
     * {@see PropertyAddress}, so "Unit 4A", "#4a" and "Apt. 4-A" agree here for
     * exactly the same reason they agree everywhere else in this namespace.
     *
     * @param  list<object> $rows
     * @return list<object>
     */
    private function rowsMatchingUnit(array $rows, string $identityLine): array
    {
        $matched = [];

        foreach ($rows as $row) {
            $rowIdentity = (new PropertyAddress(
                address:     trim(((string) ($row->number ?? '')) . ' ' . ((string) ($row->street ?? ''))),
                unitAddress: (string) ($row->unit ?? ''),
                city:        (string) ($row->city ?? ''),
                state:       (string) ($row->state ?? ''),
                zip:         (string) ($row->postcode ?? ''),
            ))->propertyIdentityLine();

            if ($rowIdentity === $identityLine) {
                $matched[] = $row;
            }
        }

        return $matched;
    }

    /**
     * Distinct valid points among the matched rows, keyed by rounded
     * coordinate so rows that agree collapse to one entry.
     *
     * Rounded to 6 decimal places — roughly 0.1 m, far below any distinction a
     * property coordinate expresses. Two rows differing beyond that are two
     * different opinions about the address, not float noise.
     *
     * @param  list<object> $rows
     * @return array<string, array{lat: float, lng: float, precision: string|null}>
     */
    private function distinctValidPoints(array $rows): array
    {
        $points = [];

        foreach ($rows as $row) {
            $lat = CoordinateValidator::toFloat($row->lat ?? null);
            $lng = CoordinateValidator::toFloat($row->lng ?? null);

            if ($lat === null || $lng === null || ! CoordinateValidator::isValidPair($lat, $lng)) {
                continue;
            }

            $key = number_format($lat, 6, '.', '') . ',' . number_format($lng, 6, '.', '');

            $points[$key] ??= [
                'lat'       => $lat,
                'lng'       => $lng,
                'precision' => isset($row->precision) ? (string) $row->precision : null,
                // The corpus row's stable upstream id — a NENA SITEADDID, a NAD
                // UUID, whatever the jurisdiction publishes. Carried so a stored
                // coordinate can be traced back to the exact import row that
                // produced it. Kept opaque: this rung does not know or care what
                // any source's identifiers look like.
                'source_ref' => isset($row->source_ref) && (string) $row->source_ref !== ''
                    ? (string) $row->source_ref
                    : null,
            ];
        }

        return $points;
    }

    // ── config ──────────────────────────────────────────────────────────────

    private function enabled(): bool
    {
        return (bool) config('address_point_corpus.enabled', false);
    }

    private function corpusVersion(): ?string
    {
        $version = config('address_point_corpus.corpus_version');

        if (! is_string($version) || trim($version) === '') {
            return null;
        }

        return trim($version);
    }

    private function connection(): string
    {
        return (string) config('address_point_corpus.connection', 'pgsql_spatial');
    }

    private function maxMatches(): int
    {
        $max = (int) config('address_point_corpus.max_matches', 25);

        // A zero or negative ceiling would silence the rung while looking like a
        // tuning value. Fall back rather than serve nothing for a typo.
        return $max > 0 ? $max : 25;
    }
}
