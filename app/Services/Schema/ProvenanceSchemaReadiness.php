<?php

namespace App\Services\Schema;

use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Is this database able to store coordinate provenance yet?
 *
 * THE FAILURE THIS PREVENTS
 * -------------------------
 * Code and schema ship separately. The provenance columns were added by a
 * migration that is merged, but a deployment applies migrations only if
 * something tells it to — and until G4.5 nothing did. So there is a real,
 * observed window in which an environment runs code that knows about
 * `geocode_precision` against a table that does not have it.
 *
 * Writing to a column that does not exist raises `SQLSTATE[42703] Undefined
 * column`. That is not a degraded coordinate; it is an uncaught database
 * exception, and if the write sits inside a listing save it surfaces as a 500
 * on publish. The user loses their work over a column that only records
 * *metadata about* a coordinate we already successfully computed.
 *
 * So provenance is treated as optional at the storage layer: when the columns
 * are absent the coordinate is still stored, the provenance is skipped, and the
 * caller is told why.
 *
 * WHY THIS IS NOT REDUNDANT WITH THE DEPLOY STEP
 * ----------------------------------------------
 * `deploy/start-production.sh` now migrates before serving, which should make
 * this guard permanently inert. It is kept anyway, for reasons that are about
 * where each control lives rather than distrust of the other:
 *
 *   - The deploy guarantee lives in `.replit` and a shell script, neither of
 *     which any test in this repository can execute. A typo silently restores
 *     the old behaviour with no failing test.
 *   - Production runs more than one process. The scheduler starts independently
 *     of the web server and deliberately does not migrate, so a scheduled
 *     command can genuinely observe a pre-migration schema.
 *   - The two controls fail in opposite directions. The deploy step makes the
 *     schema correct; this makes being wrong survivable. Neither substitutes.
 *
 * PROVIDER- AND FLOW-NEUTRAL
 * --------------------------
 * Named for the columns it protects, not for Census, not for Seller/Landlord,
 * and it imports nothing from either. Any future provider or flow that persists
 * provenance asks the same question here.
 *
 * CACHING
 * -------
 * A schema lookup is a database round trip, and provenance is written once per
 * property resolution — potentially in a loop over a listing set. Asking three
 * times per row would turn a metadata concern into a query-count problem, so
 * the answer is memoised per process.
 *
 * The cache is process-local and NOT persisted, which is the correct lifetime:
 * a migration changes the schema in a way that outlives no process, and every
 * new worker re-checks. {@see self::flush()} exists for tests and for a
 * long-lived worker that migrates underneath itself.
 */
final class ProvenanceSchemaReadiness
{
    /**
     * Every column {@see \App\Services\Location\Coordinates\CoordinateProvenance}
     * writes. All three or nothing — a partially applied migration is not a
     * state we want to write into.
     */
    public const REQUIRED_COLUMNS = [
        'geocode_precision',
        'geocode_provider',
        'normalized_address',
    ];

    public const TABLE = 'property_location_dna';

    /** The structured reason recorded when provenance cannot be stored. */
    public const REASON_NOT_READY = 'schema_not_ready';

    private static ?bool $ready = null;

    /**
     * True when every provenance column exists.
     *
     * Any failure to establish that — a missing table, a connection error, a
     * driver that cannot introspect — answers false. Provenance is metadata; if
     * we cannot confirm it is safe to write, not writing it costs a diagnostic
     * field, whereas guessing costs an exception in a save path.
     */
    public static function isReady(): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }

        return self::$ready = self::inspect();
    }

    /** The columns that are missing right now. Diagnostics and telemetry. */
    public static function missingColumns(): array
    {
        try {
            if (! Schema::hasTable(self::TABLE)) {
                return self::REQUIRED_COLUMNS;
            }

            return array_values(array_filter(
                self::REQUIRED_COLUMNS,
                static fn (string $column): bool => ! Schema::hasColumn(self::TABLE, $column)
            ));
        } catch (Throwable) {
            return self::REQUIRED_COLUMNS;
        }
    }

    /**
     * Forget the memoised answer.
     *
     * Needed by tests that add or drop the columns mid-run, and by any
     * long-lived process that outlives a migration.
     */
    public static function flush(): void
    {
        self::$ready = null;
    }

    private static function inspect(): bool
    {
        try {
            if (! Schema::hasTable(self::TABLE)) {
                return false;
            }

            foreach (self::REQUIRED_COLUMNS as $column) {
                if (! Schema::hasColumn(self::TABLE, $column)) {
                    return false;
                }
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
