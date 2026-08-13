<?php

namespace App\Services\Location\AddressCorpus;

use App\Services\Location\AddressCorpus\Contracts\AddressRowNormalizer;
use App\Services\Location\Coordinates\Adapters\CoordinateValidator;
use App\Services\Location\Coordinates\PropertyAddress;

/**
 * One raw NAD row → an accepted {@see CorpusAddressRecord}, or a counted reject.
 *
 * Pure: no database, no container, no clock, no filesystem. That is what lets
 * the dry run stream a national file through it and lets every edge case be a
 * unit test with a literal array.
 *
 * NOTHING IS DROPPED SILENTLY
 * ---------------------------
 * Every row that does not become a record returns a reason. A corpus import that
 * discards 8% of a state and reports "done" is worse than one that fails, so the
 * reasons are enumerated here and counted by the caller.
 *
 * A COORDINATE THAT PARSES IS NOT A COORDINATE THAT IS PLAUSIBLE
 * --------------------------------------------------------------
 * `0,0` parses. So does a Florida address carrying a Nevada longitude, and so
 * does a sign-flipped longitude that lands in the Indian Ocean. Numeric validity
 * is checked by {@see CoordinateValidator} (range, finiteness, Null Island), and
 * then the point is checked against the bounding box of the jurisdiction the row
 * claims. A bounding box is coarse and will admit a wrong-but-nearby point; it
 * exists to catch the failures that are catastrophic and obvious, not to verify
 * a location.
 */
final class NadRowNormalizer implements AddressRowNormalizer
{
    public const SOURCE = 'nad';

    // Reject reasons — stable strings, because they are reported and compared
    // across runs. They now point at the shared vocabulary in
    // {@see CorpusRejectReason}: a second source made one-vocabulary-per-source
    // a reporting problem, since an operator comparing two jurisdictions needs
    // one dimension and not two that happen to look alike. The names published
    // here keep working.
    public const REJECT_MISSING_UUID       = CorpusRejectReason::MISSING_UUID;
    public const REJECT_MISSING_NUMBER     = CorpusRejectReason::MISSING_NUMBER;
    public const REJECT_MISSING_STREET     = CorpusRejectReason::MISSING_STREET;
    public const REJECT_MISSING_LATITUDE   = CorpusRejectReason::MISSING_LATITUDE;
    public const REJECT_MISSING_LONGITUDE  = CorpusRejectReason::MISSING_LONGITUDE;
    public const REJECT_MALFORMED_LATITUDE = CorpusRejectReason::MALFORMED_LATITUDE;
    public const REJECT_MALFORMED_LONGITUDE = CorpusRejectReason::MALFORMED_LONGITUDE;
    public const REJECT_COORDINATE_INVALID = CorpusRejectReason::COORDINATE_INVALID;
    public const REJECT_OUTSIDE_BOUNDS     = CorpusRejectReason::OUTSIDE_BOUNDS;
    public const REJECT_INSUFFICIENT       = CorpusRejectReason::INSUFFICIENT;

    public const REJECT_REASONS = [
        self::REJECT_MISSING_UUID,
        self::REJECT_MISSING_NUMBER,
        self::REJECT_MISSING_STREET,
        self::REJECT_MISSING_LATITUDE,
        self::REJECT_MISSING_LONGITUDE,
        self::REJECT_MALFORMED_LATITUDE,
        self::REJECT_MALFORMED_LONGITUDE,
        self::REJECT_COORDINATE_INVALID,
        self::REJECT_OUTSIDE_BOUNDS,
        self::REJECT_INSUFFICIENT,
    ];

    /**
     * Generous bounding boxes per FIPS.
     *
     * Moved to {@see StateBounds} when a second source arrived — both
     * normalizers ask the same question of the same coordinate, and two copies
     * of a box would eventually disagree about where Florida is. Kept here as an
     * alias because the constant was published.
     */
    public const STATE_BOUNDS = StateBounds::BOXES;

    public function source(): string
    {
        return self::SOURCE;
    }

