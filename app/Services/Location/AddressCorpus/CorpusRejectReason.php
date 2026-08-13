<?php

namespace App\Services\Location\AddressCorpus;

/**
 * The reasons a source row does not become a corpus record.
 *
 * WHY THESE ARE SHARED RATHER THAN PER-SOURCE
 * -------------------------------------------
 * They were constants on `NadRowNormalizer` while NAD was the only source. A
 * second source made that a problem of a specific kind: these strings are
 * *reported* and compared across runs, so two normalizers each spelling
 * "missing_street_name" their own way would produce two dimensions in a report
 * that is supposed to compare sources. A corpus operator asking "which source
 * loses more rows to missing streets" needs one string, not two that happen to
 * look alike today.
 *
 * `NadRowNormalizer` keeps its own `REJECT_*` constants pointed here, so the
 * names it published stay valid.
 *
 * NOTHING IS DROPPED SILENTLY
 * ---------------------------
 * Every row that does not become a record returns one of these. An import that
 * discards 8% of a jurisdiction and reports "done" is worse than one that fails.
 */
final class CorpusRejectReason
{
    // ── identity ────────────────────────────────────────────────────────────
    public const MISSING_SOURCE_REF = 'missing_source_ref';
    public const MISSING_UUID       = 'missing_uuid';

    // ── address components ──────────────────────────────────────────────────
    public const MISSING_NUMBER = 'missing_address_number';
    public const MISSING_STREET = 'missing_street_name';
    public const INSUFFICIENT   = 'insufficient_for_lookup';

    // ── coordinates ─────────────────────────────────────────────────────────
    public const MISSING_LATITUDE    = 'missing_latitude';
    public const MISSING_LONGITUDE   = 'missing_longitude';
    public const MALFORMED_LATITUDE  = 'malformed_latitude';
    public const MALFORMED_LONGITUDE = 'malformed_longitude';
    public const COORDINATE_INVALID  = 'coordinate_out_of_range';
    public const OUTSIDE_BOUNDS      = 'coordinate_outside_state_bounds';

    /**
     * NENA sources carry a lifecycle status. A retired address is a real
     * historical record and a wrong answer to "where is this property" — it must
     * not become a resolution candidate. See {@see Ng911\Ng911ColumnMap}.
     */
    public const INACTIVE_STATUS = 'inactive_address_status';

    /**
     * A 911 layer addresses every dispatchable location, which includes lift
     * stations, cell towers and dumpsters. Those are addresses; they are not
     * properties, and a property search that resolved to one would be wrong in a
     * way no coordinate check could catch.
     */
    public const NON_ADDRESS_FEATURE = 'non_address_feature';

    /** Wrong jurisdiction for the requested scope. */
    public const OUTSIDE_JURISDICTION = 'outside_requested_jurisdiction';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::MISSING_SOURCE_REF,
            self::MISSING_UUID,
            self::MISSING_NUMBER,
            self::MISSING_STREET,
            self::INSUFFICIENT,
            self::MISSING_LATITUDE,
            self::MISSING_LONGITUDE,
            self::MALFORMED_LATITUDE,
            self::MALFORMED_LONGITUDE,
            self::COORDINATE_INVALID,
            self::OUTSIDE_BOUNDS,
            self::INACTIVE_STATUS,
            self::NON_ADDRESS_FEATURE,
            self::OUTSIDE_JURISDICTION,
        ];
    }
}
