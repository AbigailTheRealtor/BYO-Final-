<?php

namespace App\Services\Location\Coordinates;

use App\Services\Schema\ProvenanceSchemaReadiness;
use Illuminate\Support\Facades\Log;

/**
 * Translates a resolution result into the columns `property_location_dna`
 * stores, and reads them back.
 *
 * WHY THIS IS A SEPARATE CLASS
 * ----------------------------
 * {@see PropertyCoordinateResult} is the provider-neutral domain type and knows
 * nothing about tables; giving it a `toColumns()` method would teach the whole
 * coordinate namespace the column names of one table, which is exactly the
 * coupling that makes a later move to `listing_locations` expensive. Keeping
 * the mapping in one small class means that move edits one file.
 *
 * PURE, AND DELIBERATELY NOT A WRITER
 * -----------------------------------
 * Nothing here touches the database, the container or a model. It converts
 * values in both directions and stops. G4's remit is to make provenance
 * *storable* and to prove the round trip; deciding *when* a listing's
 * coordinate is written is integration work (G5) and belongs with the flow that
 * owns the row. A mapper that quietly saved would put that decision here, where
 * no caller can see it.
 *
 * THE ROUND TRIP IS THE POINT
 * ---------------------------
 * A precision written as a string and read back as an unrecognised string would
 * silently degrade to Unknown — coarse — and a stored rooftop fix would stop
 * being usable for Location DNA without anything appearing to fail. So
 * {@see self::precisionFrom()} is explicit about the unrecognised case, and the
 * round trip is asserted in tests rather than assumed from the fact that both
 * sides say "string".
 */
final class CoordinateProvenance
{
    /**
     * The provenance columns for a resolved coordinate.
     *
     * Unresolved results yield nulls rather than an exception: a caller
     * recording a failed resolution is doing something reasonable, and the
     * honest record of "we tried and got nothing" is an empty provenance, not
     * a fabricated one.
     *
     * @return array{geocode_precision: string|null, geocode_provider: string|null, normalized_address: string|null}
     */
    public static function columnsFor(PropertyCoordinateResult $result): array
    {
        if (! $result->isResolved()) {
            return [
                'geocode_precision'  => null,
                'geocode_provider'   => null,
                // Kept even when unresolved: knowing which normalized line
                // failed to resolve is what makes a miss diagnosable.
                'normalized_address' => $result->normalizedAddress,
            ];
        }

        return [
            'geocode_precision'  => $result->precision->value,
            'geocode_provider'   => $result->provider,
            'normalized_address' => $result->normalizedAddress,
        ];
    }

    /**
     * The provenance columns to actually write, or an empty array when this
     * database cannot store them yet.
     *
     * The method a persistence caller should use. {@see self::columnsFor()}
     * answers "what would provenance be for this result"; this answers "what may
     * safely be written right now", and those differ during the window between a
     * release shipping and its migration being applied.
     *
     * Merging into an attribute array is the intended shape — an empty return
     * merges to nothing, so a caller writes the coordinate and simply omits the
     * provenance:
     *
     *   PropertyLocationDna::updateOrCreate($key, array_merge(
     *       $coordinateColumns,
     *       CoordinateProvenance::persistableColumnsFor($result),
     *   ));
     *
     * Skipping is the whole point. Provenance is metadata *about* a coordinate
     * we already computed successfully; losing it costs a diagnostic field,
     * whereas attempting the write against a missing column raises
     * `SQLSTATE[42703]` inside whatever transaction the caller is in — which for
     * a listing save is a 500 on publish.
     *
     * @return array{geocode_precision?: string|null, geocode_provider?: string|null, normalized_address?: string|null}
     */
    public static function persistableColumnsFor(PropertyCoordinateResult $result): array
    {
        if (! ProvenanceSchemaReadiness::isReady()) {
            self::warnSchemaNotReadyOnce();

            return [];
        }

        return self::columnsFor($result);
    }

    /**
     * Why provenance was omitted, or null when it was not.
     *
     * Derived, not remembered: it asks the same question
     * {@see self::persistableColumnsFor()} asked rather than caching an answer
     * that could drift from it.
     */
    public static function persistenceSkipReason(): ?string
    {
        return ProvenanceSchemaReadiness::isReady()
            ? null
            : ProvenanceSchemaReadiness::REASON_NOT_READY;
    }

    /**
     * Log the missing schema once per process.
     *
     * Once, because provenance is written per property and a listing sweep would
     * otherwise emit an identical line per row — burying the one fact worth
     * seeing under thousands of copies of itself. The condition is a property of
     * the deployment, not of any individual property, so it is worth saying
     * exactly once.
     */
    private static function warnSchemaNotReadyOnce(): void
    {
        if (self::$warnedSchemaNotReady) {
            return;
        }

        self::$warnedSchemaNotReady = true;

        Log::warning('coordinate_provenance_schema_not_ready', [
            'reason'          => ProvenanceSchemaReadiness::REASON_NOT_READY,
            'table'           => ProvenanceSchemaReadiness::TABLE,
            'missing_columns' => ProvenanceSchemaReadiness::missingColumns(),
            'consequence'     => 'coordinates are still stored; provenance is omitted until migrations run',
        ]);
    }

    /** Reset the once-per-process log latch. Tests only. */
    public static function flushWarningLatch(): void
    {
        self::$warnedSchemaNotReady = false;
    }

    private static bool $warnedSchemaNotReady = false;

    /**
     * The precision tier a stored row represents.
     *
     * Anything unrecognised — NULL, '', a value written by an older release, a
     * typo — reads as {@see CoordinatePrecision::Unknown}, which is coarse and
     * therefore blocked from Location DNA. That is the direction to fail in: a
     * row whose quality cannot be established must not be measured from, and
     * guessing a tier for it would defeat the gate entirely.
     */
    public static function precisionFrom(?string $stored): CoordinatePrecision
    {
        if ($stored === null || $stored === '') {
            return CoordinatePrecision::Unknown;
        }

        return CoordinatePrecision::tryFrom($stored) ?? CoordinatePrecision::Unknown;
    }

    /**
     * True when a stored row's recorded precision is good enough to drive
     * distance, travel-time and boundary work.
     *
     * The read-side counterpart of
     * {@see PropertyCoordinateResult::isUsableForLocationDna()}, so a consumer
     * reading from the table applies the same rule as one holding a fresh
     * result — rather than reinventing it and disagreeing.
     */
    public static function storedIsUsableForLocationDna(?string $storedPrecision): bool
    {
        return self::precisionFrom($storedPrecision)->isExact();
    }
}
