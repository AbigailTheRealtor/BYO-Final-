<?php

namespace App\Services\Location\Suggestions;

use App\Services\Location\Coordinates\CoordinatePrecision;
use App\Services\Location\Coordinates\PropertyAddress;

/**
 * One address a suggestion provider offered in response to a partial query.
 *
 * A CANDIDATE IS NOT A RESOLUTION
 * -------------------------------
 * This is the single most important thing about this type. A candidate is what
 * a provider *proposed* while somebody was still typing; a
 * {@see \App\Services\Location\Coordinates\PropertyCoordinateResult} is what the
 * coordinate ladder *concluded* for a property. They are deliberately separate
 * types with no conversion between them, and this class provides no method that
 * produces one.
 *
 * The reason is that the old address path did exactly that conversion. A browser
 * autocomplete pick carried a coordinate, the coordinate went straight onto the
 * listing, and nothing in between recorded which provider supplied it or how
 * precise it was — so a suggestion dropdown became an unaudited coordinate
 * source. Here, picking a candidate produces a {@see PropertyAddress} via
 * {@see self::toPropertyAddress()}, and that address goes through the ladder
 * like any other. A candidate's own coordinate is a hint for ranking and map
 * framing, never a property's location.
 *
 * WHY THE PARTS ARE SEPARATE FROM THE DISPLAY LINE
 * ------------------------------------------------
 * `displayLine` is for a human reading a dropdown; the structured parts are what
 * becomes a {@see PropertyAddress}. Re-parsing the display line back into parts
 * is how a suggestion layer starts guessing — a provider that already knows the
 * city should not have that fact recovered from a string by a regex on our side.
 * Providers that genuinely have only a single line put it in both places, which
 * makes the deficiency visible rather than hidden.
 *
 * `sourceRef` is the upstream record's own stable id, the same value the
 * `addresses` corpus stores in its `source_ref` column. A candidate that came
 * from our corpus can therefore be traced back to the exact row that produced
 * it, which is what makes a bad suggestion diagnosable instead of anecdotal.
 */
final class AddressCandidate
{
    public function __construct(
        /** Who offered this candidate — 'address_point', and later others. */
        public readonly string $providerId,

        /** What a human sees in the dropdown. */
        public readonly string $displayLine,

        public readonly string $number = '',
        public readonly string $street = '',
        public readonly string $unit = '',
        public readonly string $city = '',
        public readonly string $state = '',
        public readonly string $zip = '',

        /**
         * The provider's own stable id for this record, where it has one.
         * Mirrors `addresses.source_ref`.
         */
        public readonly ?string $sourceRef = null,

        /**
         * A hint, not an answer — see the class docblock. Nullable because a
         * provider may suggest an address without knowing where it is.
         */
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,

        /**
         * How precisely the hint above locates the address. Unknown by default,
         * which {@see CoordinatePrecision::isExact()} reads as coarse — a
         * provider that does not state its precision does not get to be trusted
         * with one.
         */
        public readonly CoordinatePrecision $precision = CoordinatePrecision::Unknown,

        /** Provider-reported 0..1 where available; most report nothing. */
        public readonly ?float $confidence = null,
    ) {
    }

    /**
     * The address to hand to the coordinate ladder when a user picks this
     * candidate.
     *
     * Street number and street name are joined into the single street line the
     * rest of the coordinate namespace speaks in; `PropertyAddress` does its own
     * normalization from there, so nothing is normalized twice.
     */
    public function toPropertyAddress(): PropertyAddress
    {
        return new PropertyAddress(
            address:     trim($this->number . ' ' . $this->street),
            unitAddress: $this->unit,
            city:        $this->city,
            state:       $this->state,
            zip:         $this->zip,
        );
    }

    /** True when the provider offered a point alongside the address. */
    public function hasCoordinateHint(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * The hint, for map framing only.
     *
     * Named for its one legitimate use. There is deliberately no accessor that
     * returns this for measurement: a coordinate becomes measurable by going
     * through the ladder and being recorded with provenance, not by having been
     * attached to a dropdown row.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function displayCoordinateHint(): ?array
    {
        if (! $this->hasCoordinateHint()) {
            return null;
        }

        return ['lat' => (float) $this->latitude, 'lng' => (float) $this->longitude];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'provider_id'  => $this->providerId,
            'display_line' => $this->displayLine,
            'number'       => $this->number,
            'street'       => $this->street,
            'unit'         => $this->unit,
            'city'         => $this->city,
            'state'        => $this->state,
            'zip'          => $this->zip,
            'source_ref'   => $this->sourceRef,
            'latitude'     => $this->latitude,
            'longitude'    => $this->longitude,
            'precision'    => $this->precision->value,
            'confidence'   => $this->confidence,
        ];
    }
}
