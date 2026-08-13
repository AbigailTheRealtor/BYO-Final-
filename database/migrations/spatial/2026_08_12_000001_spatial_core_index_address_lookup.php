<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The index the AddressPoint rung actually needs.
 *
 * WHAT WAS MISSING
 * ----------------
 * `AddressPointCoordinateAdapter` resolves by equality on
 * `(corpus_version, normalized)` — the canonical unit-free lookup line. The
 * table shipped with three indexes and none of them serve that query:
 *
 *   addresses_geom      GiST on geom       — proximity, not lookup
 *   addresses_trgm      GIN trigram        — typeahead similarity
 *   addresses_city_zip  (state, city, postcode)
 *
 * A GIN trigram index *can* answer an equality predicate, and it answers it
 * badly: it matches trigrams, produces candidate rows and rechecks each one.
 * On a Florida-scale corpus that is the difference between an index probe and a
 * scan of every address sharing common trigrams with "main st". The rung would
 * have worked in testing on an empty table and degraded exactly when the corpus
 * arrived.
 *
 * WHY BOTH INDEXES STAY
 * ---------------------
 * They answer different questions and the corpus serves both:
 *
 *   btree (corpus_version, normalized)  exact resolution — the coordinate rung
 *   GIN trigram (normalized)            "315 E Mad…" — the suggestion provider
 *
 * Dropping the trigram index would make this migration look cheaper and would
 * delete the typeahead the corpus exists to also serve.
 *
 * COLUMN ORDER
 * ------------
 * `corpus_version` leads. Every query the rung issues pins a version, two
 * versions coexist by design while one is validated, and a leading version
 * column keeps a query against the pinned corpus from touching the other one's
 * rows at all. The reverse order would index a nationwide address space and then
 * filter, which is the same rows in a worse arrangement.
 *
 * CREATE IT AFTER THE BULK LOAD
 * -----------------------------
 * Building a btree over ~12M rows during an import means maintaining it per
 * insert. Loading first and indexing after is markedly faster. This migration
 * is written to run either way — it is idempotent and additive — but the
 * intended order is: migrate schema, load corpus, then create indexes.
 *
 * Additive: no column, index or row is altered or dropped.
 */
class SpatialCoreIndexAddressLookup extends Migration
{
    protected $connection = 'pgsql_spatial';

    public function up(): void
    {
        $this->guardSpatialConnection();

        DB::connection($this->getConnection())->statement(
            'CREATE INDEX IF NOT EXISTS addresses_corpus_normalized
             ON addresses (corpus_version, normalized)'
        );
    }

    public function down(): void
    {
        $this->guardSpatialConnection();

        DB::connection($this->getConnection())->statement('DROP INDEX IF EXISTS addresses_corpus_normalized');
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