    /**
     * @param  array<string, string|null> $row header-keyed NAD row
     * @return array{record: CorpusAddressRecord|null, reject: string|null}
     */
    public function normalize(array $row, string $stateFips): array
    {
        $uuid = $this->value($row, 'UUID');

        if ($uuid === '') {
            return $this->reject(self::REJECT_MISSING_UUID);
        }

        // Number: AddNo_Full carries the prefix and suffix ("123 1/2", "N6W2
        // 3001"); Add_Number is the bare integer. Prefer the complete form and
        // fall back, because a row with only the bare number is still an address.
        $number = $this->value($row, 'AddNo_Full') ?: $this->value($row, 'Add_Number');

        if ($number === '') {
            return $this->reject(self::REJECT_MISSING_NUMBER);
        }

        $street = $this->value($row, 'StNam_Full');

        if ($street === '') {
            return $this->reject(self::REJECT_MISSING_STREET);
        }

        // Locality: Post_City is the name a person writes on an envelope and
        // therefore types into our form. Inc_Muni is the legal municipality,
        // which differs across the large unincorporated share of Florida. See
        // the field-mapping decision in CLAUDE.md.
        $city           = $this->value($row, 'Post_City');
        $localitySource = 'post_city';

        if ($city === '') {
            $city           = $this->value($row, 'Inc_Muni');
            $localitySource = $city === '' ? 'none' : 'inc_muni';
        }

        // Unit: SubAddress is the complete subaddress ("Apartment 101"); Unit is
        // the bare designator. Either normalizes to the same identifier.
        $unit       = $this->value($row, 'SubAddress');
        $unitSource = 'subaddress';

        if ($unit === '') {
            $unit       = $this->value($row, 'Unit');
            $unitSource = $unit === '' ? 'none' : 'unit';
        }

        $rawLat = $this->rawValue($row, 'Latitude');
        $rawLng = $this->rawValue($row, 'Longitude');

        if (trim((string) $rawLat) === '') {
            return $this->reject(self::REJECT_MISSING_LATITUDE);
        }

        if (trim((string) $rawLng) === '') {
            return $this->reject(self::REJECT_MISSING_LONGITUDE);
        }

        $latitude  = CoordinateValidator::toFloat($rawLat);
        $longitude = CoordinateValidator::toFloat($rawLng);

        if ($latitude === null) {
            return $this->reject(self::REJECT_MALFORMED_LATITUDE);
        }

        if ($longitude === null) {
            return $this->reject(self::REJECT_MALFORMED_LONGITUDE);
        }

        if (! CoordinateValidator::isValidPair($latitude, $longitude)) {
            return $this->reject(self::REJECT_COORDINATE_INVALID);
        }

        if (! $this->withinStateBounds($latitude, $longitude, $stateFips)) {
            return $this->reject(self::REJECT_OUTSIDE_BOUNDS);
        }

        $address = new PropertyAddress(
            address:     trim($number . ' ' . $street),
            unitAddress: $unit,
            city:        $city,
            county:      $this->value($row, 'County'),
            state:       $this->value($row, 'State'),
            zip:         $this->value($row, 'Zip_Code'),
        );

        // The corpus is matched on the lookup line, so a row that cannot produce
        // one is a row the rung could never return. Rejecting it here keeps it
        // out of the index rather than out of the results.
        if (! $address->hasMinimumForLookup()) {
            return $this->reject(self::REJECT_INSUFFICIENT);
        }

        $placement = $this->rawValue($row, 'Placement');

        return [
            'record' => new CorpusAddressRecord(
                source:         self::SOURCE,
                sourceRef:      $uuid,
                number:         $number,
                street:         $street,
                unit:           $unit,
                city:           $city,
                state:          $this->value($row, 'State'),
                postcode:       $this->value($row, 'Zip_Code'),
                latitude:       $latitude,
                longitude:      $longitude,
                stateFips:      StateFips::normalizeFips($stateFips),
                county:         $this->value($row, 'County'),
                rawPlacement:   $placement,
                localitySource: $localitySource,
                unitSource:     $unitSource,
                address:        $address,

                // The placement decision is resolved here, by the source that
                // owns the vocabulary, so the dry-run report never has to know
                // NAD exists. `proposedPrecision()` returns null when it
                // declines, and null must stay null — see NadPlacementMap.
                placementLabel:      NadPlacementMap::normalize($placement),
                placementRecognised: NadPlacementMap::isRecognised($placement),
                precision:           NadPlacementMap::proposedPrecision($placement),

                jurisdiction:     StateFips::toUsps($stateFips) ?? '',
                stateProvenance:  'column',
                countyProvenance: 'column',
            ),
            'reject' => null,
        ];
    }

    /** True when the row's `State` matches the requested jurisdiction. */
    public function matchesState(array $row, string $stateFips): bool
    {
        $wanted = StateFips::toUsps($stateFips);

        if ($wanted === null) {
            return false;
        }

        return strtoupper($this->value($row, 'State')) === $wanted;
    }

    private function withinStateBounds(float $lat, float $lng, string $stateFips): bool
    {
        return StateBounds::contains($lat, $lng, $stateFips);
    }

    /** @return array{record: null, reject: string} */
    private function reject(string $reason): array
    {
        return ['record' => null, 'reject' => $reason];
    }

    /** Trimmed string value for a header, '' when absent. */
    private function value(array $row, string $key): string
    {
        return trim((string) ($row[$key] ?? ''));
    }

    /** Raw value, preserving null vs '' so absence and emptiness stay distinct. */
    private function rawValue(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        return $value === null ? null : (string) $value;
    }
}
