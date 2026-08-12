<?php

namespace App\Services\Location\AddressCorpus;

use App\Services\Location\Coordinates\Adapters\CoordinateValidator;
use App\Services\Location\Coordinates\PropertyAddress;

/**
 * One raw NAD row → an accepted {@see NadAddressRecord}, or a counted reject.
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
final class NadRowNormalizer
{
    // Reject reasons — stable strings, because they are reported and compared
    // across runs.
    public const REJECT_MISSING_UUID       = 'missing_uuid';
    public const REJECT_MISSING_NUMBER     = 'missing_address_number';
    public const REJECT_MISSING_STREET     = 'missing_street_name';
    public const REJECT_MISSING_LATITUDE   = 'missing_latitude';
    public const REJECT_MISSING_LONGITUDE  = 'missing_longitude';
    public const REJECT_MALFORMED_LATITUDE = 'malformed_latitude';
    public const REJECT_MALFORMED_LONGITUDE = 'malformed_longitude';
    public const REJECT_COORDINATE_INVALID = 'coordinate_out_of_range';
    public const REJECT_OUTSIDE_BOUNDS     = 'coordinate_outside_state_bounds';
    public const REJECT_INSUFFICIENT       = 'insufficient_for_lookup';

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
     * Generous bounding boxes per FIPS, as [minLat, maxLat, minLng, maxLng].
     *
     * Deliberately loose — they include offshore keys and territorial water, and
     * they are a sanity check rather than a boundary test. A state absent from
     * this list is simply not bounds-checked, which is the right failure
     * direction: a missing box must not reject a valid address.
     */
    public const STATE_BOUNDS = [
        '12' => [24.2, 31.2, -87.8, -79.8],   // Florida, incl. the Keys
    ];

    /**
     * @param  array<string, string|null> $row header-keyed NAD row
     * @return array{record: NadAddressRecord|null, reject: string|null}
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

        return [
            'record' => new NadAddressRecord(
                sourceRef:      $uuid,
                number:         $number,
                street:         $street,
                unit:           $unit,
                city:           $city,
                state:          $this->value($row, 'State'),
                postcode:       $this->value($row, 'Zip_Code'),
                latitude:       $latitude,
                longitude:      $longitude,
                stateFips:      $stateFips,
                county:         $this->value($row, 'County'),
                rawPlacement:   $this->rawValue($row, 'Placement'),
                localitySource: $localitySource,
                unitSource:     $unitSource,
                address:        $address,
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
        $box = self::STATE_BOUNDS[StateFips::normalizeFips($stateFips)] ?? null;

        if ($box === null) {
            return true;
        }

        [$minLat, $maxLat, $minLng, $maxLng] = $box;

        return $lat >= $minLat && $lat <= $maxLat && $lng >= $minLng && $lng <= $maxLng;
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
