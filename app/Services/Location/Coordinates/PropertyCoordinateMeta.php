<?php

namespace App\Services\Location\Coordinates;

/**
 * The listing meta keys that carry a resolved coordinate and its provenance.
 *
 * WHY THE COORDINATE TRAVELS THROUGH META
 * ---------------------------------------
 * `property_location_dna` already has an owner. {@see \App\Services\LocationDna\LocationDnaGeocodeService}
 * writes it asynchronously from {@see \App\Jobs\ComputeLocationDna}, and — at
 * its step (d) — clears `geocoded_lat`/`geocoded_lng` whenever the stored
 * `source_*` fields differ from the address it was handed. Because that job is
 * dispatched *after* the very save a listing flow resolves on, anything written
 * straight into that table can be nulled moments later by a job already in the
 * queue.
 *
 * So the resolver does not write there. It writes `property_lat`/`property_lng`
 * meta — the values the pipeline already reads as `pre_lat`/`pre_lng` and
 * honours ahead of any geocoding — and the pipeline remains the single writer
 * of `property_location_dna`. One owner per stage, no race.
 *
 * WHY THE PROVENANCE KEYS EXIST
 * -----------------------------
 * The pipeline's pre-coordinate branch historically stamped every supplied
 * coordinate `geocode_source = 'saved_meta'`, because the only thing that ever
 * supplied one was the Google Places autocomplete widget and there was nothing
 * else to say. A ladder result knows considerably more: which provider answered,
 * how precisely the point identifies the property, and which address the
 * coordinate actually describes. Flattening a Census interpolation and an MLS
 * parcel coordinate into the same word discards exactly the distinction
 * {@see CoordinatePrecision} exists to preserve.
 *
 * These keys carry that through. Their absence is meaningful too: a
 * `property_lat` with no provenance beside it is a legacy value whose origin
 * cannot be proven, and it must keep its legacy handling rather than be
 * assigned a precision nobody established.
 *
 * NAMING
 * ------
 * `property_*`, snake_case, matching every other listing meta key
 * (`property_city`, `property_zip`, `property_lat`). Provider-neutral by
 * construction — nothing here names Census, Bridge or Google, so a future
 * provider needs no new keys.
 */
final class PropertyCoordinateMeta
{
    /** Latitude. Pre-existing key — the pipeline already reads it as `pre_lat`. */
    public const LAT = 'property_lat';

    /** Longitude. Pre-existing key — the pipeline already reads it as `pre_lng`. */
    public const LNG = 'property_lng';

    /** {@see CoordinatePrecision} value: 'interpolated', 'parcel', 'rooftop', … */
    public const PRECISION = 'property_coordinate_precision';

    /** Provider id: 'us_census', 'bridge_mls', 'existing_coordinates', … */
    public const PROVIDER = 'property_coordinate_provider';

    /** {@see CoordinateSource} value: 'geocoder', 'mls', 'existing', … */
    public const SOURCE = 'property_coordinate_source';

    /**
     * The normalized address the coordinate describes.
     *
     * Doubles as the change detector. It is the unit-free
     * {@see PropertyAddress::coordinateLookupLine()}, so re-resolution is
     * triggered by a genuinely different address and not by re-typing the same
     * one differently — and a unit-only edit reuses the building coordinate,
     * exactly as the addressing rules intend.
     */
    public const NORMALIZED_ADDRESS = 'property_coordinate_normalized_address';

    /**
     * Every provenance key, i.e. everything except the coordinate itself.
     *
     * @return list<string>
     */
    public static function provenanceKeys(): array
    {
        return [self::PRECISION, self::PROVIDER, self::SOURCE, self::NORMALIZED_ADDRESS];
    }

    /**
     * The provenance a resolved result should be stored with.
     *
     * @return array<string, string>
     */
    public static function provenanceFor(PropertyCoordinateResult $result): array
    {
        return [
            self::PRECISION          => $result->precision->value,
            self::PROVIDER           => (string) ($result->provider ?? ''),
            self::SOURCE             => (string) ($result->source?->value ?? ''),
            self::NORMALIZED_ADDRESS => (string) ($result->normalizedAddress ?? ''),
        ];
    }

    /**
     * Read provenance back off a listing, or null when it is absent or partial.
     *
     * Partial counts as absent on purpose. Provenance is a single statement
     * about one coordinate; half of it is not a weaker version of that
     * statement, it is an unverifiable one, and the caller must fall back to
     * legacy handling rather than assemble a claim out of fragments.
     *
     * @param  callable(string): mixed $reader a meta getter, e.g. fn($k) => $listing->info($k)
     * @return array{precision: string, provider: string, source: string, normalized_address: string}|null
     */
    public static function readProvenance(callable $reader): ?array
    {
        $precision  = trim((string) ($reader(self::PRECISION) ?? ''));
        $provider   = trim((string) ($reader(self::PROVIDER) ?? ''));
        $source     = trim((string) ($reader(self::SOURCE) ?? ''));
        $normalized = trim((string) ($reader(self::NORMALIZED_ADDRESS) ?? ''));

        if ($precision === '' || $provider === '') {
            return null;
        }

        // A precision this release does not recognise is not a precision. Better
        // to fall back to legacy handling than to store a tier no consumer can
        // interpret and that CoordinatePrecision would read as Unknown anyway.
        if (CoordinatePrecision::tryFrom($precision) === null) {
            return null;
        }

        return [
            'precision'          => $precision,
            'provider'           => $provider,
            'source'             => $source,
            'normalized_address' => $normalized,
        ];
    }
}
