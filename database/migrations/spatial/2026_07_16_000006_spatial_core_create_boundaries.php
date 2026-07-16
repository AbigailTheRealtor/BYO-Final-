<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * B1.2 migration 06/11 — boundaries + boundaries_kind_geom (SSOT §7.2).
 *
 * All polygonal geography: flood zones, FEMA coverage, school districts, ZCTAs,
 * counties, places, protected areas. FK target for boundaries_parts (07), so it
 * MUST precede it. Additive, guarded, no consumers.
 */
class SpatialCoreCreateBoundaries extends Migration
{
    protected $connection = 'pgsql_spatial';

    public function up(): void
    {
        $this->guardSpatialConnection();
        $conn = DB::connection($this->getConnection());

        $conn->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS boundaries (
              id             bigserial PRIMARY KEY,
              kind           text NOT NULL,
              external_ref   text,
              attrs          jsonb,
              geom           geography(MultiPolygon,4326) NOT NULL,
              corpus_version text NOT NULL
            )
        SQL);

        $conn->statement(
            'CREATE INDEX IF NOT EXISTS boundaries_kind_geom ON boundaries USING gist (kind, geom)'
        );
    }

    public function down(): void
    {
        $this->guardSpatialConnection();
        DB::connection($this->getConnection())->statement('DROP TABLE IF EXISTS boundaries CASCADE');
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
