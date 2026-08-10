<?php

namespace App\Services\Location\Coordinates;

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
