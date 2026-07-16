<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * B1.2 migration 11/11 — corpus_imports (SSOT §7.2, E-32).
 *
 * Provenance / version ledger: every import writes exactly one row. Independent
 * table. Additive, guarded, no consumers (the Phase 2 import batch writes here).
 */
class SpatialCoreCreateCorpusImports extends Migration
{
    protected $connection = 'pgsql_spatial';

    public function up(): void
    {
        $this->guardSpatialConnection();

        DB::connection($this->getConnection())->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS corpus_imports (
              id bigserial PRIMARY KEY,
              dataset text, corpus_version text, row_count bigint, bytes bigint,
              territory_coverage jsonb,
              started_at timestamptz, finished_at timestamptz, status text, notes jsonb
            )
        SQL);
    }

    public function down(): void
    {
        $this->guardSpatialConnection();
        DB::connection($this->getConnection())->statement('DROP TABLE IF EXISTS corpus_imports CASCADE');
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
