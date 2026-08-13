<?php

namespace App\Services\Location\AddressCorpus\Ng911;

use App\Services\Location\AddressCorpus\Contracts\AddressRowNormalizer;
use App\Services\Location\AddressCorpus\CorpusAddressRecord;
use App\Services\Location\AddressCorpus\CorpusRejectReason;
use App\Services\Location\AddressCorpus\GeoJsonSourceReader;
use App\Services\Location\AddressCorpus\StateBounds;
use App\Services\Location\AddressCorpus\StateFips;
use App\Services\Location\Coordinates\Adapters\CoordinateValidator;
use App\Services\Location\Coordinates\PropertyAddress;

/**
 * One NENA NG9-1-1 address-point row → a {@see CorpusAddressRecord}, or a reject.
 *
 * ONE NORMALIZER, MANY JURISDICTIONS
 * ----------------------------------
 * Every state's 911 authority publishes the NENA NG9-1-1 GIS Data Model, so the
 * *semantics* of an address point are national even where the spelling is local.
 * This class holds the semantics; a {@see Ng911ColumnMap} holds the spelling.
 * Adding a county is expected to mean adding a map, and the design is only worth
 * anything if that stays true — so nothing here may name a county.
 *
 * Pure: no database, no container, no clock, no filesystem, no network.
 *
 * THE FOUR THINGS A 911 LAYER GETS WRONG FOR OUR PURPOSE
 * ------------------------------------------------------
 * A dispatch layer is not a property index, and four differences matter:
 *
 *  1. It keeps retired addresses. They are correct history and a wrong answer to
 *     "where is this property", so `activeStatusValues` filters them out.
 *  2. It addresses infrastructure — lift stations, cell towers, dumpsters. Those
 *     resolve to real coordinates that are not properties, which no coordinate
 *     check could ever catch. `nonAddressPlacements` filters the values the
 *     audit actually observed and nothing else.
 *  3. It says almost nothing about *placement*. Neither audited county documents
 *     whether a point is a roof, a parcel centre or a driveway, so precision
 *     comes from the map's conservative default and never from the geometry.
 *  4. It may omit state and county entirely, because within one county both are
 *     obvious. Those arrive as configured constants and are recorded as injected.
 *
 * COORDINATES ARRIVE ALREADY VALIDATED AS DEGREES
 * -----------------------------------------------
 * {@see GeoJsonSourceReader} refuses a non-WGS84 envelope and refuses projected
 * coordinates, so by the time a row reaches here `__lat`/`__lng` are degrees or
 * the run has already stopped. This class still range-checks them, because the
 * reader guarantees the *container* and this guards the *value*.
 */
final class Ng911RowNormalizer implements AddressRowNormalizer
{
    public function __construct(private readonly Ng911ColumnMap $map)
    {
    }

    public function source(): string
    {
        return $this->map->source;
    }

    public function columnMap(): Ng911ColumnMap
    {
        return $this->map;
    }

    /**
     * Whether the row belongs to the requested jurisdiction.
     *
     * A source with a state column is asked; a single-county source without one
     * is answered from its configured constant. Either way a Florida map can
     * never satisfy a request for Georgia — the county file cannot smuggle rows
     * into a jurisdiction it does not cover.
     *
     * @param array<string, mixed> $row
     */
    public function matchesState(array $row, string $stateFips): bool
    {
        $wanted = StateFips::toUsps($stateFips);

        if ($wanted === null) {
            return false;
        }

        // A map declares the jurisdiction it describes. If the operator asked for
        // a different one, no row in this file qualifies, whatever it says.
        if (StateFips::normalizeFips($this->map->stateFips) !== StateFips::normalizeFips($stateFips)) {
            return false;
        }

        $stated = $this->column($row, $this->map->stateColumn);

        if ($stated !== '') {
            return strtoupper($stated) === $wanted;
        }

        // No state column: the configured constant answers, and it must agree
        // with the map's own FIPS or the configuration contradicts itself.
        $constant = strtoupper(trim((string) $this->map->stateConstant));

        return $constant === '' ? true : $constant === $wanted;
    }

