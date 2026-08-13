<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Address corpus versioning + provenance (extends B1.2 migration 09/11).
 *
 * WHAT THIS FIXES
 * ---------------
 * `addresses` shipped in B1.2 with every *address* field it needs — number,
 * street, unit, city, state, postcode, normalized, geom, precision — and no way
 * to be safely *imported into*. Its only constraint is the surrogate primary key
 * on `id`, so running an import twice silently doubles the corpus: there is no
 * key that says "this upstream record is already here".
 *
 * Its sibling from the same batch, `places`, already solved this exact problem
 * with `UNIQUE (corpus_version, source, source_ref)`. This migration brings the
 * same invariant to `addresses`. It adds no address semantics and changes none.
 *
 * WHY EACH COLUMN EXISTS (nothing here is speculative)
 * ---------------------------------------------------
 *   corpus_version  Which import produced this row. Without it there is one
 *                   undifferentiated pile and no way to replace a jurisdiction's
 *                   data without deleting rows by hand — or to keep the old
 *                   slice readable while a new one loads.
 *   source_ref      The upstream record's own stable id. This is the half of the
 *                   dedupe key that makes re-import idempotent. Both candidate
 *                   sources supply one: NAD publishes `UUID` (Always Used) and
 *                   OpenAddresses carries the county feature id.
 *   state_fips      The jurisdiction this row was imported *for* — deliberately
 *                   distinct from `state`, which is what the address itself
 *                   claims. A mis-keyed record can carry the wrong `state` and
 *                   still belong to the Florida batch, and a bounded delete must
 *                   follow the batch, not the typo. Also the unit the existing
 *                   `corpus_imports.territory_coverage` ledger already speaks in
 *                   ({"region":"florida","state_fips":"12"}).
 *   first_seen      When this row first appeared, and when it was last
 *   last_seen       confirmed by an import. `places` carries the same pair.
 *
 * Deliberately NOT added: `attrs` and `confidence`. `places` has both, but
 * nothing in the address path reads either, and a column added "because the
 * sibling has one" is how a schema stops describing its data.
 *
 * WHY NOT NULL, AND WHY IT IS NOT OPTIONAL
 * ----------------------------------------
 * PostgreSQL treats NULLs in a unique index as *distinct from each other*. A
 * nullable `corpus_version` or `source_ref` would therefore let unlimited
 * duplicates through a UNIQUE constraint that looks like it forbids them —
 * the failure mode is silent, and it is the precise failure this migration
 * exists to prevent. So the dedupe columns are NOT NULL or the invariant is
 * decorative.
 *
 * The table is empty today (0 rows), which is the only reason NOT NULL can be
 * added without a backfill. If it is ever not empty when this runs, the
 * migration refuses rather than inventing a value — see the guard in up().
 *
 * WHY THIS TABLE IS NOT PARTITIONED (and `places` is)
 * ---------------------------------------------------
 * `places` is LIST-partitioned by corpus_version, which is how it gets atomic
 * corpus swap: build a partition, attach it, detach the old one. The obvious
 * move is to mirror that here, and it is not available.
 *
 * PostgreSQL has no `ALTER TABLE ... PARTITION BY`. A partitioned table can only
 * be *created* partitioned. Converting `addresses` would mean creating a second
 * table, copying, dropping the original and renaming — a destructive recreation
 * of a table that already exists in a live database, to gain a property nothing
 * currently needs, at a moment when the corpus is empty and there is no import
 * to swap. That trade is not worth making blind, so it is not made here.
 *
 * What is lost is atomic swap. What replaces it is bounded and reversible:
 * `corpus_version` + `state_fips` are both indexed, so retiring an import is a
 * scoped DELETE that names its own blast radius, and two corpus versions can
 * coexist while one is validated. If atomic swap is later needed, converting
 * then is a deliberate migration with its own review — and it will be no harder
 * than it is today, because these columns are exactly the ones a partition key
 * would need.
 *
 * Additive throughout: no column is dropped, no index is replaced, no existing
 * address field or index is touched, and no consumer reads any of this yet.
 */
