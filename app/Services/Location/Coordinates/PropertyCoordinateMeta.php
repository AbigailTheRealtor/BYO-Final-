<?php

namespace App\Services\Location\Coordinates;

use DateTimeInterface;

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
     * When this coordinate was established, ISO-8601.
     *
     * Not the same fact as `property_location_dna.geocoded_at`. That column
     * records when the PIPELINE last wrote its row, and the pipeline rewrites it
     * on runs that did not change the coordinate at all. This records when the
     * coordinate itself was determined, and it survives being read back and
     * re-stored by the Existing rung — which is what makes "how old is this
     * point" answerable rather than resetting to now on every save.
     */
    public const RECORDED_AT = 'property_coordinate_recorded_at';

    /**
     * The upstream record this coordinate came from, where the source publishes
     * a stable identifier.
     *
     * One opaque string, deliberately not a per-source key. A NENA SITEADDID, a
     * NAD UUID and an MLS listing key are all "which record upstream said this",
     * and naming any of them here would put a jurisdiction into a class that has
     * stayed provider-neutral on purpose. Empty for sources that publish none.
     */
    public const SOURCE_REF = 'property_coordinate_source_ref';

    /**
     * The user id behind a manual override. Empty for every automatic source.
     *
     * @see PropertyCoordinateResult::manual()
     */
    public const ACTOR_ID = 'property_coordinate_actor_id';

    /**
     * The stated justification for a manual override. Empty for automatic
     * sources.
     *
     * Not to be confused with an unresolved result's failure reason, which is a
     * machine code about why nothing was found and is never stored here.
     */
    public const REASON = 'property_coordinate_reason';

    /**
     * Every provenance key, i.e. everything except the coordinate itself.
     *
     * @return list<string>
     */
    public static function provenanceKeys(): array
    {
        return [
            self::PRECISION,
            self::PROVIDER,
            self::SOURCE,
            self::NORMALIZED_ADDRESS,
            self::RECORDED_AT,
            self::SOURCE_REF,
            self::ACTOR_ID,
            self::REASON,
        ];
    }

    /**
     * The provenance a resolved result should be stored with.
     *
     * Every key is always written, empty string included. A key that is present
     * and empty says "this source publishes no such value"; a key that is absent
     * says "this coordinate predates the field". Those are different facts and
     * {@see self::classify()} needs to tell them apart.
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
            self::RECORDED_AT        => $result->resolvedAt?->format(DateTimeInterface::ATOM) ?? '',
            self::SOURCE_REF         => (string) ($result->sourceRef ?? ''),
            // Written only by a manual override. An automatic result carries
            // null for both, which stores as empty — the record of the fact that
            // no person was involved, rather than the absence of a record.
            self::ACTOR_ID           => $result->actorId !== null ? (string) $result->actorId : '',
            self::REASON             => (string) ($result->overrideReason ?? ''),
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
     * The enriched keys are returned alongside the original four and are never
     * required. A coordinate resolved before they existed reads back with them
     * empty and stays exactly as trustworthy as it was.
     *
     * @param  callable(string): mixed $reader a meta getter, e.g. fn($k) => $listing->info($k)
     * @return array{precision: string, provider: string, source: string, normalized_address: string, recorded_at: string, source_ref: string, actor_id: string, reason: string}|null
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
            'recorded_at'        => trim((string) ($reader(self::RECORDED_AT) ?? '')),
            'source_ref'         => trim((string) ($reader(self::SOURCE_REF) ?? '')),
            'actor_id'           => trim((string) ($reader(self::ACTOR_ID) ?? '')),
            'reason'             => trim((string) ($reader(self::REASON) ?? '')),
        ];
    }

    /**
     * What kind of provenance this listing's coordinate carries.
     *
     * The question every downstream trust decision actually wants answered, in
     * one place, so no caller re-derives it from string comparisons. Fix B's
     * reuse policy is the first real consumer; today this only classifies.
     *
     * A manual claim is verified, not taken at face value. `source = manual`
     * without an actor and a reason is not a weaker manual override — it is a
     * coordinate wearing one, which is precisely the thing browser-supplied
     * values must never be able to become. It classifies as Incomplete, and the
     * accountable path is the only way to reach Manual.
     *
     * @param callable(string): mixed $reader a meta getter
     */
    public static function classify(callable $reader): CoordinateProvenanceStatus
    {
        $provenance = self::readProvenance($reader);

        if ($provenance === null) {
            return CoordinateProvenanceStatus::Incomplete;
        }

        if ($provenance['source'] === CoordinateSource::Manual->value) {
            $signed = $provenance['actor_id'] !== ''
                && ctype_digit($provenance['actor_id'])
                && (int) $provenance['actor_id'] > 0
                && $provenance['reason'] !== '';

            return $signed
                ? CoordinateProvenanceStatus::Manual
                : CoordinateProvenanceStatus::Incomplete;
        }

        // Conversely, an automatic source must NOT be carrying an actor. If one
        // is present the record contradicts itself, and a self-contradictory
        // provenance is not a trustworthy one.
        if ($provenance['actor_id'] !== '') {
            return CoordinateProvenanceStatus::Incomplete;
        }

        return CoordinateProvenanceStatus::Automatic;
    }
}
