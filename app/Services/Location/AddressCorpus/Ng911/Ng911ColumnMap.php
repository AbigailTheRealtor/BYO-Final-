<?php

namespace App\Services\Location\AddressCorpus\Ng911;

use App\Services\Location\Coordinates\CoordinatePrecision;

/**
 * How one NENA NG9-1-1 jurisdiction spells the fields the corpus needs.
 *
 * WHY A MAP AND NOT A NORMALIZER PER COUNTY
 * -----------------------------------------
 * Pinellas and Hillsborough both publish the NENA NG9-1-1 GIS Data Model, which
 * is what every state's 911 authority produces. Their *semantics* are identical:
 * `ADDRNUM` is a house number in both, `UNITID` is a subaddress in both, a
 * lifecycle status governs whether the row is a live address in both. What
 * differs is spelling — `PSTLCITY` against `POSTALCOMM`, `PSTLZIP5` against
 * `ZIP` — and which fields a county chose to publish at all.
 *
 * Spelling is configuration. Writing `PinellasRowNormalizer` and
 * `HillsboroughRowNormalizer` would duplicate the semantics to vary the
 * spelling, and the third county would duplicate them again. So there is one
 * {@see Ng911RowNormalizer} and one of these per jurisdiction, and adding a
 * county is expected to mean adding a map — not a parser.
 *
 * INJECTED CONSTANTS ARE PROVENANCE, NOT ASSUMPTIONS
 * --------------------------------------------------
 * Hillsborough publishes no state and no county column, because within
 * Hillsborough County's own 911 system both are self-evident. They are not
 * self-evident to a nationwide corpus. `stateConstant` / `countyConstant` supply
 * them, and the record records that they were *injected* rather than read — so a
 * later reviewer can tell a value the county asserted from a value we
 * configured. Inventing the data silently is the failure this guards against.
 *
 * PRECISION IS DECLARED, NOT DERIVED
 * ----------------------------------
 * `defaultPrecision` is what the jurisdiction's documentation justifies.
 * For both audited counties that is {@see CoordinatePrecision::Parcel} and not
 * Rooftop: Pinellas leaves `POINTTYPE` empty on 99.98% of rows and Hillsborough
 * fills it with the generic value `Location`, so neither documents whether the
 * point is a roof, a parcel centre or a driveway. Parcel locates the property
 * without claiming the roof, and it still satisfies `isExact()`, so Location DNA
 * can measure from it honestly. A future source that *does* document placement
 * can declare something finer here without touching the normalizer.
 */
final class Ng911ColumnMap
{
    /**
     * @param list<string>                   $additionalUnitColumns  further unit id columns, in fallback order
     * @param list<string>                   $activeStatusValues     case-folded statuses that count as live
     * @param list<string>                   $nonAddressPlacements   case-folded placements that are not properties
     * @param array<string, string>          $constants              informational provenance notes
     */
    public function __construct(
        public readonly string $source,
        public readonly string $jurisdiction,
        public readonly string $stateFips,
        public readonly string $numberColumn,
        public readonly string $streetColumn,
        public readonly string $sourceRefColumn,
        public readonly ?string $fallbackSourceRefColumn = null,
        public readonly ?string $unitTypeColumn = null,
        public readonly ?string $unitIdColumn = null,
        public readonly array $additionalUnitColumns = [],
        public readonly ?string $cityColumn = null,
        public readonly ?string $fallbackCityColumn = null,
        public readonly ?string $zipColumn = null,
        public readonly ?string $stateColumn = null,
        public readonly ?string $countyColumn = null,
        public readonly ?string $municipalityColumn = null,
        public readonly ?string $placementColumn = null,
        public readonly ?string $statusColumn = null,
        public readonly ?string $updatedColumn = null,
        public readonly ?string $stateConstant = null,
        public readonly ?string $countyConstant = null,
        public readonly array $activeStatusValues = [],
        public readonly array $nonAddressPlacements = [],
        public readonly CoordinatePrecision $defaultPrecision = CoordinatePrecision::Parcel,
    ) {
    }

    /**
     * Columns this map needs the source to actually contain.
     *
     * Only the two without which there is no address at all. Everything else is
     * either optional, or supplied by a constant, and a jurisdiction that omits
     * a ZIP but publishes a city is still usable — `PropertyAddress` says so.
     *
     * @return list<string>
     */
    public function requiredColumns(): array
    {
        return [$this->numberColumn, $this->streetColumn, $this->sourceRefColumn];
    }

    /**
     * Whether a status value counts as a live address.
     *
     * An empty allow-list means the jurisdiction publishes no status, so every
     * row is live — the alternative, treating "no status" as "not current",
     * would silently empty a corpus.
     */
    public function isActiveStatus(?string $status): bool
    {
        if ($this->activeStatusValues === []) {
            return true;
        }

        $value = strtolower(trim((string) $status));

        // A blank status in a source that *does* publish one is unknown, not
        // retired. Keeping it is the conservative direction: a live address
        // wrongly dropped is invisible, a retired one wrongly kept is visible in
        // the report.
        if ($value === '') {
            return true;
        }

        return in_array($value, array_map('strtolower', $this->activeStatusValues), true);
    }

    /**
     * Whether a placement value marks a feature that is not a property address.
     *
     * Exact match after case folding, never substring, and only against values
     * the audit actually observed. A 911 layer addresses lift stations and cell
     * towers; those are dispatchable locations, not properties. Values this list
     * does not name — including `Other` and `Unknown` — are kept rather than
     * guessed at, and remain visible in the placement distribution.
     */
    public function isNonAddressFeature(?string $placement): bool
    {
        $value = strtolower(trim((string) $placement));

        if ($value === '') {
            return false;
        }

        return in_array($value, array_map('strtolower', $this->nonAddressPlacements), true);
    }
}