class SpatialCoreVersionAddressCorpus extends Migration
{
    protected $connection = 'pgsql_spatial';

    public function up(): void
    {
        $this->guardSpatialConnection();
        $conn = DB::connection($this->getConnection());

        // Additive first, so the columns exist before anything constrains them.
        foreach ([
            'corpus_version text',
            'source_ref     text',
            'state_fips     char(2)',
            'first_seen     timestamptz',
            'last_seen      timestamptz',
        ] as $column) {
            $conn->statement("ALTER TABLE addresses ADD COLUMN IF NOT EXISTS {$column}");
        }

        // NOT NULL is what makes the unique index below mean anything. It can
        // only be asserted on an empty table; refusing loudly beats backfilling
        // a jurisdiction or a corpus tag we would have to invent.
        $existing = (int) $conn->table('addresses')->count();

        if ($existing > 0) {
            throw new \RuntimeException(
                "[spatial] `addresses` holds {$existing} row(s), so corpus_version / source_ref / "
                . 'state_fips cannot be made NOT NULL without a backfill. Backfill those three '
                . 'columns for every existing row, then re-run this migration.'
            );
        }

        foreach (['corpus_version', 'source_ref', 'state_fips'] as $column) {
            $conn->statement("ALTER TABLE addresses ALTER COLUMN {$column} SET NOT NULL");
        }

        // The dedupe invariant. `source` was already NOT NULL in B1.2, so all
        // three members of this key are now non-nullable and the constraint
        // cannot be bypassed by a NULL.
        $conn->statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS addresses_corpus_source_ref
             ON addresses (corpus_version, source, source_ref)'
        );

        // Bounded scope lookup: "what did the Florida import load?" and the
        // scoped DELETE that retires it. Without this, retiring one import
        // means a sequential scan of a nationwide table.
        $conn->statement(
            'CREATE INDEX IF NOT EXISTS addresses_corpus_state
             ON addresses (corpus_version, state_fips)'
        );
    }

    /**
     * Reverses exactly what up() added and nothing else.
     *
     * Note this migration does NOT drop the `addresses` table — it does not own
     * it. B1.2 migration 09/11 created that table and its own rollback is what
     * removes it. Dropping it here would destroy a table this migration only
     * extended, taking the address corpus with it.
     */
    public function down(): void
    {
        $this->guardSpatialConnection();
        $conn = DB::connection($this->getConnection());

        $conn->statement('DROP INDEX IF EXISTS addresses_corpus_source_ref');
        $conn->statement('DROP INDEX IF EXISTS addresses_corpus_state');

        foreach (['corpus_version', 'source_ref', 'state_fips', 'first_seen', 'last_seen'] as $column) {
            $conn->statement("ALTER TABLE addresses DROP COLUMN IF EXISTS {$column}");
        }
    }

    private function guardSpatialConnection(): void
    {
        $name = $this->getConnection();
        $conf = config("database.connections.{$name}");

        if (empty($conf) || (empty($conf['url']) && empty($conf['host']))) {
            throw new \RuntimeException(
                "[B1.2/spatial] Connection [{$name}] is not configured. Set SPATIAL_DATABASE_URL "
                . "(or SPATIAL_PGHOST/SPATIAL_PGDATABASE), then run: "
                . "php artisan migrate --path=database/migrations/spatial --database=pgsql_spatial"
            );
        }

        $driver = DB::connection($name)->getDriverName();
        if ($driver !== 'pgsql') {
            throw new \RuntimeException(
                "[B1.2/spatial] Connection [{$name}] resolves to driver [{$driver}], not 'pgsql'. "
                . "Refusing to execute PostGIS DDL. Use --database=pgsql_spatial."
            );
        }
    }
}