    /**
     * @param  array<string, mixed> $row
     * @return array{record: CorpusAddressRecord|null, reject: string|null}
     */
    public function normalize(array $row, string $stateFips): array
    {
        $sourceRef = $this->column($row, $this->map->sourceRefColumn);

        if ($sourceRef === '') {
            $sourceRef = $this->column($row, $this->map->fallbackSourceRefColumn);
        }

        if ($sourceRef === '') {
            return $this->reject(CorpusRejectReason::MISSING_SOURCE_REF);
        }

        $status = $this->column($row, $this->map->statusColumn);

        if (! $this->map->isActiveStatus($status)) {
            return $this->reject(CorpusRejectReason::INACTIVE_STATUS);
        }

        $placement = $this->column($row, $this->map->placementColumn);

        if ($this->map->isNonAddressFeature($placement)) {
            return $this->reject(CorpusRejectReason::NON_ADDRESS_FEATURE);
        }

        $number = $this->column($row, $this->map->numberColumn);

        if ($number === '') {
            return $this->reject(CorpusRejectReason::MISSING_NUMBER);
        }

        $street = $this->column($row, $this->map->streetColumn);

        if ($street === '') {
            return $this->reject(CorpusRejectReason::MISSING_STREET);
        }

        [$unit, $unitSource] = $this->resolveUnit($row);
        [$city, $localitySource] = $this->resolveCity($row);

        [$state, $stateProvenance]   = $this->resolveState($row);
        [$county, $countyProvenance] = $this->resolveCounty($row);

        $rawLat = $row[GeoJsonSourceReader::LATITUDE] ?? null;
        $rawLng = $row[GeoJsonSourceReader::LONGITUDE] ?? null;

        if ($rawLat === null || trim((string) $rawLat) === '') {
            return $this->reject(CorpusRejectReason::MISSING_LATITUDE);
        }

        if ($rawLng === null || trim((string) $rawLng) === '') {
            return $this->reject(CorpusRejectReason::MISSING_LONGITUDE);
        }

        $latitude  = CoordinateValidator::toFloat($rawLat);
        $longitude = CoordinateValidator::toFloat($rawLng);

        if ($latitude === null) {
            return $this->reject(CorpusRejectReason::MALFORMED_LATITUDE);
        }

        if ($longitude === null) {
            return $this->reject(CorpusRejectReason::MALFORMED_LONGITUDE);
        }

        if (! CoordinateValidator::isValidPair($latitude, $longitude)) {
            return $this->reject(CorpusRejectReason::COORDINATE_INVALID);
        }

        if (! StateBounds::contains($latitude, $longitude, $stateFips)) {
            return $this->reject(CorpusRejectReason::OUTSIDE_BOUNDS);
        }

        // The one normalization. A corpus row and a typed address become strings
        // the same way or the corpus cannot be matched at all.
        $address = new PropertyAddress(
            address:     trim($number . ' ' . $street),
            unitAddress: $unit,
            city:        $city,
            county:      $county,
            state:       $state,
            zip:         $this->column($row, $this->map->zipColumn),
        );

        if (! $address->hasMinimumForLookup()) {
            return $this->reject(CorpusRejectReason::INSUFFICIENT);
        }

        return [
            'record' => new CorpusAddressRecord(
                source:              $this->map->source,
                sourceRef:           $sourceRef,
                number:              $number,
                street:              $street,
                unit:                $unit,
                city:                $city,
                state:               $state,
                postcode:            $this->column($row, $this->map->zipColumn),
                latitude:            $latitude,
                longitude:           $longitude,
                stateFips:           StateFips::normalizeFips($stateFips),
                county:              $county,
                rawPlacement:        $placement === '' ? null : $placement,
                localitySource:      $localitySource,
                unitSource:          $unitSource,
                address:             $address,
                placementLabel:      $placement === '' ? '' : strtolower($placement),
                // A NENA placement value is a label the county chose, not a
                // vocabulary this codebase maps to a precision tier. Reporting it
                // as "recognised" would imply we had read meaning into it.
                placementRecognised: false,
                precision:           $this->map->defaultPrecision,
                jurisdiction:        $this->map->jurisdiction,
                stateProvenance:     $stateProvenance,
                countyProvenance:    $countyProvenance,
                status:              $status,
            ),
            'reject' => null,
        ];
    }

    /**
     * The unit identifier, and which field supplied it.
     *
     * Type and id are joined ("Apt" + "4A") because `PropertyAddress` folds the
     * designator away anyway — what must survive is the identifier. The
     * additional columns exist for jurisdictions that model several designators
     * per record; they are appended so two condos that differ only in a
     * secondary unit stay distinct on the identity line.
     *
     * @param  array<string, mixed> $row
     * @return array{0: string, 1: string}
     */
    private function resolveUnit(array $row): array
    {
        $parts = [];

        $type = $this->column($row, $this->map->unitTypeColumn);
        $id   = $this->column($row, $this->map->unitIdColumn);

        if ($id !== '') {
            $parts[] = trim($type . ' ' . $id);
        }

        foreach ($this->map->additionalUnitColumns as $column) {
            $extra = $this->column($row, $column);

            if ($extra !== '') {
                $parts[] = $extra;
            }
        }

        if ($parts === []) {
            return ['', 'none'];
        }

        return [implode(' ', $parts), 'unit'];
    }

    /**
     * @param  array<string, mixed> $row
     * @return array{0: string, 1: string}
     */
    private function resolveCity(array $row): array
    {
        $city = $this->column($row, $this->map->cityColumn);

        if ($city !== '') {
            return [$city, 'post_city'];
        }

        $fallback = $this->column($row, $this->map->fallbackCityColumn);

        if ($fallback !== '') {
            return [$fallback, 'inc_muni'];
        }

        $municipality = $this->column($row, $this->map->municipalityColumn);

        return $municipality === '' ? ['', 'none'] : [$municipality, 'inc_muni'];
    }

    /**
     * @param  array<string, mixed> $row
     * @return array{0: string, 1: string}
     */
    private function resolveState(array $row): array
    {
        $stated = $this->column($row, $this->map->stateColumn);

        if ($stated !== '') {
            return [$stated, 'column'];
        }

        return [(string) $this->map->stateConstant, 'injected'];
    }

    /**
     * @param  array<string, mixed> $row
     * @return array{0: string, 1: string}
     */
    private function resolveCounty(array $row): array
    {
        $stated = $this->column($row, $this->map->countyColumn);

        if ($stated !== '') {
            return [$stated, 'column'];
        }

        return [(string) $this->map->countyConstant, 'injected'];
    }

    /** Trimmed value for a mapped column, '' when the column is unmapped/absent. */
    private function column(array $row, ?string $column): string
    {
        if ($column === null) {
            return '';
        }

        return trim((string) ($row[$column] ?? ''));
    }

    /** @return array{record: null, reject: string} */
    private function reject(string $reason): array
    {
        return ['record' => null, 'reject' => $reason];
    }
}
