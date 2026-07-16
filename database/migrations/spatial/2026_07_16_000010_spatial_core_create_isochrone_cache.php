<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * B1.2 migration 10/11 — isochrone_cache + index (SSOT §7.2, Phase 6).
 *
 * Routing artifacts, storable because the engine (Valhalla) is ours. Independent
 * table. Additive, guarded, no consumers (Phase 6 populates this).
 */
class SpatialCoreCreateIsochroneCache extends Migration
{
    protected $connection = 'pgsql_spatial';

    public function up(): void
    {
        $this->guardSpatialConnection();
        $conn = DB::connection($this->getConnection());

        $conn->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS isochrone_cache (
              origin_geohash  text     NOT NULL,
              mode            text     NOT NULL,
              minutes         smallint NOT NULL,
              routing_version text     NOT NULL,
              geom            geography(MultiPolygon,4326) NOT NULL,
              computed_at     timestamptz NOT NULL,
              PRIMARY KEY (origin_geohash, mode, minutes, routing_version)
            )
        SQL);

        $conn->statement(
            'CREATE INDEX IF NOT EXISTS isochrone_geom ON isochrone_cache USING gist (geom)'
        );
    }

    public function down(): void
    {
        $this->guardSpatialConnection();
        DB::connection($this->getConnection())->statement('DROP TABLE IF EXISTS isochrone_cache CASCADE');
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
